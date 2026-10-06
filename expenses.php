<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Expenses';
$expenses = [];
$dbError = null;

try {
    $stmt = getDb()->prepare('SELECT * FROM expenses ORDER BY expense_date DESC, id DESC');
    $stmt->execute();
    $expenses = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log('Expense list query failed: ' . $ex->getMessage());
    $dbError = 'Could not load expenses from the database.';
}

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Expenses</h1>
    <a href="add.php" class="btn btn-primary">+ Add Expense</a>
</div>

<?php if ($dbError): ?>
    <div class="alert alert-danger"><?= e($dbError) ?></div>
<?php elseif (!$expenses): ?>
    <div class="alert alert-info">No expenses recorded yet. <a href="add.php">Add one now.</a></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($expenses as $row): ?>
                    <tr>
                        <td><?= e($row['description']) ?></td>
                        <td class="text-end"><?= e(formatMoney($row['amount'])) ?></td>
                        <td><span class="badge category-<?= e(strtolower($row['category'])) ?>"><?= e($row['category']) ?></span></td>
                        <td><?= e($row['expense_date']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="edit.php?id=<?= e($row['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <a href="delete.php?id=<?= e($row['id']) ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
