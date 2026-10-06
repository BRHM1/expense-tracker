<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Dashboard';
$stats = null;
$recent = [];
$dbError = null;

try {
    $db = getDb();

    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total_count,
                COALESCE(SUM(amount), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN expense_date >= :month_start AND expense_date < :next_month
                                  THEN amount END), 0) AS month_amount
         FROM expenses'
    );
    $stmt->execute([
        ':month_start' => date('Y-m-01'),
        ':next_month'  => date('Y-m-01', strtotime('first day of next month')),
    ]);
    $stats = $stmt->fetch();

    $stmt = $db->prepare('SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT :limit');
    $stmt->bindValue(':limit', 5, PDO::PARAM_INT);
    $stmt->execute();
    $recent = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log('Dashboard query failed: ' . $ex->getMessage());
    $dbError = 'Could not load data from the database. Check the database configuration.';
}

require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4">Dashboard</h1>

<?php if ($dbError): ?>
    <div class="alert alert-danger"><?= e($dbError) ?></div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small">Total expenses</div>
                    <div class="stat-value"><?= e($stats['total_count']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card border-success h-100">
                <div class="card-body">
                    <div class="text-muted small">Total amount spent</div>
                    <div class="stat-value"><?= e(formatMoney($stats['total_amount'])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card border-warning h-100">
                <div class="card-body">
                    <div class="text-muted small">Spent in <?= e(date('F Y')) ?></div>
                    <div class="stat-value"><?= e(formatMoney($stats['month_amount'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Recent expenses</span>
            <a href="add.php" class="btn btn-sm btn-primary">+ Add Expense</a>
        </div>
        <?php if (!$recent): ?>
            <div class="card-body text-muted">No expenses yet. <a href="add.php">Add your first one.</a></div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($recent as $row): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>
                            <?= e($row['description']) ?>
                            <span class="badge text-bg-light ms-1"><?= e($row['category']) ?></span>
                            <small class="text-muted ms-1"><?= e($row['expense_date']) ?></small>
                        </span>
                        <span class="fw-semibold"><?= e(formatMoney($row['amount'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer text-end"><a href="expenses.php">View all expenses &rarr;</a></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
