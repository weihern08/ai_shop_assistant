<?php
/**
 * Shared HTML header / navigation.
 *
 * @var string $pageTitle
 * @var string|null $bodyClass
 * @var bool|null $hideNav
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$hideNav = $hideNav ?? false;
$user = current_user();
$isAdmin = is_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b3d2e">
    <meta name="description" content="Scan products with your phone camera to check price and information instantly.">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/fontawesome.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <!-- Optional fonts when online -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if (!$hideNav): ?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark"><i class="fa-solid fa-bag-shopping"></i></span>
            <span class="brand-text">AI Smart Shopping</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-house me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('products.php')) ?>"><i class="fa-solid fa-store me-1"></i>Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-cta" href="<?= e(url('scan.php')) ?>"><i class="fa-solid fa-camera me-1"></i>Scanner</a>
                </li>
                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('admin/products.php')) ?>"><i class="fa-solid fa-boxes-stacked me-1"></i>Manage</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('admin/settings.php')) ?>"><i class="fa-solid fa-robot me-1"></i>AI Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('login.php')) ?>"><i class="fa-solid fa-user-lock me-1"></i>Admin Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="app-main">
<?php
$flash = flash_get();
if ($flash):
    $alertClass = ($flash['type'] ?? '') === 'success' ? 'alert-success' : (($flash['type'] ?? '') === 'warning' ? 'alert-warning' : 'alert-danger');
?>
<div class="container mt-3">
    <div class="alert <?= e($alertClass) ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= e($flash['message'] ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>
