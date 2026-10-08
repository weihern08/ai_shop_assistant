<?php
/**
 * PDO database connection.
 * Prefers MySQL; falls back to local SQLite when MySQL is unavailable.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

define('SQLITE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app.sqlite');

/**
 * Whether the active connection is SQLite.
 */
function db_is_sqlite(): bool
{
    db();
    return ($GLOBALS['_db_driver'] ?? '') === 'sqlite';
}

/**
 * Get a shared PDO instance.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $preferSqlite = strtolower((string) (getenv('DB_DRIVER') ?: '')) === 'sqlite';

    // 1) Try MySQL unless explicitly forced to SQLite
    if (!$preferSqlite) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            // Fail fast when MySQL is down / wrong password (avoids multi-second hangs)
            $mysqlOptions = $options + [
                PDO::ATTR_TIMEOUT => 2,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $mysqlOptions);
            $GLOBALS['_db_driver'] = 'mysql';
            return $pdo;
        } catch (Throwable $e) {
            error_log('MySQL unavailable, trying SQLite: ' . $e->getMessage());
        }
    }

    // 2) SQLite (local demo / DB_DRIVER=sqlite / MySQL unavailable)
    $dir = dirname(SQLITE_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $isNew = !is_file(SQLITE_PATH);
    $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, $options);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $GLOBALS['_db_driver'] = 'sqlite';

    ensure_sqlite_schema($pdo);

    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($isNew || $userCount === 0) {
        seed_sqlite_defaults($pdo);
    } else {
        // Keep local demo admin password usable if still the default account
        seed_sqlite_defaults($pdo);
    }

    return $pdo;
}

/**
 * Create SQLite tables if missing.
 */
function ensure_sqlite_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT \'admin\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sku TEXT NOT NULL UNIQUE,
            barcode TEXT NULL,
            name TEXT NOT NULL,
            detect_keywords TEXT DEFAULT NULL,
            price REAL NOT NULL DEFAULT 0,
            description TEXT NULL,
            image_path TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NULL
        )'
    );
}

/**
 * Seed admin + sample products + AI settings for SQLite.
 */
function seed_sqlite_defaults(PDO $pdo): void
{
    $hash = password_hash('Admin@12345', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute(['admin@shop.local', $hash, 'admin']);

    // Always ensure known admin password for local demo
    $upd = $pdo->prepare('UPDATE users SET password_hash = ?, role = ? WHERE email = ?');
    $upd->execute([$hash, 'admin', 'admin@shop.local']);

    $defaults = [
        'ai_provider' => getenv('AI_PROVIDER') ?: 'agnes',
        'agnes_api_url' => getenv('AGNES_API_URL') ?: 'https://apihub.agnes-ai.com/v1',
        'agnes_api_key' => getenv('AGNES_API_KEY') ?: '',
        'agnes_model' => getenv('AGNES_MODEL') ?: 'agnes-3.0-flash',
        'agnes_fallback_models' => '',
        'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
        'gemini_model' => getenv('GEMINI_MODEL') ?: 'gemini-3.6-flash',
        'gemini_fallback_models' => getenv('GEMINI_FALLBACK_MODELS') ?: 'gemini-3.7-flash,gemini-3.8-flash',
    ];
    $set = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
    );
    foreach ($defaults as $k => $v) {
        $set->execute([$k, $v]);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($count === 0) {
        $ins = $pdo->prepare(
            'INSERT INTO products (sku, barcode, name, detect_keywords, price, description, image_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $samples = [
            ['SKU-001', '8857200535366', 'Thai Herbal Inhaler', 'herbal inhaler, thai inhaler, peppermint inhaler', 5.00, 'Refreshing traditional Thai herbal inhaler with menthol and essential oils.', 'uploads/products/p001-inhaler.jpg'],
            ['SKU-002', '8850123456789', 'Green Tea Bottle 500ml', 'green tea, iced tea, tea bottle, beverage', 3.50, 'Chilled green tea beverage with a light, refreshing taste.', 'uploads/products/p002-greentea.jpg'],
            ['SKU-003', '8901234567890', 'Crispy Potato Chips', 'potato chips, crisps, snack, salty chips', 4.20, 'Classic salted potato chips with a light crunch.', 'uploads/products/p003-chips.jpg'],
            ['SKU-004', '5012345678900', 'Mineral Water 600ml', 'mineral water, water bottle, drinking water', 1.50, 'Pure mineral water in a convenient 600ml bottle.', 'uploads/products/p004-water.jpg'],
            ['SKU-005', '4006381333931', 'Chocolate Wafer Bar', 'chocolate, wafer, candy bar, sweet snack', 2.80, 'Crispy wafer layers covered in smooth milk chocolate.', 'uploads/products/p005-chocolate.jpg'],
            ['SKU-006', null, 'Organic Hand Sanitizer', 'hand sanitizer, sanitizer gel, alcohol gel, hygiene', 8.90, 'Alcohol-based hand sanitizer with aloe vera.', 'uploads/products/p006-sanitizer.jpg'],
        ];
        foreach ($samples as $row) {
            $ins->execute($row);
        }
    }
}

/**
 * Test database connection without selecting a database (for installer).
 */
function db_connect_server(?string $host = null, ?string $user = null, ?string $pass = null): PDO
{
    $host = $host ?? DB_HOST;
    $user = $user ?? DB_USER;
    $pass = $pass ?? DB_PASS;

    $dsn = sprintf('mysql:host=%s;charset=%s', $host, DB_CHARSET);

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
