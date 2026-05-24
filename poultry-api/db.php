<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "egg_trading_db");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}
?>