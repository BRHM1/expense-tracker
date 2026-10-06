<?php
/**
 * GET  delete.php?id=N  -> shows a confirmation page
 * POST delete.php?id=N  -> deletes the expense (requires CSRF token)
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Delete Expense';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    setFlash('danger', 'Invalid expense ID.');
    redirect('expenses.php');
}

try {
    $db = getDb();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf()) {
            setFlash('danger', 'Your session expired. Please try again.');
            redirect('expenses.php');
        }

        $stmt = $db->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Expense deleted successfully.');
        } else {
            setFlash('warning', 'Expense not found or already deleted.');
        }
        redirect('expenses.php');
    }

    $stmt = $db->prepare('SELECT * FROM expenses WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $expense = $stmt->fetch();
} catch (PDOException $ex) {
    error_log('Delete failed: ' . $ex->getMessage());
    setFlash('danger', 'Could not delete the expense. Please try again.');
    redirect('expenses.php');
}

if (!$expense) {
    setFlash('warning', 'Expense not found.');
    redirect('expenses.php');
}

require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white fw-semibold">Confirm deletion</div>
            <div class="card-body">
                <p>Are you sure you want to delete this expense? This cannot be undone.</p>
                <dl class="row mb-4">
                    <dt class="col-sm-4">Description</dt><dd class="col-sm-8"><?= e($expense['description']) ?></dd>
                    <dt class="col-sm-4">Amount</dt><dd class="col-sm-8"><?= e(formatMoney($expense['amount'])) ?></dd>
                    <dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?= e($expense['category']) ?></dd>
                    <dt class="col-sm-4">Date</dt><dd class="col-sm-8"><?= e($expense['expense_date']) ?></dd>
                </dl>
                <form method="post" action="delete.php?id=<?= e($id) ?>" class="d-flex gap-2">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-danger">Yes, delete it</button>
                    <a href="expenses.php" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
