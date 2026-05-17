<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$message = "";
$messageType = "info";

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

$totalEggsInBatch = intval($batch['total_eggs']);

/* =========================
   SAVE GRADING
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_grading'])) {
    $large = intval($_POST['large'] ?? 0);
    $medium = intval($_POST['medium'] ?? 0);
    $small = intval($_POST['small'] ?? 0);
    $cracked = intval($_POST['cracked'] ?? 0);

    $totalGradedEggs = $large + $medium + $small + $cracked;

    if ($large < 0 || $medium < 0 || $small < 0 || $cracked < 0) {
        $message = "Egg quantities cannot be negative.";
        $messageType = "danger";
    } elseif ($totalGradedEggs <= 0) {
        $message = "Please enter at least one egg quantity.";
        $messageType = "danger";
    } elseif ($totalGradedEggs > $totalEggsInBatch) {
        $excess = $totalGradedEggs - $totalEggsInBatch;

        $message = "Graded eggs cannot exceed total eggs in the batch. Total eggs: {$totalEggsInBatch}, graded eggs: {$totalGradedEggs}, excess: {$excess}.";
        $messageType = "danger";
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
                    (batch_id, grade, quantity, sent_to_inventory) 
                    VALUES (?, ?, ?, 0)
                ");

                $insertStmt->bind_param("isi", $batch_id, $grade, $quantity);
                $insertStmt->execute();
            }
        }

        $remaining = $totalEggsInBatch - $totalGradedEggs;

        $message = "Egg grading saved successfully. Remaining ungraded eggs: {$remaining}.";
        $messageType = "success";
    }
}

/* =========================
   SEND TO INVENTORY API
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_grading'])) {
    $large = intval($_POST['large'] ?? 0);
    $medium = intval($_POST['medium'] ?? 0);
    $small = intval($_POST['small'] ?? 0);
    $cracked = intval($_POST['cracked'] ?? 0);

    $totalGradedEggs = $large + $medium + $small + $cracked;

    $sentCheckStmt = $conn->prepare("
        SELECT COUNT(*) AS total_sent 
        FROM egg_grades 
        WHERE batch_id = ? 
        AND sent_to_inventory = 1
    ");
    $sentCheckStmt->bind_param("i", $batch_id);
    $sentCheckStmt->execute();
    $totalSentRecords = intval($sentCheckStmt->get_result()->fetch_assoc()['total_sent']);

    if ($totalSentRecords > 0) {
        $message = "This batch was already sent to inventory. You cannot edit grading after sending.";
        $messageType = "danger";
    } elseif ($large < 0 || $medium < 0 || $small < 0 || $cracked < 0) {
        $message = "Egg quantities cannot be negative.";
        $messageType = "danger";
    } elseif ($totalGradedEggs <= 0) {
        $message = "Please enter at least one egg quantity.";
        $messageType = "danger";
    } elseif ($totalGradedEggs > $totalEggsInBatch) {
        $excess = $totalGradedEggs - $totalEggsInBatch;
        $message = "Graded eggs cannot exceed total eggs in the batch. Total eggs: {$totalEggsInBatch}, graded eggs: {$totalGradedEggs}, excess: {$excess}.";
        $messageType = "danger";
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
                    (batch_id, grade, quantity, sent_to_inventory) 
                    VALUES (?, ?, ?, 0)
                ");

                $insertStmt->bind_param("isi", $batch_id, $grade, $quantity);
                $insertStmt->execute();
            }
        }

        $remaining = $totalEggsInBatch - $totalGradedEggs;

        $message = "Egg grading saved successfully. Remaining ungraded eggs: {$remaining}.";
        $messageType = "success";
    }
}

/* =========================
   GET CURRENT GRADING RECORDS
========================= */
$existingStmt = $conn->prepare("SELECT * FROM egg_grades WHERE batch_id = ?");
$existingStmt->bind_param("i", $batch_id);
$existingStmt->execute();
$existingGrades = $existingStmt->get_result();

$currentLarge = 0;
$currentMedium = 0;
$currentSmall = 0;
$currentCracked = 0;
$currentTotalGraded = 0;

$gradeRows = [];

while ($row = $existingGrades->fetch_assoc()) {
    $gradeRows[] = $row;
    $currentTotalGraded += intval($row['quantity']);

    if ($row['grade'] === "Large") {
        $currentLarge = intval($row['quantity']);
    } elseif ($row['grade'] === "Medium") {
        $currentMedium = intval($row['quantity']);
    } elseif ($row['grade'] === "Small") {
        $currentSmall = intval($row['quantity']);
    } elseif ($row['grade'] === "Cracked") {
        $currentCracked = intval($row['quantity']);
    }
}

$currentRemaining = $totalEggsInBatch - $currentTotalGraded;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Grade Egg Batch</h4>

    <a href="dashboard.php?page=view_batches" class="btn btn-secondary">
        Back to Batches
    </a>
</div>

<div class="alert alert-secondary">
    <strong>Batch Code:</strong> <?= htmlspecialchars($batch['batch_code']); ?><br>
    <strong>Total Eggs in Batch:</strong> <?= htmlspecialchars($totalEggsInBatch); ?><br>
    <strong>Currently Graded:</strong> <?= htmlspecialchars($currentTotalGraded); ?><br>
    <strong>Remaining Ungraded:</strong> <?= htmlspecialchars(max(0, $currentRemaining)); ?><br>
    <strong>Collection Date:</strong> <?= htmlspecialchars($batch['collection_date']); ?>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType; ?>">
        <?= htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">

        <form method="POST" action="dashboard.php?page=grade_batch&id=<?= $batch_id; ?>">

            <div class="row">

                <div class="col-md-3">
                    <label>Large</label>
                    <input 
                        type="number" 
                        name="large" 
                        class="form-control egg-input" 
                        min="0" 
                        value="<?= $currentLarge; ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label>Medium</label>
                    <input 
                        type="number" 
                        name="medium" 
                        class="form-control egg-input" 
                        min="0" 
                        value="<?= $currentMedium; ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label>Small</label>
                    <input 
                        type="number" 
                        name="small" 
                        class="form-control egg-input" 
                        min="0" 
                        value="<?= $currentSmall; ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label>Cracked</label>
                    <input 
                        type="number" 
                        name="cracked" 
                        class="form-control egg-input" 
                        min="0" 
                        value="<?= $currentCracked; ?>"
                    >
                </div>

            </div>

            <div class="alert alert-light border mt-3">
                <strong>Total Entered:</strong> <span id="totalEntered"><?= $currentTotalGraded; ?></span> /
                <strong>Total Eggs:</strong> <?= $totalEggsInBatch; ?><br>
                <strong>Remaining:</strong> <span id="remainingEggs"><?= max(0, $currentRemaining); ?></span>
            </div>

            <button type="submit" name="save_grading" class="btn btn-warning">
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
        <?php if (!empty($gradeRows)): ?>
            <?php foreach ($gradeRows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['grade']); ?></td>
                    <td><?= htmlspecialchars($row['quantity']); ?></td>
                    <td>
                        <?php if ($row['sent_to_inventory']): ?>
                            <span class="badge bg-success">Yes</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">No grading records yet.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<form method="POST" action="dashboard.php?page=grade_batch&id=<?= $batch_id; ?>">
    <button 
        type="submit" 
        name="send_inventory" 
        class="btn btn-success"
        onclick="return confirm('Send unsent graded eggs to inventory API?');"
        <?= empty($gradeRows) ? 'disabled' : ''; ?>
    >
        Send to Inventory API
    </button>
</form>

<script>
const totalEggs = <?= $totalEggsInBatch; ?>;
const inputs = document.querySelectorAll('.egg-input');
const totalEntered = document.getElementById('totalEntered');
const remainingEggs = document.getElementById('remainingEggs');

function calculateTotal() {
    let total = 0;

    inputs.forEach(input => {
        total += parseInt(input.value || 0);
    });

    let remaining = totalEggs - total;

    totalEntered.textContent = total;
    remainingEggs.textContent = remaining >= 0 ? remaining : 0;

    if (total > totalEggs) {
        totalEntered.classList.add('text-danger', 'fw-bold');
        remainingEggs.classList.add('text-danger', 'fw-bold');
    } else {
        totalEntered.classList.remove('text-danger', 'fw-bold');
        remainingEggs.classList.remove('text-danger', 'fw-bold');
    }
}

inputs.forEach(input => {
    input.addEventListener('input', calculateTotal);
});
</script>