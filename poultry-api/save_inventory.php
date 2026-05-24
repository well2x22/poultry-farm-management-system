<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . "/db.php";

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON data."
    ]);
    exit();
}

$batch_code = trim($data['batch_code'] ?? '');
$egg_size = trim($data['egg_size'] ?? '');
$quantity = intval($data['quantity'] ?? 0);
$received_date = trim($data['received_date'] ?? '');

$allowed_sizes = ['Extra Large', 'Large', 'Medium', 'Small'];

if ($batch_code === '') {
    http_response_code(422);
    echo json_encode([
        "status" => false,
        "message" => "Batch code is required."
    ]);
    exit();
}

if (!in_array($egg_size, $allowed_sizes)) {
    http_response_code(422);
    echo json_encode([
        "status" => false,
        "message" => "Invalid egg size."
    ]);
    exit();
}

if ($quantity <= 0) {
    http_response_code(422);
    echo json_encode([
        "status" => false,
        "message" => "Quantity must be greater than zero."
    ]);
    exit();
}

if ($received_date === '') {
    $received_date = date("Y-m-d");
}

/*
    Prevent duplicate inventory inflation.

    If same batch_code + egg_size exists:
        replace quantity
    Else:
        insert new record
*/

$checkStmt = $conn->prepare("
    SELECT id 
    FROM egg_inventories 
    WHERE batch_code = ? 
    AND egg_size = ?
    LIMIT 1
");

$checkStmt->bind_param("ss", $batch_code, $egg_size);
$checkStmt->execute();

$existing = $checkStmt->get_result();

if ($existing && $existing->num_rows > 0) {
    $row = $existing->fetch_assoc();
    $inventory_id = intval($row['id']);

    $updateStmt = $conn->prepare("
        UPDATE egg_inventories
        SET quantity = ?, received_date = ?
        WHERE id = ?
    ");

    $updateStmt->bind_param(
        "isi",
        $quantity,
        $received_date,
        $inventory_id
    );

    if ($updateStmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Existing inventory updated successfully."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to update inventory: " . $updateStmt->error
        ]);
    }

} else {
    $insertStmt = $conn->prepare("
        INSERT INTO egg_inventories
        (batch_code, egg_size, quantity, received_date)
        VALUES (?, ?, ?, ?)
    ");

    $insertStmt->bind_param(
        "ssis",
        $batch_code,
        $egg_size,
        $quantity,
        $received_date
    );

    if ($insertStmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "status" => true,
            "message" => "New inventory record saved successfully."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to save inventory: " . $insertStmt->error
        ]);
    }
}