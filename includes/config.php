<?php
/**
 * Application configuration.
 * Adjust DB credentials for your XAMPP / cPanel environment.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
}

// Optional local overrides written by install.php (putenv before constants)
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

define('APP_NAME', 'AI Smart Shopping Assistant');
define('APP_VERSION', '1.0.0');

// Database — change these for your environment (or use config.local.php)
define('DB_HOST', getenv('DB_HOST') !== false && getenv('DB_HOST') !== '' ? getenv('DB_HOST') : 'localhost');
define('DB_NAME', getenv('DB_NAME') !== false && getenv('DB_NAME') !== '' ? getenv('DB_NAME') : 'ai_shop_assistant');
define('DB_USER', getenv('DB_USER') !== false && getenv('DB_USER') !== '' ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products');
define('CACHE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cache');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_MAX_DIMENSION', 2000);
define('AI_IMAGE_MAX_DIMENSION', 1280);
define('AI_IMAGE_JPEG_QUALITY', 82);
// Overridable via config.local.php putenv('AI_TIMEOUT_SECONDS=60') etc.
define(
    'AI_TIMEOUT_SECONDS',
    (int) ((getenv('AI_TIMEOUT_SECONDS') !== false && getenv('AI_TIMEOUT_SECONDS') !== '')
        ? getenv('AI_TIMEOUT_SECONDS')
        : 45)
);
define(
    'AI_CONNECT_TIMEOUT',
    (int) ((getenv('AI_CONNECT_TIMEOUT') !== false && getenv('AI_CONNECT_TIMEOUT') !== '')
        ? getenv('AI_CONNECT_TIMEOUT')
        : 20)
);

// Session
define('SESSION_NAME', 'ai_shop_sess');

// Install lock file (created after successful install)
define('INSTALL_LOCK', ROOT_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'install.lock');

// Public URL for phones / QR (prefer LAN IP + HTTPS)
define(
    'APP_PUBLIC_URL',
    rtrim(
        (getenv('APP_PUBLIC_URL') !== false && getenv('APP_PUBLIC_URL') !== '')
            ? (string) getenv('APP_PUBLIC_URL')
            : '',
        '/'
    )
);
define('APP_PUBLIC_HOST', (string) (getenv('APP_PUBLIC_HOST') ?: ''));
define('APP_FORCE_HTTPS', (getenv('APP_FORCE_HTTPS') === '1' || getenv('APP_FORCE_HTTPS') === 'true'));
define(
    'APP_BASE_PATH',
    rtrim(str_replace('\\', '/', (string) (getenv('APP_BASE_PATH') ?: '')), '/')
);

/**
 * Detect whether the current request is HTTPS.
 */
function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Application base path from the current script (e.g. /ai_shop_assistant).
 */
function detect_base_path(): string
{
    // 1) Explicit override (best for cPanel subdirectory installs)
    if (defined('APP_BASE_PATH') && APP_BASE_PATH !== '') {
        return APP_BASE_PATH;
    }

    // 2) Compare filesystem app root to DOCUMENT_ROOT (reliable on shared hosting)
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(ROOT_PATH);
    if ($docRoot && $appRoot) {
        $docRoot = str_replace('\\', '/', $docRoot);
        $appRoot = str_replace('\\', '/', $appRoot);
        if (str_starts_with(strtolower($appRoot), strtolower($docRoot))) {
            $rel = substr($appRoot, strlen($docRoot));
            $rel = '/' . trim(str_replace('\\', '/', (string) $rel), '/');
            if ($rel !== '/') {
                return rtrim($rel, '/');
            }
            return '';
        }
    }

    // 3) SCRIPT_NAME fallback
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));

    if (preg_match('#/(admin|api)$#', $dir)) {
        $dir = dirname($dir);
    }

    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        return '';
    }

    return rtrim($dir, '/');
}

/**
 * Host as seen by the browser (honours reverse-proxy headers).
 */
function request_host(): string
{
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($host, ',')) {
        $host = trim(explode(',', $host)[0]);
    }
    return $host;
}

/**
 * True when the current PHP script lives under /admin or /api.
 */
function request_in_subdir(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return (bool) preg_match('#/(admin|api)/[^/]+$#', $script);
}

/**
 * Root-relative base path for links/assets (works through HTTPS proxy).
 * Example: "" or "/ai_shop_assistant"
 */
function app_web_root(): string
{
    return detect_base_path();
}

/**
 * Current-request absolute base URL (uses forwarded host/proto when proxied).
 */
function app_base_url(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $scheme = request_is_https() ? 'https' : 'http';
    $host = request_host();
    $dir = detect_base_path();

    $cached = rtrim($scheme . '://' . $host . $dir, '/');
    return $cached;
}

/**
 * Customer-facing public URL for QR / phone scanner (LAN IP + HTTPS).
 */
function public_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if (APP_PUBLIC_URL !== '') {
        $base = APP_PUBLIC_URL;
    } elseif (APP_PUBLIC_HOST !== '') {
        $scheme = APP_FORCE_HTTPS ? 'https' : (request_is_https() ? 'https' : 'http');
        $dir = APP_BASE_PATH !== '' ? APP_BASE_PATH : detect_base_path();
        $base = rtrim($scheme . '://' . APP_PUBLIC_HOST . $dir, '/');
    } else {
        $base = app_base_url();
        if (APP_FORCE_HTTPS && str_starts_with($base, 'http://')) {
            $base = 'https://' . substr($base, 7);
        }
    }

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

/**
 * Same-origin URL path (root-relative) — safe for CSS/JS behind HTTPS proxy.
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $root = app_web_root();
    if ($path === '') {
        return ($root === '' ? '/' : $root . '/');
    }
    return ($root === '' ? '' : $root) . '/' . $path;
}

/**
 * Asset helper.
 * Prefer root-relative URLs with correct subdirectory; if base cannot be
 * detected, fall back to paths relative to the current script (admin/api → ../).
 */
function asset(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $root = app_web_root();

    if ($root !== '' || (defined('APP_BASE_PATH') && APP_BASE_PATH !== '')) {
        return url($path);
    }

    // Last resort: relative to current page (works when SCRIPT_NAME detection fails)
    $prefix = request_in_subdir() ? '../' : '';
    return $prefix . $path;
}

/**
 * Redirect scanner (and related pages) to HTTPS on the public host when needed.
 */
function enforce_https_for_scan(): void
{
    if (!APP_FORCE_HTTPS) {
        return;
    }

    if (request_is_https()) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Allow plain HTTP only on localhost for desktop testing
    if ($host === 'localhost' || str_starts_with($host, '127.0.0.1')) {
        return;
    }

    $target = public_url(ltrim($_SERVER['REQUEST_URI'] ?? '/scan.php', '/'));
    // If REQUEST_URI already includes base path, rebuild from script name
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'scan.php');
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = public_url($script) . ($query !== '' ? ('?' . $query) : '');

    header('Location: ' . $target, true, 302);
    exit;
}
