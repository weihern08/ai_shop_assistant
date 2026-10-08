<?php
/**
 * Serve product images reliably on cPanel (static /uploads often 404 or blocked).
 * Usage: image.php?f=p001-inhaler.jpg
 */
declare(strict_types=1);

$file = basename((string) ($_GET['f'] ?? ''));
if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+\.(jpe?g|png|webp|gif)$/i', $file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

$root = __DIR__;
$candidates = [
    $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $file,
    $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $file,
];

$full = null;
foreach ($candidates as $path) {
    if (is_file($path) && is_readable($path)) {
        $full = $path;
        break;
    }
}

if ($full === null) {
    // SVG placeholder
    $placeholder = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'placeholder.svg';
    if (is_file($placeholder)) {
        header('Content-Type: image/svg+xml; charset=UTF-8');
        header('Cache-Control: public, max-age=300');
        readfile($placeholder);
        exit;
    }
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
];
$mime = $types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($full));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($full);
