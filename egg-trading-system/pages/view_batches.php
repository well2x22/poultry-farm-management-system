<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../config/database.php";

    $database = new Database();
    $conn = $database->connect();
}

/** @var mysqli $conn */

$batches = $conn->query("SELECT * FROM egg_batches ORDER BY id DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Egg Batches</h4>

    <a href="dashboard.php?page=add_batch" class="btn btn-warning">
        Add New Batch
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">

        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Batch Code</th>
                    <th>Collection Date</th>
                    <th>Total Eggs</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($batches && $batches->num_rows > 0): ?>
                    <?php while ($row = $batches->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['batch_code']); ?></td>
                            <td><?= htmlspecialchars($row['collection_date']); ?></td>
                            <td><?= htmlspecialchars($row['total_eggs']); ?></td>
                            <td><?= htmlspecialchars($row['remarks']); ?></td>
                            <td>
                                <a href="dashboard.php?page=grade_batch&id=<?= $row['id']; ?>" class="btn btn-sm btn-warning">
                                    Grade
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No egg batches found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>
</div>