<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";
$messageType = "info";
$editMode = false;
$editSale = null;

$allowed_grades = ['Extra Large', 'Large', 'Medium', 'Small'];

/* =========================
   GET AVAILABLE STOCK TO SELL
   Formula:
   Available To Sell = Total Graded Eggs - Total Sold Eggs
   Laravel inventory is NOT affected.
========================= */
function getAvailableStockToSell($conn, $grade, $exclude_sale_id = 0) {
    $gradedQty = 0;
    $soldQty = 0;

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity), 0) AS total 
        FROM egg_grades 
        WHERE grade = ?
    ");
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $gradedQty = intval($stmt->get_result()->fetch_assoc()['total']);

    if ($exclude_sale_id > 0) {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS total 
            FROM egg_sales 
            WHERE grade = ? 
            AND id != ?
        ");
        $stmt->bind_param("si", $grade, $exclude_sale_id);
    } else {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS total 
            FROM egg_sales 
            WHERE grade = ?
        ");
        $stmt->bind_param("s", $grade);
    }

    $stmt->execute();
    $soldQty = intval($stmt->get_result()->fetch_assoc()['total']);

    return max(0, $gradedQty - $soldQty);
}

/* =========================
   DELETE SALE
   Deleting a sale automatically returns eggs to available stock
   because available stock is computed as graded - sold.
========================= */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt = $conn->prepare("DELETE FROM egg_sales WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "Sale deleted successfully. The eggs are now available for selling again.";
        $messageType = "success";
    } else {
        $message = "Error deleting sale: " . $stmt->error;
        $messageType = "danger";
    }
}

/* =========================
   LOAD SALE FOR EDIT
========================= */
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);

    $stmt = $conn->prepare("SELECT * FROM egg_sales WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $editMode = true;
        $editSale = $result->fetch_assoc();
    } else {
        $message = "Sale record not found.";
        $messageType = "danger";
    }
}

/* =========================
   ADD OR UPDATE SALE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sale_id = intval($_POST['sale_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $grade = $_POST['grade'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    $price_per_piece = floatval($_POST['price_per_piece'] ?? 0);
    $sale_date = $_POST['sale_date'] ?? '';

    $total_amount = $quantity * $price_per_piece;

    $availableStock = in_array($grade, $allowed_grades)
        ? getAvailableStockToSell($conn, $grade, $sale_id)
        : 0;

    if ($customer_id <= 0) {
        $message = "Please select a customer.";
        $messageType = "danger";
    } elseif (!in_array($grade, $allowed_grades)) {
        $message = "Invalid egg size selected.";
        $messageType = "danger";
    } elseif ($quantity <= 0) {
        $message = "Quantity must be greater than zero.";
        $messageType = "danger";
    } elseif ($quantity > $availableStock) {
        $message = "Not enough {$grade} eggs to sell. Available {$grade} eggs: {$availableStock}.";
        $messageType = "danger";
    } elseif ($price_per_piece <= 0) {
        $message = "Price per piece must be greater than zero.";
        $messageType = "danger";
    } elseif ($sale_date === '') {
        $message = "Sale date is required.";
        $messageType = "danger";
    } else {
        if ($sale_id > 0) {
            $stmt = $conn->prepare("
                UPDATE egg_sales
                SET customer_id = ?, grade = ?, quantity = ?, price_per_piece = ?, total_amount = ?, sale_date = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "isiddsi",
                $customer_id,
                $grade,
                $quantity,
                $price_per_piece,
                $total_amount,
                $sale_date,
                $sale_id
            );

            if ($stmt->execute()) {
                $message = "Sale updated successfully. Egg stock to sell was recalculated.";
                $messageType = "success";
                $editMode = false;
                $editSale = null;
            } else {
                $message = "Error updating sale: " . $stmt->error;
                $messageType = "danger";
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO egg_sales 
                (customer_id, grade, quantity, price_per_piece, total_amount, sale_date) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "isidds",
                $customer_id,
                $grade,
                $quantity,
                $price_per_piece,
                $total_amount,
                $sale_date
            );

            if ($stmt->execute()) {
                $message = "Sale recorded successfully. Sold eggs were deducted from the PHP system stock.";
                $messageType = "success";
            } else {
                $message = "Error recording sale: " . $stmt->error;
                $messageType = "danger";
            }
        }
    }
}

/* =========================
   STOCK SUMMARY
========================= */
$stockSummary = [];
$totalRemainingToSell = 0;

foreach ($allowed_grades as $gradeName) {
    $stockSummary[$gradeName] = getAvailableStockToSell($conn, $gradeName);
    $totalRemainingToSell += $stockSummary[$gradeName];
}

$allBatchEggs = intval($conn->query("
    SELECT COALESCE(SUM(total_eggs), 0) AS total 
    FROM egg_batches
")->fetch_assoc()['total']);

$allGradedEggs = intval($conn->query("
    SELECT COALESCE(SUM(quantity), 0) AS total 
    FROM egg_grades
")->fetch_assoc()['total']);

$totalSoldEggs = intval($conn->query("
    SELECT COALESCE(SUM(quantity), 0) AS total 
    FROM egg_sales
")->fetch_assoc()['total']);

/*
    These are display values after sold eggs deduction.
    Laravel inventory remains unchanged.
*/
$totalEggsAfterSold = max(0, $allBatchEggs - $totalSoldEggs);
$totalGradedEggsAfterSold = max(0, $allGradedEggs - $totalSoldEggs);
$totalNotGradedEggs = max(0, $totalEggsAfterSold - $totalGradedEggsAfterSold);

$customers = $conn->query("SELECT * FROM customers ORDER BY customer_name ASC");

$sales = $conn->query("
    SELECT 
        egg_sales.*, 
        customers.customer_name
    FROM egg_sales
    LEFT JOIN customers 
        ON egg_sales.customer_id = customers.id
    ORDER BY egg_sales.id DESC
");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Egg Sales</h4>

</div>

<!-- AVAILABLE TO SELL PER SIZE -->
<div class="row mb-4">
    <?php foreach ($stockSummary as $gradeName => $available): ?>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6><?= htmlspecialchars($gradeName); ?> Available To Sell</h6>
                    <h3><?= $available; ?></h3>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ADD / EDIT SALE FORM -->
<div class="card shadow-sm mb-4">
    <div class="card-body">

        <h5 class="mb-3">
            <?= $editMode ? "Edit Sale" : "Add Sale"; ?>
        </h5>

        <form method="POST" action="dashboard.php?page=sales">

            <input 
                type="hidden" 
                name="sale_id" 
                value="<?= $editMode ? htmlspecialchars($editSale['id']) : 0; ?>"
            >

            <div class="mb-3">
                <label>Customer</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">Select Customer</option>

                    <?php if ($customers && $customers->num_rows > 0): ?>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <option 
                                value="<?= $customer['id']; ?>"
                                <?= $editMode && $editSale['customer_id'] == $customer['id'] ? 'selected' : ''; ?>
                            >
                                <?= htmlspecialchars($customer['customer_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Egg Size</label>
                <select name="grade" class="form-control" required>
                    <option value="">Select Egg Size</option>

                    <?php foreach ($allowed_grades as $gradeName): ?>
                        <option 
                            value="<?= $gradeName; ?>"
                            <?= $editMode && $editSale['grade'] === $gradeName ? 'selected' : ''; ?>
                        >
                            <?= $gradeName; ?> | Available To Sell: <?= $stockSummary[$gradeName]; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Quantity</label>
                <input 
                    type="number" 
                    name="quantity" 
                    class="form-control" 
                    min="1" 
                    value="<?= $editMode ? htmlspecialchars($editSale['quantity']) : ''; ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label>Price Per Piece</label>
                <input 
                    type="number" 
                    name="price_per_piece" 
                    class="form-control" 
                    step="0.01" 
                    min="0.01" 
                    value="<?= $editMode ? htmlspecialchars($editSale['price_per_piece']) : ''; ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label>Sale Date</label>
                <input 
                    type="date" 
                    name="sale_date" 
                    class="form-control" 
                    value="<?= $editMode ? htmlspecialchars($editSale['sale_date']) : ''; ?>"
                    required
                >
            </div>

            <button 
                type="submit" 
                class="btn <?= $editMode ? 'btn-primary' : 'btn-info'; ?>"
            >
                <?= $editMode ? "Update Sale" : "Save Sale"; ?>
            </button>

            <?php if ($editMode): ?>
                <a href="dashboard.php?page=sales" class="btn btn-secondary">
                    Cancel Edit
                </a>
            <?php endif; ?>

        </form>

    </div>
</div>

<h5>Sales Records</h5>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>Customer</th>
            <th>Egg Size</th>
            <th>Quantity Sold</th>
            <th>Price/Piece</th>
            <th>Total Amount</th>
            <th>Sale Date</th>
            <th width="160">Action</th>
        </tr>
    </thead>

    <tbody>
        <?php if ($sales && $sales->num_rows > 0): ?>
            <?php while ($row = $sales->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['grade']); ?></td>
                    <td><?= htmlspecialchars($row['quantity']); ?></td>
                    <td>₱<?= number_format($row['price_per_piece'], 2); ?></td>
                    <td>₱<?= number_format($row['total_amount'], 2); ?></td>
                    <td><?= htmlspecialchars($row['sale_date']); ?></td>
                    <td>
                        <a 
                            href="dashboard.php?page=sales&edit_id=<?= $row['id']; ?>" 
                            class="btn btn-sm btn-primary"
                        >
                            Edit
                        </a>

                        <a 
                            href="dashboard.php?page=sales&delete_id=<?= $row['id']; ?>" 
                            class="btn btn-sm btn-danger"
                            onclick="return confirm('Are you sure you want to delete this sale? This will return the eggs to available stock.');"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center">
                    No sales records found.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>