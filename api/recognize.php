<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_input();
$image = (string) ($input['image'] ?? '');

if ($image === '' || !str_starts_with($image, 'data:image/')) {
    json_response([
        'success' => false,
        'message' => 'Unable to identify the product. Please try another photo.',
    ], 422);
}

$saved = save_capture_temp($image);
if (!$saved['ok']) {
    json_response([
        'success' => false,
        'message' => $saved['error'] ?? 'Unable to identify the product. Please try another photo.',
    ], 422);
}

$tempPath = $saved['path'];

try {
    $result = recognize_product_from_image($tempPath);

    if (!($result['ok'] ?? false)) {
        json_response([
            'success' => false,
            'message' => $result['message'] ?? 'Unable to identify the product. Please try another photo.',
        ], 200);
    }

    if (!($result['found'] ?? false)) {
        json_response([
            'success' => true,
            'found' => false,
            'message' => $result['message'] ?? 'Product not found in the store catalog.',
        ]);
    }

    // Ensure price is only from DB-formatted product
    json_response([
        'success' => true,
        'found' => true,
        'message' => 'Product found.',
        'product' => $result['product'],
    ]);
} catch (Throwable $e) {
    error_log('recognize API error: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Unable to identify the product. Please try another photo.',
    ], 500);
} finally {
    if (isset($tempPath) && is_file($tempPath)) {
        @unlink($tempPath);
    }
}
