<?php
/**
 * Small shared helpers: escaping, flash messages, CSRF and validation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

const CATEGORIES = ['Food', 'Transportation', 'Shopping', 'Bills', 'Other'];

/** Escape output for HTML. */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMoney(float|string $amount): string
{
    return '$' . number_format((float) $amount, 2);
}

/** Store a one-time message shown on the next page load. */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): bool
{
    return isset($_POST['csrf_token']) && hash_equals(csrfToken(), (string) $_POST['csrf_token']);
}

/**
 * Validate submitted expense data.
 * Returns [cleanData, errors] where errors is keyed by field name.
 */
function validateExpense(array $input): array
{
    $data = [
        'description'  => trim((string) ($input['description'] ?? '')),
        'amount'       => trim((string) ($input['amount'] ?? '')),
        'category'     => (string) ($input['category'] ?? ''),
        'expense_date' => (string) ($input['expense_date'] ?? ''),
    ];
    $errors = [];

    if ($data['description'] === '') {
        $errors['description'] = 'Description is required.';
    } elseif (mb_strlen($data['description']) > 255) {
        $errors['description'] = 'Description must be 255 characters or fewer.';
    }

    if ($data['amount'] === '' || !is_numeric($data['amount'])) {
        $errors['amount'] = 'Amount must be a number.';
    } elseif ((float) $data['amount'] <= 0) {
        $errors['amount'] = 'Amount must be greater than zero.';
    } elseif ((float) $data['amount'] > 99999999.99) {
        $errors['amount'] = 'Amount is too large.';
    } else {
        $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
    }

    if (!in_array($data['category'], CATEGORIES, true)) {
        $errors['category'] = 'Please choose a valid category.';
    }

    $date = DateTime::createFromFormat('!Y-m-d', $data['expense_date']);
    if (!$date || $date->format('Y-m-d') !== $data['expense_date']) {
        $errors['expense_date'] = 'Please enter a valid date.';
    }

    return [$data, $errors];
}
