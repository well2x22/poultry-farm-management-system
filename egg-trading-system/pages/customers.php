<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";
$editMode = false;
$editCustomer = null;

/* =========================
   DELETE CUSTOMER
========================= */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "Customer deleted successfully.";
    } else {
        $message = "Error deleting customer: " . $stmt->error;
    }
}

/* =========================
   LOAD CUSTOMER FOR EDIT
========================= */
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);

    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $editMode = true;
        $editCustomer = $result->fetch_assoc();
    } else {
        $message = "Customer not found.";
    }
}

/* =========================
   ADD OR UPDATE CUSTOMER
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customer_name === '') {
        $message = "Customer name is required.";
    } else {

        if ($customer_id > 0) {
            // UPDATE CUSTOMER
            $stmt = $conn->prepare("
                UPDATE customers 
                SET customer_name = ?, contact_number = ?, address = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sssi",
                $customer_name,
                $contact_number,
                $address,
                $customer_id
            );

            if ($stmt->execute()) {
                $message = "Customer updated successfully.";
                $editMode = false;
                $editCustomer = null;
            } else {
                $message = "Error updating customer: " . $stmt->error;
            }

        } else {
            // ADD CUSTOMER
            $stmt = $conn->prepare("
                INSERT INTO customers 
                (customer_name, contact_number, address) 
                VALUES (?, ?, ?)
            ");

            $stmt->bind_param(
                "sss",
                $customer_name,
                $contact_number,
                $address
            );

            if ($stmt->execute()) {
                $message = "Customer added successfully.";
            } else {
                $message = "Error adding customer: " . $stmt->error;
            }
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

        <h5 class="mb-3">
            <?= $editMode ? "Edit Customer" : "Add Customer"; ?>
        </h5>

        <form method="POST" action="dashboard.php?page=customers">

            <input 
                type="hidden" 
                name="customer_id" 
                value="<?= $editMode ? htmlspecialchars($editCustomer['id']) : 0; ?>"
            >

            <div class="mb-3">
                <label>Customer Name</label>
                <input 
                    type="text" 
                    name="customer_name" 
                    class="form-control" 
                    placeholder="Enter customer name"
                    value="<?= $editMode ? htmlspecialchars($editCustomer['customer_name']) : ''; ?>"
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
                    value="<?= $editMode ? htmlspecialchars($editCustomer['contact_number']) : ''; ?>"
                >
            </div>

            <div class="mb-3">
                <label>Address</label>
                <textarea 
                    name="address" 
                    class="form-control" 
                    rows="3"
                    placeholder="Enter address"
                ><?= $editMode ? htmlspecialchars($editCustomer['address']) : ''; ?></textarea>
            </div>

            <button 
                type="submit" 
                class="btn <?= $editMode ? 'btn-primary' : 'btn-success'; ?>"
            >
                <?= $editMode ? "Update Customer" : "Save Customer"; ?>
            </button>

            <?php if ($editMode): ?>
                <a href="dashboard.php?page=customers" class="btn btn-secondary">
                    Cancel Edit
                </a>
            <?php endif; ?>

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
            <th width="160">Action</th>
        </tr>
    </thead>

    <tbody>
        <?php if ($customers && $customers->num_rows > 0): ?>
            <?php while ($row = $customers->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['customer_name']); ?></td>
                    <td><?= htmlspecialchars($row['contact_number']); ?></td>
                    <td><?= htmlspecialchars($row['address']); ?></td>
                    <td>
                        <a 
                            href="dashboard.php?page=customers&edit_id=<?= $row['id']; ?>" 
                            class="btn btn-sm btn-primary"
                        >
                            Edit
                        </a>

                        <a 
                            href="dashboard.php?page=customers&delete_id=<?= $row['id']; ?>" 
                            class="btn btn-sm btn-danger"
                            onclick="return confirm('Are you sure you want to delete this customer?');"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center">
                    No customers found.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>