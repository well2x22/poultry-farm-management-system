<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/config/database.php";

$database = new Database();
$conn = $database->connect();

/** @var mysqli $conn */

$page = $_GET['page'] ?? 'home';

$allowed_pages = [
    'home',
    'add_batch',
    'view_batches',
    'grade_batch',
    'customers',
    'sales'
];

if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

$totalBatches = $conn->query("SELECT COUNT(*) AS total FROM egg_batches")->fetch_assoc()['total'];

$totalEggs = $conn->query("
    SELECT COALESCE(SUM(total_eggs), 0) AS total 
    FROM egg_batches
")->fetch_assoc()['total'];

$totalGradedEggs = $conn->query("
    SELECT COALESCE(SUM(quantity), 0) AS total 
    FROM egg_grades
")->fetch_assoc()['total'];

$totalNotGradedEggs = max(0, $totalEggs - $totalGradedEggs);

$totalSales = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total 
    FROM egg_sales
")->fetch_assoc()['total'];

$totalCustomers = $conn->query("
    SELECT COUNT(*) AS total 
    FROM customers
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Poultry Egg Trading System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">

        <a class="navbar-brand" href="dashboard.php">
            Poultry Egg System
        </a>

        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
            <a href="dashboard.php?page=add_batch" class="btn btn-outline-light btn-sm">Add Batch</a>
            <a href="dashboard.php?page=view_batches" class="btn btn-outline-light btn-sm">Batches</a>
            <a href="dashboard.php?page=customers" class="btn btn-outline-light btn-sm">Customers</a>
            <a href="dashboard.php?page=sales" class="btn btn-outline-light btn-sm">Sales</a>
            <a href="logout.php" class="btn btn-warning btn-sm">Logout</a>
        </div>

    </div>
</nav>

<div class="container mt-4">

    <!-- DASHBOARD HEADER ALWAYS VISIBLE -->
    <h3>Dashboard</h3>
    <p>Welcome, <?= htmlspecialchars($_SESSION['fullname']); ?></p>

    <!-- DASHBOARD CARDS ALWAYS VISIBLE -->
    <div class="row mt-4">

    <div class="col-md-2">
        <div class="card shadow-sm dashboard-card">
            <div class="card-body">
                <h6>Total Batches</h6>
                <h2><?= $totalBatches; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card shadow-sm dashboard-card">
            <div class="card-body">
                <h6>Total Eggs</h6>
                <h2><?= $totalEggs; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card shadow-sm dashboard-card">
            <div class="card-body">
                <h6>Graded Eggs</h6>
                <h2><?= $totalGradedEggs; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card shadow-sm dashboard-card">
            <div class="card-body">
                <h6>Not Graded</h6>
                <h2><?= $totalNotGradedEggs; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card shadow-sm dashboard-card">
            <div class="card-body">
                <h6>Total Customers</h6>
                <h2><?= $totalCustomers; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-2">
        <div class="card shadow-sm dashboard-card">
            <div class="card-body">
                <h6>Total Sales</h6>
                <h2>₱<?= number_format($totalSales, 2); ?></h2>
            </div>
        </div>
    </div>

</div>

    <hr>

    <!-- MODULE CONTENT LOADS BELOW DASHBOARD CARDS -->
    <div class="mt-4">

        <?php
        if ($page === 'home') {
            echo '
                <div class="alert alert-light border">
                    Select a module from the navigation bar above.
                </div>
            ';
        } elseif ($page === 'add_batch') {
            include __DIR__ . "/pages/add_batch.php";
        } elseif ($page === 'view_batches') {
            include __DIR__ . "/pages/view_batches.php";
        } elseif ($page === 'grade_batch') {
            include __DIR__ . "/pages/grade_batch.php";
        } elseif ($page === 'customers') {
            include __DIR__ . "/pages/customers.php";
        } elseif ($page === 'sales') {
            include __DIR__ . "/pages/sales.php";
        }
        ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>