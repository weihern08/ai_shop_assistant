<?php
/**
 * One-time installer for AI Smart Shopping Assistant.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

start_app_session();

/**
 * Execute a SQL file that may contain multiple statements.
 */
function run_sql_batch(PDO $pdo, string $sql): void
{
    // Remove line comments
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $pdo->exec($part);
    }
}

$alreadyInstalled = is_file(INSTALL_LOCK);
$messages = [];
$errors = [];
$done = false;

// cPanel SQL import path: DB already seeded but install.lock missing
if (!$alreadyInstalled) {
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $check = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $check->query('SELECT 1 FROM users LIMIT 1');
        @file_put_contents(INSTALL_LOCK, date('c') . "\n");
        $alreadyInstalled = is_file(INSTALL_LOCK);
        if ($alreadyInstalled) {
            $messages[] = 'Detected existing database — install lock created.';
        }
    } catch (Throwable $e) {
        // Not installed yet
    }
}

$defaultAdminEmail = 'admin@shop.local';
$defaultAdminPassword = 'Admin@12345';

$form = [
    'db_host' => DB_HOST,
    'db_name' => DB_NAME,
    'db_user' => DB_USER,
    'db_pass' => DB_PASS,
    'admin_email' => $defaultAdminEmail,
    'admin_password' => $defaultAdminPassword,
    'seed_samples' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $form['db_host'] = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $form['db_name'] = trim((string) ($_POST['db_name'] ?? 'ai_shop_assistant'));
        $form['db_user'] = trim((string) ($_POST['db_user'] ?? 'root'));
        $form['db_pass'] = (string) ($_POST['db_pass'] ?? '');
        $form['admin_email'] = strtolower(trim((string) ($_POST['admin_email'] ?? $defaultAdminEmail)));
        $form['admin_password'] = (string) ($_POST['admin_password'] ?? $defaultAdminPassword);
        $form['seed_samples'] = isset($_POST['seed_samples']) ? '1' : '0';

        if ($form['db_host'] === '' || $form['db_name'] === '' || $form['db_user'] === '') {
            $errors[] = 'Database host, name, and user are required.';
        }
        if (!filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid admin email.';
        }
        if (strlen($form['admin_password']) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }

        if (!$errors) {
            try {
                $server = db_connect_server($form['db_host'], $form['db_user'], $form['db_pass']);
                $dbName = str_replace('`', '``', $form['db_name']);
                $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $server->exec("USE `{$dbName}`");
                $messages[] = 'Database: Ready';

                $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
                if ($schema === false) {
                    throw new RuntimeException('Unable to read schema.sql');
                }
                run_sql_batch($server, $schema);
                $messages[] = 'Tables: Ready';

                // Default settings
                $defaults = [
                    'ai_provider' => 'agnes',
                    'agnes_api_url' => 'https://api.agnes-ai.com/v1',
                    'agnes_api_key' => '',
                    'agnes_model' => 'agnes-2.5-flash',
                    'agnes_fallback_models' => '',
                    'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta',
                    'gemini_api_key' => '',
                    'gemini_model' => 'gemini-3.8-flash',
                    'gemini_fallback_models' => 'gemini-3.7-flash,gemini-3.6-flash,gemini-2.5-flash',
                ];
                $stmt = $server->prepare(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                foreach ($defaults as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                $messages[] = 'Default settings: Created';

                $hash = password_hash($form['admin_password'], PASSWORD_DEFAULT);
                $stmt = $server->prepare(
                    'INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)'
                );
                $stmt->execute([$form['admin_email'], $hash, 'admin']);
                $messages[] = 'Default Admin: Created';

                if ($form['seed_samples'] === '1') {
                    $count = (int) $server->query('SELECT COUNT(*) FROM products')->fetchColumn();
                    if ($count === 0) {
                        $samples = [
                            ['SKU-001', '8857200535366', 'Thai Herbal Inhaler', 'herbal inhaler, thai inhaler, peppermint inhaler', 5.00, 'Refreshing traditional Thai herbal inhaler with menthol and essential oils.'],
                            ['SKU-002', '8850123456789', 'Green Tea Bottle 500ml', 'green tea, iced tea, tea bottle, beverage', 3.50, 'Chilled green tea beverage with a light, refreshing taste.'],
                            ['SKU-003', '8901234567890', 'Crispy Potato Chips', 'potato chips, crisps, snack, salty chips', 4.20, 'Classic salted potato chips with a light crunch.'],
                            ['SKU-004', '5012345678900', 'Mineral Water 600ml', 'mineral water, water bottle, drinking water', 1.50, 'Pure mineral water in a convenient 600ml bottle.'],
                            ['SKU-005', '4006381333931', 'Chocolate Wafer Bar', 'chocolate, wafer, candy bar, sweet snack', 2.80, 'Crispy wafer layers covered in smooth milk chocolate.'],
                            ['SKU-006', null, 'Organic Hand Sanitizer', 'hand sanitizer, sanitizer gel, alcohol gel, hygiene', 8.90, 'Alcohol-based hand sanitizer with aloe vera.'],
                        ];
                        $ins = $server->prepare(
                            'INSERT INTO products (sku, barcode, name, detect_keywords, price, description) VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        foreach ($samples as $row) {
                            $ins->execute($row);
                        }
                    }
                    $messages[] = 'Sample Products: Created';
                }

                // Ensure upload directories
                foreach ([UPLOAD_PATH, CACHE_PATH] as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                }

                // Update DB credentials in config.local.php without wiping APP_*/API keys
                $configPath = __DIR__ . '/includes/config.local.php';
                $existing = is_file($configPath) ? (string) file_get_contents($configPath) : '';
                $preserveKeys = [
                    'APP_PUBLIC_HOST', 'APP_FORCE_HTTPS', 'APP_BASE_PATH', 'APP_PUBLIC_URL',
                    'AI_PROVIDER', 'GEMINI_API_KEY', 'AGNES_API_KEY',
                ];
                $preserved = [];
                foreach ($preserveKeys as $key) {
                    if (preg_match('/putenv\(\s*[\'"]' . preg_quote($key, '/') . '=[^\'"]*[\'"]\s*\);/', $existing, $m)) {
                        $preserved[] = $m[0];
                    }
                }

                $configLocal = "<?php\n"
                    . "declare(strict_types=1);\n"
                    . "// Auto-generated/updated by install.php — do not commit secrets.\n"
                    . "putenv(" . var_export('DB_HOST=' . $form['db_host'], true) . ");\n"
                    . "putenv(" . var_export('DB_NAME=' . $form['db_name'], true) . ");\n"
                    . "putenv(" . var_export('DB_USER=' . $form['db_user'], true) . ");\n"
                    . "putenv(" . var_export('DB_PASS=' . $form['db_pass'], true) . ");\n";
                if ($preserved) {
                    $configLocal .= "\n// Preserved local overrides\n" . implode("\n", $preserved) . "\n";
                }
                file_put_contents($configPath, $configLocal);

                file_put_contents(INSTALL_LOCK, date('c') . "\ninstalled_by=" . $form['admin_email'] . "\n");
                $messages[] = 'Install lock: Created';
                $done = true;
                $alreadyInstalled = true;
            } catch (Throwable $e) {
                error_log('Install failed: ' . $e->getMessage());
                $errors[] = 'Installation failed. Check database credentials and MySQL permissions.';
            }
        }
    }
}

// Load local config after install for app_base_url helpers
if (is_file(__DIR__ . '/includes/config.local.php')) {
    require_once __DIR__ . '/includes/config.local.php';
}

$pageTitle = 'Installation';
$bodyClass = '';
$hideNav = false;

// Minimal header without full init (DB may not exist yet)
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/fontawesome.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<main class="app-main">
<div class="container py-4 py-md-5" style="max-width:760px">
    <div class="text-center mb-4">
        <div class="brand-mark mx-auto mb-3" style="width:3rem;height:3rem;border-radius:1rem;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#147a58,#0b3d2e);color:#fff;">
            <i class="fa-solid fa-screwdriver-wrench"></i>
        </div>
        <h1 class="display-font h2"><?= e(APP_NAME) ?></h1>
        <p class="text-muted">Installation wizard</p>
    </div>

    <?php if ($alreadyInstalled && $done): ?>
        <div class="surface-card p-4 p-md-5">
            <div class="alert alert-success">
                <h2 class="h4 display-font">Installation Complete</h2>
                <ul class="mb-0">
                    <?php foreach ($messages as $msg): ?>
                        <li><?= e($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="alert alert-warning">
                <strong>Change the default admin password immediately</strong> after your first login.
                <div class="mt-2 small">
                    Email: <code><?= e($form['admin_email']) ?></code><br>
                    Password: <code><?= e($form['admin_password']) ?></code>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-brand" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-house me-1"></i>Open Application</a>
                <a class="btn btn-accent" href="<?= e(url('login.php')) ?>"><i class="fa-solid fa-user-lock me-1"></i>Admin Login</a>
            </div>
        </div>
    <?php elseif ($alreadyInstalled): ?>
        <div class="surface-card p-4 p-md-5 text-center">
            <i class="fa-solid fa-circle-check fa-2x text-success mb-3"></i>
            <h2 class="h4 display-font">Already Installed</h2>
            <p class="text-muted">Remove <code>includes/install.lock</code> only if you intentionally want to reinstall.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-brand" href="<?= e(url('index.php')) ?>">Open Application</a>
                <a class="btn btn-soft" href="<?= e(url('login.php')) ?>">Admin Login</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="surface-card p-4 p-md-5">
            <?= csrf_field() ?>
            <h2 class="h5 display-font mb-3">Database</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="db_host">Host</label>
                    <input class="form-control" id="db_host" name="db_host" required value="<?= e($form['db_host']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="db_name">Database name</label>
                    <input class="form-control" id="db_name" name="db_name" required value="<?= e($form['db_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="db_user">Username</label>
                    <input class="form-control" id="db_user" name="db_user" required value="<?= e($form['db_user']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="db_pass">Password</label>
                    <input class="form-control" id="db_pass" name="db_pass" value="<?= e($form['db_pass']) ?>" autocomplete="off">
                </div>
            </div>

            <h2 class="h5 display-font mb-3">Administrator</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="admin_email">Email</label>
                    <input class="form-control" type="email" id="admin_email" name="admin_email" required value="<?= e($form['admin_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="admin_password">Password</label>
                    <input class="form-control" type="text" id="admin_password" name="admin_password" required minlength="8" value="<?= e($form['admin_password']) ?>">
                </div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="seed_samples" name="seed_samples" value="1" <?= $form['seed_samples'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="seed_samples">Insert sample products for testing</label>
            </div>

            <div class="alert alert-warning small">
                After installation, update <code>includes/config.php</code> (or generated <code>includes/config.local.php</code>)
                if your hosting credentials change. Change the admin password immediately.
            </div>

            <button class="btn btn-brand btn-lg w-100" type="submit">
                <i class="fa-solid fa-rocket me-2"></i>Install Application
            </button>
        </form>
    <?php endif; ?>
</div>
</main>
<script src="<?= e(asset('assets/js/vendor/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
