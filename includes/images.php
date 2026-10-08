<?php
/**
 * Secure product image upload and processing helpers.
 */

declare(strict_types=1);

/**
 * Allowed image MIME types mapped to extensions.
 *
 * @return array<string, string>
 */
function allowed_image_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

/**
 * Validate and store an uploaded product image.
 *
 * @return array{ok:bool, path?:string, error?:string}
 */
function save_product_image(array $file, ?int $productId = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No image uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image must be under 5 MB.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = allowed_image_mimes();

    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    $imageInfo = @getimagesize($tmp);
    if ($imageInfo === false) {
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }

    [$width, $height] = $imageInfo;
    if ($width < 32 || $height < 32) {
        return ['ok' => false, 'error' => 'Image is too small.'];
    }
    if ($width > UPLOAD_MAX_DIMENSION || $height > UPLOAD_MAX_DIMENSION) {
        return ['ok' => false, 'error' => 'Image dimensions must be at most ' . UPLOAD_MAX_DIMENSION . 'px.'];
    }

    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    $ext = $allowed[$mime];
    $prefix = $productId ? ('p' . $productId) : ('p' . bin2hex(random_bytes(3)));
    $filename = $prefix . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'error' => 'Unable to save image.'];
    }

    @chmod($dest, 0644);

    return ['ok' => true, 'path' => 'uploads/products/' . $filename];
}

/**
 * Delete a product image file safely (only under uploads/products).
 */
function delete_product_image(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    $normalized = str_replace(['\\', '..'], ['/', ''], $relativePath);
    if (!str_starts_with($normalized, 'uploads/products/')) {
        return;
    }

    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Find a directory PHP can write to (cPanel often locks uploads/cache).
 */
function ai_writable_temp_dir(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $candidates = [
        CACHE_PATH,
        ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cache',
        ROOT_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tmp',
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_shop_assistant',
        sys_get_temp_dir(),
    ];

    foreach ($candidates as $dir) {
        $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
        if ($dir === '') {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            continue;
        }
        $probe = $dir . DIRECTORY_SEPARATOR . '.w-' . bin2hex(random_bytes(4));
        if (@file_put_contents($probe, '1') !== false) {
            @unlink($probe);
            $cached = $dir;
            return $cached;
        }
    }

    $cached = '';
    return $cached;
}

/**
 * Decode a base64 data URL image, resize, save temporarily for AI processing.
 *
 * @return array{ok:bool, path?:string, error?:string}
 */
function save_capture_temp(string $dataUrl): array
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        return ['ok' => false, 'error' => 'PHP GD extension is not enabled on this hosting.'];
    }

    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $dataUrl, $m)) {
        return ['ok' => false, 'error' => 'Invalid image data.'];
    }

    $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
    // Strip whitespace that some mobiles insert
    $base64 = preg_replace('/\s+/', '', $base64) ?? $base64;
    $binary = base64_decode($base64, true);
    if ($binary === false || strlen($binary) < 100) {
        return ['ok' => false, 'error' => 'Unable to decode image.'];
    }

    if (strlen($binary) > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Captured image is too large.'];
    }

    // Decode in memory first — no need to write the raw capture to disk
    $image = @imagecreatefromstring($binary);
    if ($image === false) {
        return ['ok' => false, 'error' => 'Invalid captured image.'];
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $max = AI_IMAGE_MAX_DIMENSION;

    if ($width > $max || $height > $max) {
        $ratio = min($max / $width, $max / $height);
        $newW = max(1, (int) round($width * $ratio));
        $newH = max(1, (int) round($height * $ratio));
        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    $dir = ai_writable_temp_dir();
    if ($dir === '') {
        imagedestroy($image);
        return [
            'ok' => false,
            'error' => 'Unable to store temporary image. Make uploads/cache writable (chmod 775) on cPanel.',
        ];
    }

    $outPath = $dir . DIRECTORY_SEPARATOR . 'cap-' . bin2hex(random_bytes(8)) . '.jpg';
    $ok = @imagejpeg($image, $outPath, AI_IMAGE_JPEG_QUALITY);
    imagedestroy($image);

    if (!$ok || !is_file($outPath)) {
        return [
            'ok' => false,
            'error' => 'Unable to store temporary image. Make uploads/cache writable (chmod 775) on cPanel.',
        ];
    }

    @chmod($outPath, 0644);

    return ['ok' => true, 'path' => $outPath];
}

/**
 * Public URL for a product image path.
 * Served via image.php so cPanel static /uploads blocks cannot hide catalog photos.
 */
function product_image_url(?string $path): string
{
    $placeholder = asset('assets/css/placeholder.svg');

    if ($path === null || trim($path) === '') {
        return $placeholder;
    }

    $relative = ltrim(str_replace('\\', '/', $path), '/');
    if (str_contains($relative, '..')) {
        return $placeholder;
    }

    $file = basename($relative);
    if (!preg_match('/^[a-zA-Z0-9._-]+\.(jpe?g|png|webp|gif)$/i', $file)) {
        return $placeholder;
    }

    $candidates = [
        ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $file,
        ROOT_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $file,
    ];
    $version = (string) time();
    foreach ($candidates as $full) {
        if (is_file($full)) {
            $version = (string) filemtime($full);
            break;
        }
    }

    return url('image.php') . '?f=' . rawurlencode($file) . '&v=' . rawurlencode($version);
}
