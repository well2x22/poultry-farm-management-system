<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/database.php";

$database = new Database();
$conn = $database->connect();

if (!$conn) {
    echo json_encode([
        "status" => false,
        "message" => "Database connection failed.",
        "data" => []
    ]);
    exit();
}

$sql = "
    SELECT 
        id,
        batch_code,
        egg_size,
        quantity,
        received_date,
        created_at,
        updated_at
    FROM egg_inventories
    ORDER BY id DESC
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
            "created_at" => $row["created_at"],
            "updated_at" => $row["updated_at"]
        ];
    }
}

echo json_encode([
    "status" => true,
    "message" => "Inventory records retrieved successfully.",
    "data" => $data
]);