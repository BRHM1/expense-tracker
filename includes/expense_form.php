<?php
/**
 * Shared Add/Edit form.
 *
 * @var array  $expense     Current field values.
 * @var array  $errors      Validation errors keyed by field.
 * @var string $submitLabel Button text.
 */
function fieldClass(string $base, string $field, array $errors): string
{
    return isset($errors[$field]) ? "$base is-invalid" : $base;
}
?>
<form method="post" class="needs-validation" novalidate>
    <?= csrfField() ?>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <input type="text" id="description" name="description" maxlength="255" required
               class="<?= fieldClass('form-control', 'description', $errors) ?>"
               value="<?= e($expense['description']) ?>">
        <div class="invalid-feedback"><?= e($errors['description'] ?? 'Description is required.') ?></div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="amount" class="form-label">Amount</label>
            <div class="input-group has-validation">
                <span class="input-group-text">$</span>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="99999999.99" required
                       class="<?= fieldClass('form-control', 'amount', $errors) ?>"
                       value="<?= e($expense['amount']) ?>">
                <div class="invalid-feedback"><?= e($errors['amount'] ?? 'Enter an amount greater than zero.') ?></div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <label for="category" class="form-label">Category</label>
            <select id="category" name="category" required
                    class="<?= fieldClass('form-select', 'category', $errors) ?>">
                <option value="">Choose…</option>
                <?php foreach (CATEGORIES as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $expense['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= e($errors['category'] ?? 'Please choose a category.') ?></div>
        </div>

        <div class="col-md-4 mb-3">
            <label for="expense_date" class="form-label">Date</label>
            <input type="date" id="expense_date" name="expense_date" required
                   class="<?= fieldClass('form-control', 'expense_date', $errors) ?>"
                   value="<?= e($expense['expense_date']) ?>">
            <div class="invalid-feedback"><?= e($errors['expense_date'] ?? 'Please enter a date.') ?></div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
        <a href="expenses.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
