<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/db.php";

$sql = "
    SELECT 
        egg_grades.id,
        egg_batches.batch_code,
        egg_grades.grade AS egg_size,
        egg_grades.quantity,
        egg_batches.collection_date AS received_date,
        egg_grades.sent_to_inventory,
        egg_grades.created_at
    FROM egg_grades
    INNER JOIN egg_batches 
        ON egg_grades.batch_id = egg_batches.id
    ORDER BY egg_batches.collection_date DESC, egg_grades.id DESC
";

$result = $conn->query($sql);

$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "id" => intval($row["id"]),
            "batch_code" => $row["batch_code"],
            "egg_size" => $row["egg_size"],
            "quantity" => intval($row["quantity"]),
            "received_date" => $row["received_date"],
            "sent_to_inventory" => intval($row["sent_to_inventory"]),
            "created_at" => $row["created_at"],
            "updated_at" => $row["created_at"]
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Graded egg inventory records retrieved successfully.",
        "data" => $data
    ]);
} else {
    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Query failed: " . $conn->error,
        "data" => []
    ]);
}