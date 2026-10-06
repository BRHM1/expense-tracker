<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Add Expense';
$expense = ['description' => '', 'amount' => '', 'category' => '', 'expense_date' => date('Y-m-d')];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Your session expired. Please try again.');
        redirect('add.php');
    }

    [$expense, $errors] = validateExpense($_POST);

    if (!$errors) {
        try {
            $stmt = getDb()->prepare(
                'INSERT INTO expenses (description, amount, category, expense_date)
                 VALUES (:description, :amount, :category, :expense_date)'
            );
            $stmt->execute($expense);

            setFlash('success', 'Expense added successfully.');
            redirect('expenses.php');
        } catch (PDOException $ex) {
            error_log('Insert failed: ' . $ex->getMessage());
            $errors['general'] = 'Could not save the expense. Please try again.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h3 mb-4">Add Expense</h1>
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php elseif ($errors): ?>
            <div class="alert alert-danger">Please fix the errors below.</div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <?php $submitLabel = 'Add Expense'; require __DIR__ . '/includes/expense_form.php'; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
