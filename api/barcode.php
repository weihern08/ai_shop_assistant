<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_input();
if (!$input) {
    $input = $_POST;
}

$barcode = normalize_barcode((string) ($input['barcode'] ?? ''));

if ($barcode === '' || !validate_barcode($barcode)) {
    json_response([
        'success' => false,
        'message' => 'Invalid barcode.',
    ], 422);
}

try {
    $product = find_product_by_barcode($barcode);

    if (!$product) {
        json_response([
            'success' => true,
            'found' => false,
            'message' => 'Product not found.',
        ]);
    }

    json_response([
        'success' => true,
        'found' => true,
        'message' => 'Product found.',
        'product' => format_product($product),
    ]);
} catch (Throwable $e) {
    error_log('barcode API error: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Unable to look up barcode. Please try again.',
    ], 500);
}
