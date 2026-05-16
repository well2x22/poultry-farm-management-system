<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";

if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger'>No batch selected.</div>";
    return;
}

$batch_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM egg_batches WHERE id = ?");
$stmt->bind_param("i", $batch_id);
$stmt->execute();

$batch = $stmt->get_result()->fetch_assoc();

if (!$batch) {
    echo "<div class='alert alert-danger'>Batch not found.</div>";
    return;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $large = intval($_POST['large'] ?? 0);
    $medium = intval($_POST['medium'] ?? 0);
    $small = intval($_POST['small'] ?? 0);
    $cracked = intval($_POST['cracked'] ?? 0);

    $total_grades = $large + $medium + $small + $cracked;

    if ($total_grades > $batch['total_eggs']) {
        $message = "Graded eggs cannot exceed total eggs in the batch.";
    } else {
        $deleteStmt = $conn->prepare("DELETE FROM egg_grades WHERE batch_id = ?");
        $deleteStmt->bind_param("i", $batch_id);
        $deleteStmt->execute();

        $grades = [
            "Large" => $large,
            "Medium" => $medium,
            "Small" => $small,
            "Cracked" => $cracked
        ];

        foreach ($grades as $grade => $quantity) {
            if ($quantity > 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO egg_grades 
                    (batch_id, grade, quantity) 
                    VALUES (?, ?, ?)
                ");

                $insertStmt->bind_param("isi", $batch_id, $grade, $quantity);
                $insertStmt->execute();
            }
        }

        $message = "Egg grading saved successfully.";
    }
}

$existingStmt = $conn->prepare("SELECT * FROM egg_grades WHERE batch_id = ?");
$existingStmt->bind_param("i", $batch_id);
$existingStmt->execute();
$existingGrades = $existingStmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Grade Egg Batch</h4>

    <a href="dashboard.php?page=view_batches" class="btn btn-secondary">
        Back to Batches
    </a>
</div>

<div class="alert alert-secondary">
    <strong>Batch Code:</strong> <?= htmlspecialchars($batch['batch_code']); ?><br>
    <strong>Total Eggs:</strong> <?= htmlspecialchars($batch['total_eggs']); ?><br>
    <strong>Collection Date:</strong> <?= htmlspecialchars($batch['collection_date']); ?>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-info">
        <?= htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">

        <form method="POST" action="dashboard.php?page=grade_batch&id=<?= $batch_id; ?>">

            <div class="row">

                <div class="col-md-3">
                    <label>Large</label>
                    <input type="number" name="large" class="form-control" min="0" value="0">
                </div>

                <div class="col-md-3">
                    <label>Medium</label>
                    <input type="number" name="medium" class="form-control" min="0" value="0">
                </div>

                <div class="col-md-3">
                    <label>Small</label>
                    <input type="number" name="small" class="form-control" min="0" value="0">
                </div>

                <div class="col-md-3">
                    <label>Cracked</label>
                    <input type="number" name="cracked" class="form-control" min="0" value="0">
                </div>

            </div>

            <button type="submit" class="btn btn-warning mt-3">
                Save Grading
            </button>

        </form>

    </div>
</div>

<h5>Current Grading Records</h5>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>Grade</th>
            <th>Quantity</th>
            <th>Sent to Inventory</th>
        </tr>
    </thead>

    <tbody>
        <?php if ($existingGrades && $existingGrades->num_rows > 0): ?>
            <?php while ($row = $existingGrades->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['grade']); ?></td>
                    <td><?= htmlspecialchars($row['quantity']); ?></td>
                    <td><?= $row['sent_to_inventory'] ? "Yes" : "No"; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">No grading records yet.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>