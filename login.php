<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_admin()) {
    redirect('admin/products.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Enter your email and password.';
        } else {
            try {
                if (attempt_login($email, $password)) {
                    redirect('admin/products.php');
                }
                $error = 'Invalid email or password.';
            } catch (Throwable $e) {
                $error = 'Unable to sign in: database is not ready. Open install.php or check DB settings.';
            }
        }
    }
}

$pageTitle = 'Admin Login';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="login-wrap">
        <div class="surface-card p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="brand-mark mx-auto mb-3" style="width:3rem;height:3rem;border-radius:1rem;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#147a58,#0b3d2e);color:#fff;">
                    <i class="fa-solid fa-user-lock"></i>
                </div>
                <h1 class="h3 display-font mb-1">Admin Login</h1>
                <p class="text-muted mb-0">Manage products and AI settings</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="alert alert-success border-0 mb-4">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-key me-1"></i>Demo admin account</div>
                <div class="small mb-0">
                    <div><span class="text-muted">User / Email:</span> <code>admin@shop.local</code></div>
                    <div><span class="text-muted">Password:</span> <code>Admin@12345</code></div>
                </div>
            </div>

            <form method="post" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control form-control-lg" type="email" name="email" id="email" required
                           value="<?= e($_POST['email'] ?? 'admin@shop.local') ?>" placeholder="admin@shop.local">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control form-control-lg" type="password" name="password" id="password" required
                           value="<?= e($_POST['password'] ?? 'Admin@12345') ?>" placeholder="Admin@12345">
                </div>
                <button class="btn btn-brand w-100 btn-lg" type="submit">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Sign in
                </button>
            </form>
        </div>
        <p class="text-center text-muted small mt-3 mb-0">
            Customers do not need an account — use the <a href="<?= e(url('scan.php')) ?>">scanner</a>.
        </p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
