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

$sentCheckStmt = $conn->prepare("
    SELECT COUNT(*) AS total_sent 
    FROM egg_grades 
    WHERE batch_id = ? 
    AND sent_to_inventory = 1
");
$sentCheckStmt->bind_param("i", $batch_id);
$sentCheckStmt->execute();
$totalSentRecords = intval($sentCheckStmt->get_result()->fetch_assoc()['total_sent']);

$alreadySent = $totalSentRecords > 0;

/* =========================
   SAVE GRADING
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_grading'])) {
    $extra_large = intval($_POST['extra_large'] ?? 0);
    $large = intval($_POST['large'] ?? 0);
    $medium = intval($_POST['medium'] ?? 0);
    $small = intval($_POST['small'] ?? 0);

    $totalGradedEggs = $extra_large + $large + $medium + $small;

    if ($alreadySent) {
        $message = "This batch was already sent to inventory. You cannot edit grading after sending.";
        $messageType = "danger";
    } elseif ($extra_large < 0 || $large < 0 || $medium < 0 || $small < 0) {
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
            "Extra Large" => $extra_large,
            "Large" => $large,
            "Medium" => $medium,
            "Small" => $small
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

        $alreadySent = false;
    }
}

/* =========================
   SEND GRADED EGGS TO INVENTORY API
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['send_inventory'])) {

    /*
        New API layer path.
        This replaces the old Laravel API URL.
    */
    $apiUrl = "http://localhost/poultry-farm-management-system/poultry-api/save_inventory.php";

    $gradeStmt = $conn->prepare("
        SELECT 
            egg_grades.id,
            egg_grades.grade,
            egg_grades.quantity,
            egg_grades.sent_to_inventory,
            egg_batches.batch_code,
            egg_batches.collection_date
        FROM egg_grades
        INNER JOIN egg_batches 
            ON egg_grades.batch_id = egg_batches.id
        WHERE egg_grades.batch_id = ?
        AND egg_grades.sent_to_inventory = 0
    ");

    $gradeStmt->bind_param("i", $batch_id);
    $gradeStmt->execute();

    $gradesResult = $gradeStmt->get_result();

    if ($gradesResult->num_rows === 0) {
        $message = "No graded eggs available to send. This batch may already have been sent to inventory.";
        $messageType = "warning";
    } else {
        $successCount = 0;
        $errorMessages = [];

        while ($gradeRow = $gradesResult->fetch_assoc()) {

            $data = [
                "batch_code" => $gradeRow['batch_code'],
                "egg_size" => $gradeRow['grade'],
                "quantity" => intval($gradeRow['quantity']),
                "received_date" => $gradeRow['collection_date']
            ];

            $ch = curl_init($apiUrl);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Accept: application/json",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if (!empty($curlError)) {
                $errorMessages[] = "Connection error for {$gradeRow['grade']}: {$curlError}";
                continue;
            }

            $decodedResponse = json_decode($response, true);

            if (
                ($httpCode === 200 || $httpCode === 201) &&
                isset($decodedResponse['status']) &&
                $decodedResponse['status'] === true
            ) {
                $updateStmt = $conn->prepare("
                    UPDATE egg_grades 
                    SET sent_to_inventory = 1 
                    WHERE id = ?
                ");

                $updateStmt->bind_param("i", $gradeRow['id']);
                $updateStmt->execute();

                $successCount++;
            } else {
                $apiMessage = $decodedResponse['message'] ?? $response;

                $errorMessages[] = "API error for {$gradeRow['grade']}: HTTP {$httpCode}. Response: {$apiMessage}";
            }
        }

        if ($successCount > 0 && empty($errorMessages)) {
            $message = "Graded eggs sent to inventory successfully.";
            $messageType = "success";
            $alreadySent = true;
        } elseif ($successCount > 0 && !empty($errorMessages)) {
            $message = "Some graded eggs were sent, but some failed: " . implode(" | ", $errorMessages);
            $messageType = "warning";
        } else {
            $message = "Failed to send graded eggs to inventory. " . implode(" | ", $errorMessages);
            $messageType = "danger";
        }
    }
}

/* =========================
   GET CURRENT GRADING RECORDS
========================= */
$existingStmt = $conn->prepare("
    SELECT * 
    FROM egg_grades 
    WHERE batch_id = ?
    ORDER BY FIELD(grade, 'Extra Large', 'Large', 'Medium', 'Small')
");
$existingStmt->bind_param("i", $batch_id);
$existingStmt->execute();
$existingGrades = $existingStmt->get_result();

$currentExtraLarge = 0;
$currentLarge = 0;
$currentMedium = 0;
$currentSmall = 0;
$currentTotalGraded = 0;

$gradeRows = [];

while ($row = $existingGrades->fetch_assoc()) {
    $gradeRows[] = $row;

    $quantity = intval($row['quantity']);
    $currentTotalGraded += $quantity;

    if ($row['grade'] === "Extra Large") {
        $currentExtraLarge = $quantity;
    } elseif ($row['grade'] === "Large") {
        $currentLarge = $quantity;
    } elseif ($row['grade'] === "Medium") {
        $currentMedium = $quantity;
    } elseif ($row['grade'] === "Small") {
        $currentSmall = $quantity;
    }
}

$currentNotGraded = max(0, $totalEggsInBatch - $currentTotalGraded);

$unsentStmt = $conn->prepare("
    SELECT COUNT(*) AS total_unsent 
    FROM egg_grades 
    WHERE batch_id = ? 
    AND sent_to_inventory = 0
");
$unsentStmt->bind_param("i", $batch_id);
$unsentStmt->execute();
$totalUnsentRecords = intval($unsentStmt->get_result()->fetch_assoc()['total_unsent']);

$canSendToInventory = $totalUnsentRecords > 0;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Grade Egg Batch</h4>
</div>

<div class="alert alert-secondary">
    <strong>Batch Code:</strong> <?= htmlspecialchars($batch['batch_code']); ?><br>
    <strong>Collection Date:</strong> <?= htmlspecialchars($batch['collection_date']); ?>
</div>

<div class="row mb-4">

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6>Total Eggs in Batch</h6>
                <h2><?= $totalEggsInBatch; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6>Total Graded Eggs</h6>
                <h2><?= $currentTotalGraded; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6>Not Graded Eggs</h6>
                <h2><?= $currentNotGraded; ?></h2>
            </div>
        </div>
    </div>

</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType); ?>">
        <?= htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($alreadySent): ?>
    <div class="alert alert-warning">
        This batch already has grading records sent to inventory. Editing is disabled to prevent inventory mismatch.
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">

        <h5 class="mb-3">Egg Grading Form</h5>

        <form method="POST" action="dashboard.php?page=grade_batch&id=<?= $batch_id; ?>">

            <div class="row">

                <div class="col-md-3">
                    <label>Extra Large</label>
                    <input 
                        type="number" 
                        name="extra_large" 
                        class="form-control egg-input" 
                        min="0" 
                        value="<?= $currentExtraLarge; ?>"
                        <?= $alreadySent ? 'readonly' : ''; ?>
                    >
                </div>

                <div class="col-md-3">
                    <label>Large</label>
                    <input 
                        type="number" 
                        name="large" 
                        class="form-control egg-input" 
                        min="0" 
                        value="<?= $currentLarge; ?>"
                        <?= $alreadySent ? 'readonly' : ''; ?>
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
                        <?= $alreadySent ? 'readonly' : ''; ?>
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
                        <?= $alreadySent ? 'readonly' : ''; ?>
                    >
                </div>

            </div>

            <div class="alert alert-light border mt-3">
                <strong>Total Entered:</strong> 
                <span id="totalEntered"><?= $currentTotalGraded; ?></span> /
                <strong>Total Eggs:</strong> <?= $totalEggsInBatch; ?><br>

                <strong>Not Graded:</strong> 
                <span id="remainingEggs"><?= $currentNotGraded; ?></span>
            </div>

            <button 
                type="submit" 
                name="save_grading" 
                class="btn btn-warning"
                <?= $alreadySent ? 'disabled' : ''; ?>
            >
                Save Grading
            </button>

        </form>

    </div>
</div>

<h5>Current Grading Records</h5>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>Egg Size</th>
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
                            <span class="badge bg-success">Sent</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Not Yet Sent</span>
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
        onclick="return confirm('Send these graded eggs to the inventory system?');"
        <?= !$canSendToInventory ? 'disabled' : ''; ?>
    >
        Send Graded Eggs to Inventory
    </button>

    <?php if (!$canSendToInventory && !empty($gradeRows)): ?>
        <span class="text-muted ms-2">
            All graded eggs from this batch have already been sent.
        </span>
    <?php endif; ?>
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