<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . "/db.php";

$input = file_get_contents("php://input");
$data = json_decode($input, true);

$batch_code = trim($data['batch_code'] ?? '');
$collection_date = $data['collection_date'] ?? '';
$total_eggs = intval($data['total_eggs'] ?? 0);
$remarks = trim($data['remarks'] ?? '');

if ($batch_code === '') {
    http_response_code(422);
    echo json_encode([
        "status" => false,
        "message" => "Batch code is required."
    ]);
    exit();
}

if ($collection_date === '') {
    http_response_code(422);
    echo json_encode([
        "status" => false,
        "message" => "Collection date is required."
    ]);
    exit();
}

if ($total_eggs <= 0) {
    http_response_code(422);
    echo json_encode([
        "status" => false,
        "message" => "Total eggs must be greater than zero."
    ]);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO egg_batches 
    (batch_code, collection_date, total_eggs, remarks) 
    VALUES (?, ?, ?, ?)
");

$stmt->bind_param(
    "ssis",
    $batch_code,
    $collection_date,
    $total_eggs,
    $remarks
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Egg batch added successfully through API."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to add batch: " . $stmt->error
    ]);
}