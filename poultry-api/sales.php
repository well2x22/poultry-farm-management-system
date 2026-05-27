<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

require_once __DIR__ . "/db.php";

$allowed_grades = ['Extra Large', 'Large', 'Medium', 'Small'];

function getAvailableStockToSell($conn, $grade, $exclude_sale_id = 0)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity), 0) AS total
        FROM egg_grades
        WHERE grade = ?
    ");
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $gradedQty = intval($stmt->get_result()->fetch_assoc()['total']);

    if ($exclude_sale_id > 0) {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS total
            FROM egg_sales
            WHERE grade = ?
            AND id != ?
        ");
        $stmt->bind_param("si", $grade, $exclude_sale_id);
    } else {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS total
            FROM egg_sales
            WHERE grade = ?
        ");
        $stmt->bind_param("s", $grade);
    }

    $stmt->execute();
    $soldQty = intval($stmt->get_result()->fetch_assoc()['total']);

    return max(0, $gradedQty - $soldQty);
}

/* =========================
   GET SALES PAGE DATA
========================= */
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $sale_id = intval($_GET['sale_id'] ?? 0);

    if ($sale_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM egg_sales WHERE id = ?");
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();

        $sale = $stmt->get_result()->fetch_assoc();

        if (!$sale) {
            http_response_code(404);
            echo json_encode([
                "status" => false,
                "message" => "Sale record not found.",
                "data" => null
            ]);
            exit();
        }

        echo json_encode([
            "status" => true,
            "message" => "Sale retrieved successfully.",
            "data" => $sale
        ]);
        exit();
    }

    $customersResult = $conn->query("SELECT * FROM customers ORDER BY customer_name ASC");
    $customers = [];

    if ($customersResult) {
        while ($row = $customersResult->fetch_assoc()) {
            $customers[] = $row;
        }
    }

    $salesResult = $conn->query("
        SELECT 
            egg_sales.*,
            customers.customer_name
        FROM egg_sales
        LEFT JOIN customers
            ON egg_sales.customer_id = customers.id
        ORDER BY egg_sales.id DESC
    ");

    $sales = [];

    if ($salesResult) {
        while ($row = $salesResult->fetch_assoc()) {
            $sales[] = $row;
        }
    }

    $stockSummary = [];
    $totalRemainingToSell = 0;

    foreach (['Extra Large', 'Large', 'Medium', 'Small'] as $gradeName) {
        $available = getAvailableStockToSell($conn, $gradeName);
        $stockSummary[$gradeName] = $available;
        $totalRemainingToSell += $available;
    }

    $allBatchEggs = intval($conn->query("
        SELECT COALESCE(SUM(total_eggs), 0) AS total 
        FROM egg_batches
    ")->fetch_assoc()['total']);

    $allGradedEggs = intval($conn->query("
        SELECT COALESCE(SUM(quantity), 0) AS total 
        FROM egg_grades
    ")->fetch_assoc()['total']);

    $totalSoldEggs = intval($conn->query("
        SELECT COALESCE(SUM(quantity), 0) AS total 
        FROM egg_sales
    ")->fetch_assoc()['total']);

    $totalEggsAfterSold = max(0, $allBatchEggs - $totalSoldEggs);
    $totalGradedEggsAfterSold = max(0, $allGradedEggs - $totalSoldEggs);
    $totalNotGradedEggs = max(0, $totalEggsAfterSold - $totalGradedEggsAfterSold);

    echo json_encode([
        "status" => true,
        "message" => "Sales data retrieved successfully.",
        "data" => [
            "customers" => $customers,
            "sales" => $sales,
            "stock_summary" => $stockSummary,
            "total_remaining_to_sell" => $totalRemainingToSell,
            "total_eggs_after_sold" => $totalEggsAfterSold,
            "total_graded_after_sold" => $totalGradedEggsAfterSold,
            "total_sold_eggs" => $totalSoldEggs,
            "total_not_graded" => $totalNotGradedEggs
        ]
    ]);

    exit();
}

/* =========================
   POST ACTIONS
========================= */
$input = file_get_contents("php://input");
$data = json_decode($input, true);

$action = $data['action'] ?? '';

if ($action === "add" || $action === "update") {
    $sale_id = intval($data['sale_id'] ?? 0);
    $customer_id = intval($data['customer_id'] ?? 0);
    $grade = trim($data['grade'] ?? '');
    $quantity = intval($data['quantity'] ?? 0);
    $price_per_piece = floatval($data['price_per_piece'] ?? 0);
    $sale_date = trim($data['sale_date'] ?? '');

    $availableStock = in_array($grade, $allowed_grades)
        ? getAvailableStockToSell($conn, $grade, $sale_id)
        : 0;

    if ($customer_id <= 0) {
        http_response_code(422);
        echo json_encode(["status" => false, "message" => "Please select a customer."]);
        exit();
    }

    if (!in_array($grade, $allowed_grades)) {
        http_response_code(422);
        echo json_encode(["status" => false, "message" => "Invalid egg size selected."]);
        exit();
    }

    if ($quantity <= 0) {
        http_response_code(422);
        echo json_encode(["status" => false, "message" => "Quantity must be greater than zero."]);
        exit();
    }

    if ($quantity > $availableStock) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Not enough {$grade} eggs to sell. Available {$grade} eggs: {$availableStock}."
        ]);
        exit();
    }

    if ($price_per_piece <= 0) {
        http_response_code(422);
        echo json_encode(["status" => false, "message" => "Price per piece must be greater than zero."]);
        exit();
    }

    if ($sale_date === '') {
        http_response_code(422);
        echo json_encode(["status" => false, "message" => "Sale date is required."]);
        exit();
    }

    $total_amount = $quantity * $price_per_piece;

    if ($action === "update" && $sale_id > 0) {
        $stmt = $conn->prepare("
            UPDATE egg_sales
            SET customer_id = ?, grade = ?, quantity = ?, price_per_piece = ?, total_amount = ?, sale_date = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "isiddsi",
            $customer_id,
            $grade,
            $quantity,
            $price_per_piece,
            $total_amount,
            $sale_date,
            $sale_id
        );

        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Sale updated successfully through API."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "message" => "Failed to update sale: " . $stmt->error
            ]);
        }

        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO egg_sales
        (customer_id, grade, quantity, price_per_piece, total_amount, sale_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isidds",
        $customer_id,
        $grade,
        $quantity,
        $price_per_piece,
        $total_amount,
        $sale_date
    );

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Sale recorded successfully through API."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to record sale: " . $stmt->error
        ]);
    }

    exit();
}

if ($action === "delete") {
    $sale_id = intval($data['sale_id'] ?? 0);

    if ($sale_id <= 0) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Invalid sale ID."
        ]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM egg_sales WHERE id = ?");
    $stmt->bind_param("i", $sale_id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Sale deleted successfully through API."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to delete sale: " . $stmt->error
        ]);
    }

    exit();
}

http_response_code(400);
echo json_encode([
    "status" => false,
    "message" => "Invalid action."
]);