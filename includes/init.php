<?php
/**
 * Application bootstrap.
 */

declare(strict_types=1);

// Safe defaults — never show stack traces to users
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/ai.php';

start_app_session();

// Keep AI keys/provider in sync with config.local.php (cPanel uploads)
try {
    if (function_exists('ensure_default_settings')) {
        ensure_default_settings();
    }
} catch (Throwable $e) {
    // DB may not be ready yet (installer)
}

/**
 * Redirect helper (absolute URL avoids blank pages behind HTTPS proxy).
 */
function redirect(string $path): void
{
    $path = ltrim($path, '/');
    // Prefer current absolute base so Location works on phone + proxy
    $target = rtrim(app_base_url(), '/') . '/' . $path;
    header('Location: ' . $target);
    exit;
}

/**
 * Check whether the app appears installed.
 */
function is_installed(): bool
{
    if (is_file(INSTALL_LOCK)) {
        return true;
    }

    try {
        $pdo = db();
        $pdo->query('SELECT 1 FROM users LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Force install redirect when not installed (except install.php itself).
 */
function require_installed(): void
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'install.php') {
        return;
    }

    if (!is_installed()) {
        redirect('install.php');
    }
}

// Auto-redirect to installer when DB is not ready (skip for install.php)
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptName !== 'install.php' && !is_file(INSTALL_LOCK)) {
    try {
        db()->query('SELECT 1 FROM products LIMIT 1');
    } catch (Throwable $e) {
        // Only redirect HTML pages; APIs get JSON
        if (str_contains(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/api')) {
            json_response(['success' => false, 'message' => 'Application is not installed.'], 503);
        }
        header('Location: ' . url('install.php'));
        exit;
    }
}
