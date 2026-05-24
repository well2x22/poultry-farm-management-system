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
        "message" => "Invalid JSON request.",
        "data" => null
    ]);
    exit();
}

$action = $data['action'] ?? '';

/* =========================
   LOGIN THROUGH API
========================= */
if ($action === "login") {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if ($username === '' || $password === '') {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Please enter username and password.",
            "data" => null
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT id, fullname, username, password, role 
        FROM users 
        WHERE username = ? 
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if (!$result || $result->num_rows !== 1) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Username not found.",
            "data" => null
        ]);
        exit();
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Password is incorrect.",
            "data" => null
        ]);
        exit();
    }

    unset($user['password']);

    echo json_encode([
        "status" => true,
        "message" => "Login successful.",
        "data" => $user
    ]);
    exit();
}

/* =========================
   REGISTER THROUGH API
========================= */
if ($action === "register") {
    $fullname = trim($data['fullname'] ?? '');
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $confirmPassword = trim($data['confirm_password'] ?? '');
    $role = "staff";

    if ($fullname === '') {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Full name is required.",
            "data" => null
        ]);
        exit();
    }

    if ($username === '') {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Username is required.",
            "data" => null
        ]);
        exit();
    }

    if (strlen($username) < 4) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Username must be at least 4 characters.",
            "data" => null
        ]);
        exit();
    }

    if ($password === '') {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Password is required.",
            "data" => null
        ]);
        exit();
    }

    if (strlen($password) < 6) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Password must be at least 6 characters.",
            "data" => null
        ]);
        exit();
    }

    if ($password !== $confirmPassword) {
        http_response_code(422);
        echo json_encode([
            "status" => false,
            "message" => "Passwords do not match.",
            "data" => null
        ]);
        exit();
    }

    $checkStmt = $conn->prepare("
        SELECT id 
        FROM users 
        WHERE username = ? 
        LIMIT 1
    ");

    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();

    $checkResult = $checkStmt->get_result();

    if ($checkResult && $checkResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            "status" => false,
            "message" => "Username already exists.",
            "data" => null
        ]);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users 
        (fullname, username, password, role)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssss",
        $fullname,
        $username,
        $hashedPassword,
        $role
    );

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "status" => true,
            "message" => "Registration successful. You can now log in.",
            "data" => null
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Registration failed: " . $stmt->error,
            "data" => null
        ]);
    }

    exit();
}

http_response_code(400);
echo json_encode([
    "status" => false,
    "message" => "Invalid action.",
    "data" => null
]);