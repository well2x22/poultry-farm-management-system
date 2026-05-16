<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $grade = $_POST['grade'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    $price_per_piece = floatval($_POST['price_per_piece'] ?? 0);
    $sale_date = $_POST['sale_date'] ?? '';

    $allowed_grades = ['Large', 'Medium', 'Small', 'Cracked'];

    $total_amount = $quantity * $price_per_piece;

    if ($customer_id <= 0) {
        $message = "Please select a customer.";
    } elseif (!in_array($grade, $allowed_grades)) {
        $message = "Invalid egg size selected.";
    } elseif ($quantity <= 0) {
        $message = "Quantity must be greater than zero.";
    } elseif ($price_per_piece <= 0) {
        $message = "Price per piece must be greater than zero.";
    } elseif ($sale_date === '') {
        $message = "Sale date is required.";
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
            $message = "Error: " . $stmt->error;
        }
    }
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

<div class="card shadow-sm mb-4">
    <div class="card-body">

        <form method="POST" action="dashboard.php?page=sales">

            <div class="mb-3">
                <label>Customer</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">Select Customer</option>

                    <?php if ($customers && $customers->num_rows > 0): ?>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <option value="<?= $customer['id']; ?>">
                                <?= htmlspecialchars($customer['customer_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Egg Size / Grade</label>
                <select name="grade" class="form-control" required>
                    <option value="">Select Egg Size</option>
                    <option value="Large">Large</option>
                    <option value="Medium">Medium</option>
                    <option value="Small">Small</option>
                    <option value="Cracked">Cracked</option>
                </select>
            </div>

            <div class="mb-3">
                <label>Quantity</label>
                <input 
                    type="number" 
                    name="quantity" 
                    class="form-control" 
                    min="1" 
                    placeholder="Enter quantity"
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
                    placeholder="Enter price per piece"
                    required
                >
            </div>

            <div class="mb-3">
                <label>Sale Date</label>
                <input 
                    type="date" 
                    name="sale_date" 
                    class="form-control" 
                    required
                >
            </div>

            <button type="submit" class="btn btn-info">
                Save Sale
            </button>

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
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center">
                    No sales records found.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>