<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Edit Expense';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    setFlash('danger', 'Invalid expense ID.');
    redirect('expenses.php');
}

try {
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM expenses WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $expense = $stmt->fetch();
} catch (PDOException $ex) {
    error_log('Load expense failed: ' . $ex->getMessage());
    setFlash('danger', 'Could not load the expense.');
    redirect('expenses.php');
}

if (!$expense) {
    setFlash('warning', 'Expense not found.');
    redirect('expenses.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Your session expired. Please try again.');
        redirect('edit.php?id=' . $id);
    }

    [$expense, $errors] = validateExpense($_POST);

    if (!$errors) {
        try {
            $stmt = $db->prepare(
                'UPDATE expenses
                 SET description = :description, amount = :amount,
                     category = :category, expense_date = :expense_date
                 WHERE id = :id'
            );
            $stmt->execute($expense + ['id' => $id]);

            setFlash('success', 'Expense updated successfully.');
            redirect('expenses.php');
        } catch (PDOException $ex) {
            error_log('Update failed: ' . $ex->getMessage());
            $errors['general'] = 'Could not update the expense. Please try again.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h3 mb-4">Edit Expense</h1>
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php elseif ($errors): ?>
            <div class="alert alert-danger">Please fix the errors below.</div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <?php $submitLabel = 'Save Changes'; require __DIR__ . '/includes/expense_form.php'; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
