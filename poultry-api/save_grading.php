<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

require_once __DIR__ . "/db.php";

function getBatchData($conn, $batch_id)
{
    $stmt = $conn->prepare("SELECT * FROM egg_batches WHERE id = ?");
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function getGradeRows($conn, $batch_id)
{
    $stmt = $conn->prepare("
        SELECT * 
        FROM egg_grades 
        WHERE batch_id = ?
        ORDER BY FIELD(grade, 'Extra Large', 'Large', 'Medium', 'Small')
    ");

    $stmt->bind_param("i", $batch_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $batch_id = intval($_GET['batch_id'] ?? 0);

    $batch = getBatchData($conn, $batch_id);

    if (!$batch) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Batch not found.",
            "data" => []
        ]);
        exit();
    }

    $grades = getGradeRows($conn, $batch_id);

    $totalGraded = 0;
    $current = [
        "Extra Large" => 0,
        "Large" => 0,
        "Medium" => 0,
        "Small" => 0
    ];

    $alreadySent = false;
    $canSend = false;

    foreach ($grades as $row) {
        $qty = intval($row['quantity']);
        $totalGraded += $qty;
        $current[$row['grade']] = $qty;

        if (intval($row['sent_to_inventory']) === 1) {
            $alreadySent = true;
        }

        if (intval($row['sent_to_inventory']) === 0) {
            $canSend = true;
        }
    }

    $totalEggs = intval($batch['total_eggs']);
    $notGraded = max(0, $totalEggs - $totalGraded);

    echo json_encode([
        "status" => true,
        "message" => "Grading data retrieved successfully.",
        "data" => [
            "batch" => $batch,
            "grades" => $grades,
            "current" => $current,
            "total_eggs" => $totalEggs,
            "total_graded" => $totalGraded,
            "not_graded" => $notGraded,
            "already_sent" => $alreadySent,
            "can_send" => $canSend
        ]
    ]);
    exit();
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

$action = $data['action'] ?? '';
$batch_id = intval($data['batch_id'] ?? 0);

$batch = getBatchData($conn, $batch_id);

if (!$batch) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "Batch not found."
    ]);
    exit();
}

if ($action === "save") {
    $extraLarge = intval($data['extra_large'] ?? 0);
    $large = intval($data['large'] ?? 0);
    $medium = intval($data['medium'] ?? 0);
    $small = intval($data['small'] ?? 0);

    $totalEggs = intval($batch['total_eggs']);
    $totalGraded = $extraLarge + $large + $medium + $small;

    $sentCheck = $conn->prepare("
        SELECT COUNT(*) AS total_sent 
        FROM egg_grades 
        WHERE batch_id = ? 
        AND sent_to_inventory = 1
    ");
    $sentCheck->bind_param("i", $batch_id);
    $sentCheck->execute();
    $totalSent = intval($sentCheck->get_result()->fetch_assoc()['total_sent']);

    if ($totalSent > 0) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "This batch was already sent to inventory. Editing is disabled."
        ]);
        exit();
    }

    if ($totalGraded <= 0) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Please enter at least one egg quantity."
        ]);
        exit();
    }

    if ($totalGraded > $totalEggs) {
        $excess = $totalGraded - $totalEggs;

        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Graded eggs cannot exceed total eggs. Excess: {$excess}."
        ]);
        exit();
    }

    $deleteStmt = $conn->prepare("DELETE FROM egg_grades WHERE batch_id = ?");
    $deleteStmt->bind_param("i", $batch_id);
    $deleteStmt->execute();

    $grades = [
        "Extra Large" => $extraLarge,
        "Large" => $large,
        "Medium" => $medium,
        "Small" => $small
    ];

    foreach ($grades as $grade => $quantity) {
        if ($quantity > 0) {
            $insertStmt = $conn->prepare("
                INSERT INTO egg_grades
                (batch_id, grade, quantity, sent_to_inventory)
                VALUES (?, ?, ?, 0)
            ");

            $insertStmt->bind_param("isi", $batch_id, $grade, $quantity);
            $insertStmt->execute();
        }
    }

    $remaining = $totalEggs - $totalGraded;

    echo json_encode([
        "status" => true,
        "message" => "Egg grading saved through API. Remaining ungraded eggs: {$remaining}."
    ]);
    exit();
}

if ($action === "send_inventory") {
    $stmt = $conn->prepare("
        SELECT 
            egg_grades.id,
            egg_grades.grade,
            egg_grades.quantity,
            egg_batches.batch_code,
            egg_batches.collection_date
        FROM egg_grades
        INNER JOIN egg_batches 
            ON egg_grades.batch_id = egg_batches.id
        WHERE egg_grades.batch_id = ?
        AND egg_grades.sent_to_inventory = 0
    ");

    $stmt->bind_param("i", $batch_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "No graded eggs available to send. This batch may already have been sent."
        ]);
        exit();
    }

    $successCount = 0;

    while ($row = $result->fetch_assoc()) {
        $batchCode = $row['batch_code'];
        $eggSize = $row['grade'];
        $quantity = intval($row['quantity']);
        $receivedDate = $row['collection_date'];

        $checkStmt = $conn->prepare("
            SELECT id 
            FROM egg_inventories
            WHERE batch_code = ?
            AND egg_size = ?
            LIMIT 1
        ");

        $checkStmt->bind_param("ss", $batchCode, $eggSize);
        $checkStmt->execute();

        $existing = $checkStmt->get_result();

        if ($existing && $existing->num_rows > 0) {
            $inventory = $existing->fetch_assoc();
            $inventoryId = intval($inventory['id']);

            $updateInv = $conn->prepare("
                UPDATE egg_inventories
                SET quantity = ?, received_date = ?
                WHERE id = ?
            ");

            $updateInv->bind_param("isi", $quantity, $receivedDate, $inventoryId);
            $updateInv->execute();
        } else {
            $insertInv = $conn->prepare("
                INSERT INTO egg_inventories
                (batch_code, egg_size, quantity, received_date)
                VALUES (?, ?, ?, ?)
            ");

            $insertInv->bind_param("ssis", $batchCode, $eggSize, $quantity, $receivedDate);
            $insertInv->execute();
        }

        $updateGrade = $conn->prepare("
            UPDATE egg_grades
            SET sent_to_inventory = 1
            WHERE id = ?
        ");

        $updateGrade->bind_param("i", $row['id']);
        $updateGrade->execute();

        $successCount++;
    }

    echo json_encode([
        "status" => true,
        "message" => "{$successCount} graded egg record(s) sent to inventory through API."
    ]);
    exit();
}

http_response_code(400);
echo json_encode([
    "status" => false,
    "message" => "Invalid action."
]);