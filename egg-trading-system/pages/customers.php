<?php
require_once __DIR__ . "/../includes/api_client.php";

$message = "";
$messageType = "info";
$editMode = false;
$editCustomer = null;

/* =========================
   DELETE CUSTOMER THROUGH API
========================= */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $response = postToApi("customers.php", [
        "action" => "delete",
        "customer_id" => $delete_id
    ]);

    $message = $response["message"] ?? "Unknown API response.";
    $messageType = ($response["status"] ?? false) ? "success" : "danger";
}

/* =========================
   LOAD CUSTOMER FOR EDIT THROUGH API
========================= */
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);

    $response = getFromApi("customers.php", [
        "id" => $edit_id
    ]);

    if ($response["status"] ?? false) {
        $editMode = true;
        $editCustomer = $response["data"];
    } else {
        $message = $response["message"] ?? "Customer not found.";
        $messageType = "danger";
    }
}

/* =========================
   ADD / UPDATE CUSTOMER THROUGH API
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = intval($_POST['customer_id'] ?? 0);

    $payload = [
        "action" => $customer_id > 0 ? "update" : "add",
        "customer_id" => $customer_id,
        "customer_name" => trim($_POST['customer_name'] ?? ''),
        "contact_number" => trim($_POST['contact_number'] ?? ''),
        "address" => trim($_POST['address'] ?? '')
    ];

    $response = postToApi("customers.php", $payload);

    $message = $response["message"] ?? "Unknown API response.";
    $messageType = ($response["status"] ?? false) ? "success" : "danger";

    if ($response["status"] ?? false) {
        $editMode = false;
        $editCustomer = null;
    }
}

/* =========================
   GET CUSTOMER LIST THROUGH API
========================= */
$customersResponse = getFromApi("customers.php");
$customers = ($customersResponse["status"] ?? false) ? $customersResponse["data"] : [];

if (!($customersResponse["status"] ?? false) && empty($message)) {
    $message = $customersResponse["message"] ?? "Failed to load customers.";
    $messageType = "danger";
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Customers</h4>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType); ?>">
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
            <th width="170">Action</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!empty($customers)): ?>
            <?php foreach ($customers as $row): ?>
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
                            onclick="return confirm('Delete this customer?');"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center">
                    No customers found.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>