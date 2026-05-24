<?php
require_once __DIR__ . "/../includes/api_client.php";

$message = "";
$messageType = "info";

$batch_id = intval($_GET['id'] ?? 0);

if ($batch_id <= 0) {
    echo "<div class='alert alert-danger'>No batch selected.</div>";
    return;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_grading'])) {
    $response = postToApi("save_grading.php", [
        "action" => "save",
        "batch_id" => $batch_id,
        "extra_large" => intval($_POST['extra_large'] ?? 0),
        "large" => intval($_POST['large'] ?? 0),
        "medium" => intval($_POST['medium'] ?? 0),
        "small" => intval($_POST['small'] ?? 0)
    ]);

    $message = $response["message"] ?? "Unknown API response.";
    $messageType = ($response["status"] ?? false) ? "success" : "danger";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['send_inventory'])) {
    $response = postToApi("save_grading.php", [
        "action" => "send_inventory",
        "batch_id" => $batch_id
    ]);

    $message = $response["message"] ?? "Unknown API response.";
    $messageType = ($response["status"] ?? false) ? "success" : "danger";
}

$response = getFromApi("save_grading.php", [
    "batch_id" => $batch_id
]);

if (!($response["status"] ?? false)) {
    echo "<div class='alert alert-danger'>" . htmlspecialchars($response["message"]) . "</div>";
    return;
}

$data = $response["data"];

$batch = $data["batch"];
$grades = $data["grades"];
$current = $data["current"];

$totalEggs = intval($data["total_eggs"]);
$totalGraded = intval($data["total_graded"]);
$notGraded = intval($data["not_graded"]);
$alreadySent = $data["already_sent"];
$canSend = $data["can_send"];

$currentExtraLarge = intval($current["Extra Large"] ?? 0);
$currentLarge = intval($current["Large"] ?? 0);
$currentMedium = intval($current["Medium"] ?? 0);
$currentSmall = intval($current["Small"] ?? 0);
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
                <h2><?= $totalEggs; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6>Total Graded Eggs</h6>
                <h2><?= $totalGraded; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6>Not Graded Eggs</h6>
                <h2><?= $notGraded; ?></h2>
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
        This batch already has records sent to inventory. Editing is disabled.
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
                <span id="totalEntered"><?= $totalGraded; ?></span> /
                <strong>Total Eggs:</strong> <?= $totalEggs; ?><br>

                <strong>Not Graded:</strong> 
                <span id="remainingEggs"><?= $notGraded; ?></span>
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
        <?php if (!empty($grades)): ?>
            <?php foreach ($grades as $row): ?>
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
        onclick="return confirm('Send these graded eggs to the inventory?');"
        <?= !$canSend ? 'disabled' : ''; ?>
    >
        Send Graded Eggs to Inventory
    </button>
</form>

<script>
const totalEggs = <?= $totalEggs; ?>;
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