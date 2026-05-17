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

/* =========================
   SAVE GRADING
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_grading'])) {
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
                    (batch_id, grade, quantity, sent_to_inventory) 
                    VALUES (?, ?, ?, 0)
                ");

                $insertStmt->bind_param("isi", $batch_id, $grade, $quantity);
                $insertStmt->execute();
            }
        }

        $message = "Egg grading saved successfully.";
    }
}

/* =========================
   SEND TO INVENTORY API
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['send_inventory'])) {
    $apiUrl = "http://127.0.0.1:8001/api/egg-inventory";

    $gradeStmt = $conn->prepare("
        SELECT egg_grades.*, egg_batches.batch_code
        FROM egg_grades
        INNER JOIN egg_batches ON egg_grades.batch_id = egg_batches.id
        WHERE egg_grades.batch_id = ?
        AND egg_grades.sent_to_inventory = 0
    ");

    $gradeStmt->bind_param("i", $batch_id);
    $gradeStmt->execute();

    $gradesResult = $gradeStmt->get_result();

    if ($gradesResult->num_rows === 0) {
        $message = "No unsent grading records found. This batch may already be sent to inventory.";
    } else {
        $successCount = 0;
        $errorMessages = [];

        while ($gradeRow = $gradesResult->fetch_assoc()) {
            $data = [
                "batch_code" => $gradeRow['batch_code'],
                "egg_size" => $gradeRow['grade'],
                "quantity" => intval($gradeRow['quantity']),
                "received_date" => date("Y-m-d")
            ];

            $ch = curl_init($apiUrl);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Accept: application/json",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                $errorMessages[] = "CURL Error for {$gradeRow['grade']}: " . $curlError;
                continue;
            }

            if ($httpCode === 200 || $httpCode === 201) {
                $updateStmt = $conn->prepare("
                    UPDATE egg_grades 
                    SET sent_to_inventory = 1 
                    WHERE id = ?
                ");

                $updateStmt->bind_param("i", $gradeRow['id']);
                $updateStmt->execute();

                $successCount++;
            } else {
                $errorMessages[] = "API Error for {$gradeRow['grade']}: HTTP $httpCode - $response";
            }
        }

        if ($successCount > 0 && empty($errorMessages)) {
            $message = "Graded eggs sent to inventory successfully.";
        } elseif ($successCount > 0 && !empty($errorMessages)) {
            $message = "Some records were sent, but some failed: " . implode(" | ", $errorMessages);
        } else {
            $message = "Failed to send records to inventory: " . implode(" | ", $errorMessages);
        }
    }
}

$existingStmt = $conn->prepare("SELECT * FROM egg_grades WHERE batch_id = ?");
$existingStmt->bind_param("i", $batch_id);
$existingStmt->execute();
$existingGrades = $existingStmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Grade Egg Batch</h4>
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

            <button type="submit" name="save_grading" class="btn btn-warning mt-3">
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
                    <td>
                        <?php if ($row['sent_to_inventory']): ?>
                            <span class="badge bg-success">Yes</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
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
    >
        Send to Inventory API
    </button>
</form>