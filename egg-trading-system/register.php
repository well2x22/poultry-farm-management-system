<?php
session_start();

require_once __DIR__ . "/includes/api_client.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$registerError = "";
$registerSuccess = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $response = postToApi("auth.php", [
        "action" => "register",
        "fullname" => trim($_POST['fullname'] ?? ''),
        "username" => trim($_POST['username'] ?? ''),
        "password" => trim($_POST['password'] ?? ''),
        "confirm_password" => trim($_POST['confirm_password'] ?? '')
    ]);

    if ($response["status"] ?? false) {
        $registerSuccess = $response["message"] ?? "Registration successful.";
    } else {
        $registerError = $response["message"] ?? "Registration failed.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Poultry Egg Trading System</title>
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

                <div class="card-header text-center bg-dark text-white">
                    <h4 class="mb-0">
                        Register Account
                    </h4>
                </div>

                <div class="card-body p-4">

                    <div class="text-center mb-4">
                        <h5 class="fw-bold">Create New Account</h5>
                        <p class="text-muted mb-0">
                            Register a staff account for the system.
                        </p>
                    </div>

                    <?php if (!empty($registerError)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($registerError); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($registerSuccess)): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($registerSuccess); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input 
                                type="text" 
                                name="fullname" 
                                class="form-control" 
                                placeholder="Enter full name"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input 
                                type="text" 
                                name="username" 
                                class="form-control" 
                                placeholder="Create username"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input 
                                type="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Create password"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                class="form-control" 
                                placeholder="Confirm password"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-dark w-100 fw-bold">
                            Register
                        </button>

                    </form>

                    <div class="text-center mt-3">
                        <a href="index.php" class="text-decoration-none">
                            Already have an account? Login here
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>