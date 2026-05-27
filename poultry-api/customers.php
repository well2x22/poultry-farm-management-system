<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

require_once __DIR__ . "/db.php";

/* =========================
   GET CUSTOMERS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $customer = $stmt->get_result()->fetch_assoc();

        if ($customer) {
            echo json_encode([
                "status" => true,
                "message" => "Customer retrieved successfully.",
                "data" => $customer
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => false,
                "message" => "Customer not found.",
                "data" => null
            ]);
        }

        exit();
    }

    $result = $conn->query("SELECT * FROM customers ORDER BY id DESC");

    $customers = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }

    echo json_encode([
        "status" => true,
        "message" => "Customers retrieved successfully.",
        "data" => $customers
    ]);

    exit();
}

/* =========================
   POST ACTIONS
========================= */
$input = file_get_contents("php://input");
$data = json_decode($input, true);

$action = $data['action'] ?? '';

if ($action === "add") {
    $customer_name = trim($data['customer_name'] ?? '');
    $contact_number = trim($data['contact_number'] ?? '');
    $address = trim($data['address'] ?? '');

    if ($customer_name === '') {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Customer name is required."
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO customers 
        (customer_name, contact_number, address)
        VALUES (?, ?, ?)
    ");

    $stmt->bind_param("sss", $customer_name, $contact_number, $address);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Customer added successfully through API."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to add customer: " . $stmt->error
        ]);
    }

    exit();
}

if ($action === "update") {
    $customer_id = intval($data['customer_id'] ?? 0);
    $customer_name = trim($data['customer_name'] ?? '');
    $contact_number = trim($data['contact_number'] ?? '');
    $address = trim($data['address'] ?? '');

    if ($customer_id <= 0) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Invalid customer ID."
        ]);
        exit();
    }

    if ($customer_name === '') {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Customer name is required."
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE customers
        SET customer_name = ?, contact_number = ?, address = ?
        WHERE id = ?
    ");

    $stmt->bind_param("sssi", $customer_name, $contact_number, $address, $customer_id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Customer updated successfully through API."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to update customer: " . $stmt->error
        ]);
    }

    exit();
}

if ($action === "delete") {
    $customer_id = intval($data['customer_id'] ?? 0);

    if ($customer_id <= 0) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Invalid customer ID."
        ]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Customer deleted successfully through API."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to delete customer: " . $stmt->error
        ]);
    }

    exit();
}

http_response_code(400);
echo json_encode([
    "status" => false,
    "message" => "Invalid action."
]);