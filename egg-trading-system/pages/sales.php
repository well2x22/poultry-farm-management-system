<?php
require_once __DIR__ . "/../includes/api_client.php";

$message = "";
$messageType = "info";
$editMode = false;
$editSale = null;

$allowed_grades = ['Extra Large', 'Large', 'Medium', 'Small'];

/* =========================
   DELETE SALE THROUGH API
========================= */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $response = postToApi("sales.php", [
        "action" => "delete",
        "sale_id" => $delete_id
    ]);

    $message = $response["message"] ?? "Unknown API response.";
    $messageType = ($response["status"] ?? false) ? "success" : "danger";
}

/* =========================
   LOAD SALE FOR EDIT THROUGH API
========================= */
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);

    $response = getFromApi("sales.php", [
        "sale_id" => $edit_id
    ]);

    if ($response["status"] ?? false) {
        $editMode = true;
        $editSale = $response["data"];
    } else {
        $message = $response["message"] ?? "Sale not found.";
        $messageType = "danger";
    }
}

/* =========================
   ADD / UPDATE SALE THROUGH API
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sale_id = intval($_POST['sale_id'] ?? 0);

    $response = postToApi("sales.php", [
        "action" => $sale_id > 0 ? "update" : "add",
        "sale_id" => $sale_id,
        "customer_id" => intval($_POST['customer_id'] ?? 0),
        "grade" => $_POST['grade'] ?? '',
        "quantity" => intval($_POST['quantity'] ?? 0),
        "price_per_piece" => floatval($_POST['price_per_piece'] ?? 0),
        "sale_date" => $_POST['sale_date'] ?? ''
    ]);

    $message = $response["message"] ?? "Unknown API response.";
    $messageType = ($response["status"] ?? false) ? "success" : "danger";

    if ($response["status"] ?? false) {
        $editMode = false;
        $editSale = null;
    }
}

/* =========================
   LOAD SALES PAGE DATA THROUGH API
========================= */
$salesResponse = getFromApi("sales.php");

if (!($salesResponse["status"] ?? false)) {
    $message = $salesResponse["message"] ?? "Failed to load sales data.";
    $messageType = "danger";

    $customers = [];
    $sales = [];
    $stockSummary = [];
    $totalEggsAfterSold = 0;
    $totalGradedEggsAfterSold = 0;
    $totalSoldEggs = 0;
    $totalNotGradedEggs = 0;
} else {
    $data = $salesResponse["data"];

    $customers = $data["customers"];
    $sales = $data["sales"];
    $stockSummary = $data["stock_summary"];

    $totalEggsAfterSold = intval($data["total_eggs_after_sold"]);
    $totalGradedEggsAfterSold = intval($data["total_graded_after_sold"]);
    $totalSoldEggs = intval($data["total_sold_eggs"]);
    $totalNotGradedEggs = intval($data["total_not_graded"]);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Egg Sales</h4>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType); ?>">
        <?= htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <?php foreach ($allowed_grades as $gradeName): ?>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6><?= htmlspecialchars($gradeName); ?> Available To Sell</h6>
                    <h3><?= intval($stockSummary[$gradeName] ?? 0); ?></h3>
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

                    <?php foreach ($customers as $customer): ?>
                        <option 
                            value="<?= $customer['id']; ?>"
                            <?= $editMode && $editSale['customer_id'] == $customer['id'] ? 'selected' : ''; ?>
                        >
                            <?= htmlspecialchars($customer['customer_name']); ?>
                        </option>
                    <?php endforeach; ?>
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
                            <?= $gradeName; ?> | Available To Sell: <?= intval($stockSummary[$gradeName] ?? 0); ?>
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
            <th width="170">Action</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!empty($sales)): ?>
            <?php foreach ($sales as $row): ?>
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
                            onclick="return confirm('Delete this sale through API?');"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center">
                    No sales records found.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>