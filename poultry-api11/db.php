<?php

$servername = "localhost";
$username = "root";
$password = "";
$database = "egg_trading_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Database connection failed: " . $conn->connect_error,
        "data" => []
    ]);

    exit();
}

$conn->set_charset("utf8mb4");