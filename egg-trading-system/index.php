<?php
session_start();

require_once __DIR__ . "/includes/api_client.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $response = postToApi("auth.php", [
        "action" => "login",
        "username" => trim($_POST['username'] ?? ''),
        "password" => trim($_POST['password'] ?? '')
    ]);

    if ($response["status"] ?? false) {
        $user = $response["data"];

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];

        header("Location: dashboard.php");
        exit();
    } else {
        $error = $response["message"] ?? "Login failed.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Poultry Egg Trading System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >

    <link href="assets/css/style.css?v=6" rel="stylesheet">
</head>

<body class="login-bg">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-4">

            <div class="card shadow-lg border-0 login-card">

                <div class="card-header text-center bg-warning">
                    <h4 class="mb-0 login-title">
                        Poultry Egg Trading System
                    </h4>
                </div>

                <div class="card-body p-4">

                    <div class="text-center mb-4">
                        <h5 class="fw-bold">Welcome Back</h5>
                        <p class="text-muted mb-0">
                            Sign in to manage egg trading and grading records.
                        </p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input 
                                type="text" 
                                name="username" 
                                class="form-control" 
                                placeholder="Enter username"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input 
                                type="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Enter password"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            Login
                        </button>

                    </form>

                    <div class="text-center mt-3">
                        <a href="register.php" class="text-decoration-none">
                            Create new account
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>