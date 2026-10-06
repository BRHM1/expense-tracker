<?php
/** @var string $pageTitle  Set by each page before including this file. */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$flash = getFlash();

function navClass(string $page, string $current): string
{
    return $page === $current ? 'nav-link active' : 'nav-link';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Expense Tracker') ?> · Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="index.php">💰 Expense Tracker</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="<?= navClass('index.php', $currentPage) ?>" href="index.php">Dashboard</a></li>
                <li class="nav-item"><a class="<?= navClass('expenses.php', $currentPage) ?>" href="expenses.php">Expenses</a></li>
                <li class="nav-item"><a class="<?= navClass('add.php', $currentPage) ?>" href="add.php">Add Expense</a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
