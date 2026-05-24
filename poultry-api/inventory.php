<?php
include "db.php";

$method = $_SERVER["REQUEST_METHOD"];

if ($method == "GET") {
    $result = $conn->query("SELECT * FROM inventory ORDER BY id DESC");

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

if ($method == "POST") {
    $input = json_decode(file_get_contents("php://input"), true);

    $batch_code = $input["batch_code"];
    $egg_size = $input["egg_size"];
    $quantity = $input["quantity"];
    $received_date = $input["received_date"];

    $stmt = $conn->prepare("INSERT INTO inventory (batch_code, egg_size, quantity, received_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $batch_code, $egg_size, $quantity, $received_date);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Inventory added successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to add inventory"
        ]);
    }

    exit;
}
?>