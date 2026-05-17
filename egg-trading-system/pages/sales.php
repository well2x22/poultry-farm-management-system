<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";
$editMode = false;
$editSale = null;

$allowed_grades = ['Large', 'Medium', 'Small', 'Cracked'];

/* =========================
FUNCTION: GET AVAILABLE STOCK
========================= */
function getAvailableStock($conn, $grade, $exclude_sale_id = 0) {
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
            WHERE grade = ? AND id != ?
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
========================= */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt = $conn->prepare("DELETE FROM egg_sales WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "Sale deleted successfully.";
    } else {
        $message = "Error deleting sale: " . $stmt->error;
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

    $availableStock = getAvailableStock($conn, $grade, $sale_id);

    if ($customer_id <= 0) {
        $message = "Please select a customer.";
    } elseif (!in_array($grade, $allowed_grades)) {
        $message = "Invalid egg size selected.";
    } elseif ($quantity <= 0) {
        $message = "Quantity must be greater than zero.";
    } elseif ($quantity > $availableStock) {
        $message = "Not enough stock. Available $grade eggs: " . $availableStock;
    } elseif ($price_per_piece <= 0) {
        $message = "Price per piece must be greater than zero.";
    } elseif ($sale_date === '') {
        $message = "Sale date is required.";
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
                $message = "Sale updated successfully.";
                $editMode = false;
                $editSale = null;
            } else {
                $message = "Error updating sale: " . $stmt->error;
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
                $message = "Sale recorded successfully.";
            } else {
                $message = "Error recording sale: " . $stmt->error;
            }
        }
    }
}

/* =========================
   STOCK SUMMARY
========================= */
$stockSummary = [];

foreach ($allowed_grades as $gradeName) {
    $stockSummary[$gradeName] = getAvailableStock($conn, $gradeName);
}

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

<?php if (!empty($message)): ?>
    <div class="alert alert-info">
        <?= htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <?php foreach ($stockSummary as $gradeName => $available): ?>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6><?= htmlspecialchars($gradeName); ?> Available</h6>
                    <h3><?= $available; ?></h3>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

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
                            <?= $gradeName; ?> 
                            | Available: <?= $stockSummary[$gradeName]; ?>
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
            <th>Quantity</th>
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
                            onclick="return confirm('Are you sure you want to delete this sale?');"
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