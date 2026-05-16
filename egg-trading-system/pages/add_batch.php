<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $batch_code = trim($_POST['batch_code'] ?? '');
    $collection_date = $_POST['collection_date'] ?? '';
    $total_eggs = intval($_POST['total_eggs'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($batch_code === '') {
        $message = "Batch code is required.";
    } elseif ($collection_date === '') {
        $message = "Collection date is required.";
    } elseif ($total_eggs <= 0) {
        $message = "Total eggs must be greater than zero.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO egg_batches 
            (batch_code, collection_date, total_eggs, remarks) 
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("ssis", $batch_code, $collection_date, $total_eggs, $remarks);

        if ($stmt->execute()) {
            $message = "Egg batch added successfully.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Add Egg Batch</h4>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-info">
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