<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customer_name === '') {
        $message = "Customer name is required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO customers 
            (customer_name, contact_number, address) 
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param("sss", $customer_name, $contact_number, $address);

        if ($stmt->execute()) {
            $message = "Customer added successfully.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}

$customers = $conn->query("SELECT * FROM customers ORDER BY id DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Customers</h4>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-info">
        <?= htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">

        <form method="POST" action="dashboard.php?page=customers">

            <div class="mb-3">
                <label>Customer Name</label>
                <input 
                    type="text" 
                    name="customer_name" 
                    class="form-control" 
                    placeholder="Enter customer name"
                    required
                >
            </div>

            <div class="mb-3">
                <label>Contact Number</label>
                <input 
                    type="text" 
                    name="contact_number" 
                    class="form-control" 
                    placeholder="Enter contact number"
                >
            </div>

            <div class="mb-3">
                <label>Address</label>
                <textarea 
                    name="address" 
                    class="form-control" 
                    rows="3"
                    placeholder="Enter address"
                ></textarea>
            </div>

            <button type="submit" class="btn btn-success">
                Save Customer
            </button>

        </form>

    </div>
</div>

<h5>Customer Records</h5>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>Customer Name</th>
            <th>Contact Number</th>
            <th>Address</th>
        </tr>
    </thead>

    <tbody>
        <?php if ($customers && $customers->num_rows > 0): ?>
            <?php while ($row = $customers->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['customer_name']); ?></td>
                    <td><?= htmlspecialchars($row['contact_number']); ?></td>
                    <td><?= htmlspecialchars($row['address']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">
                    No customers found.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>