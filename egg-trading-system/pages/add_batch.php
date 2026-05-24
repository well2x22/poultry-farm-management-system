<?php
require_once __DIR__ . "/../includes/api_client.php";

$message = "";
$messageType = "info";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $response = postToApi("add_batch.php", [
        "batch_code" => trim($_POST['batch_code'] ?? ''),
        "collection_date" => $_POST['collection_date'] ?? '',
        "total_eggs" => intval($_POST['total_eggs'] ?? 0),
        "remarks" => trim($_POST['remarks'] ?? '')
    ]);

    $message = $response["message"] ?? "Unknown response.";
    $messageType = ($response["status"] ?? false) ? "success" : "danger";
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Add Egg Batch</h4>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType); ?>">
        <?= htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">

        <form method="POST" action="dashboard.php?page=add_batch">

            <div class="mb-3">
                <label>Batch Code</label>
                <input 
                    type="text" 
                    name="batch_code" 
                    class="form-control" 
                    placeholder="Example: BATCH-001" 
                    required
                >
            </div>

            <div class="mb-3">
                <label>Collection Date</label>
                <input 
                    type="date" 
                    name="collection_date" 
                    class="form-control" 
                    required
                >
            </div>

            <div class="mb-3">
                <label>Total Eggs</label>
                <input 
                    type="number" 
                    name="total_eggs" 
                    class="form-control" 
                    min="1" 
                    required
                >
            </div>

            <div class="mb-3">
                <label>Remarks</label>
                <textarea 
                    name="remarks" 
                    class="form-control" 
                    rows="3"
                    placeholder="Optional remarks..."
                ></textarea>
            </div>

            <button type="submit" class="btn btn-warning">
                Save Batch
            </button>

        </form>

    </div>
</div>