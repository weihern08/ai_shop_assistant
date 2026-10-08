<?php
/**
 * Product catalog helpers and AI matching.
 */

declare(strict_types=1);

/**
 * Normalize text for fuzzy matching.
 */
function normalize_text(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

/**
 * Normalize barcode (digits/letters only, trim leading zeros carefully for UPC).
 */
function normalize_barcode(string $barcode): string
{
    $barcode = trim($barcode);
    $barcode = preg_replace('/[^0-9A-Za-z]/', '', $barcode) ?? '';
    return $barcode;
}

/**
 * Validate SKU format.
 */
function validate_sku(string $sku): bool
{
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_.]{1,39}$/', $sku);
}

/**
 * Validate barcode characters.
 */
function validate_barcode(string $barcode): bool
{
    if ($barcode === '') {
        return true;
    }
    return (bool) preg_match('/^[0-9A-Za-z\-]{4,64}$/', $barcode);
}

/**
 * Format product for API / UI (never invents price).
 */
function format_product(array $row, ?float $confidence = null, ?string $reason = null): array
{
    $product = [
        'id' => (int) $row['id'],
        'sku' => $row['sku'],
        'barcode' => $row['barcode'],
        'name' => $row['name'],
        'price' => number_format((float) $row['price'], 2, '.', ''),
        'description' => $row['description'] ?? '',
        'image' => $row['image_path'] ?? null,
        'image_url' => product_image_url($row['image_path'] ?? null),
        'detect_keywords' => $row['detect_keywords'] ?? '',
    ];

    if ($confidence !== null) {
        $product['confidence'] = round(max(0, min(1, $confidence)), 4);
    }
    if ($reason !== null && $reason !== '') {
        $product['reason'] = $reason;
    }

    return $product;
}

/**
 * Find product by barcode.
 */
function find_product_by_barcode(string $barcode): ?array
{
    $barcode = normalize_barcode($barcode);
    if ($barcode === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM products WHERE barcode = ? LIMIT 1');
    $stmt->execute([$barcode]);
    $row = $stmt->fetch();

    // Also try without leading zeros / with common padding
    if (!$row && ctype_digit($barcode)) {
        $trimmed = ltrim($barcode, '0');
        $stmt = db()->query('SELECT * FROM products WHERE barcode IS NOT NULL');
        foreach ($stmt->fetchAll() as $candidate) {
            $cand = normalize_barcode((string) $candidate['barcode']);
            if ($cand === $barcode || ltrim($cand, '0') === $trimmed) {
                $row = $candidate;
                break;
            }
        }
    }

    return $row ?: null;
}

/**
 * Find product by SKU.
 */
function find_product_by_sku(string $sku): ?array
{
    $sku = trim($sku);
    if ($sku === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM products WHERE sku = ? LIMIT 1');
    $stmt->execute([$sku]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Find product by ID.
 */
function find_product_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * List products with optional search.
 *
 * @return list<array>
 */
function list_products(?string $q = null): array
{
    if ($q !== null && trim($q) !== '') {
        $like = '%' . trim($q) . '%';
        $stmt = db()->prepare(
            'SELECT * FROM products
             WHERE name LIKE ? OR sku LIKE ? OR barcode LIKE ? OR detect_keywords LIKE ? OR description LIKE ?
             ORDER BY name ASC'
        );
        $stmt->execute([$like, $like, $like, $like, $like]);
        return $stmt->fetchAll();
    }

    return db()->query('SELECT * FROM products ORDER BY name ASC')->fetchAll();
}

/**
 * Compact catalog for AI prompt.
 *
 * @return list<array{sku:string,name:string,detect_keywords:string,description:string}>
 */
function catalog_for_ai(): array
{
    $rows = db()->query(
        'SELECT sku, barcode, name, detect_keywords, description FROM products ORDER BY name ASC LIMIT 200'
    )->fetchAll();

    $catalog = [];
    foreach ($rows as $row) {
        $catalog[] = [
            'sku' => $row['sku'],
            'barcode' => (string) ($row['barcode'] ?? ''),
            'name' => $row['name'],
            'detect_keywords' => (string) ($row['detect_keywords'] ?? ''),
            'description' => mb_substr((string) ($row['description'] ?? ''), 0, 120),
        ];
    }
    return $catalog;
}

/**
 * Match AI identification result against local catalog.
 *
 * Priority: SKU → product name → keywords → normalized text.
 *
 * @return array{product:?array, confidence:float, reason:string, method:?string}
 */
function match_ai_result(array $ai): array
{
    $sku = trim((string) ($ai['sku'] ?? ''));
    $barcode = trim((string) ($ai['barcode'] ?? ''));
    $name = trim((string) ($ai['product_name'] ?? $ai['name'] ?? ''));
    $confidence = isset($ai['confidence']) ? (float) $ai['confidence'] : 0.0;
    $reason = trim((string) ($ai['reason'] ?? ''));

    // Method 0 — barcode visible in photo (most reliable for label scans)
    if ($barcode !== '') {
        $product = find_product_by_barcode($barcode);
        if ($product) {
            return [
                'product' => $product,
                'confidence' => max($confidence, 0.95),
                'reason' => $reason !== '' ? $reason : 'Matched by barcode read from image.',
                'method' => 'barcode',
            ];
        }
    }

    // Method 1 — SKU (only accept when AI is reasonably confident)
    if ($sku !== '' && strtolower($sku) !== 'null' && $confidence >= 0.55) {
        $product = find_product_by_sku($sku);
        if ($product) {
            return [
                'product' => $product,
                'confidence' => $confidence > 0 ? $confidence : 0.9,
                'reason' => $reason !== '' ? $reason : 'Matched by SKU.',
                'method' => 'sku',
            ];
        }
    }

    $products = list_products();
    if (!$products) {
        return ['product' => null, 'confidence' => 0, 'reason' => 'Catalog is empty.', 'method' => null];
    }

    $normName = normalize_text($name);
    if ($normName === '' || mb_strlen($normName) < 3) {
        return ['product' => null, 'confidence' => 0, 'reason' => 'No confident catalog match.', 'method' => null];
    }

    $best = null;
    $bestScore = 0.0;
    $bestMethod = null;

    foreach ($products as $product) {
        $pName = normalize_text((string) $product['name']);
        $keywords = (string) ($product['detect_keywords'] ?? '');

        // Method 2 — exact / close product name
        if ($pName !== '') {
            if ($normName === $pName) {
                return [
                    'product' => $product,
                    'confidence' => max($confidence, 0.9),
                    'reason' => $reason !== '' ? $reason : 'Matched by product name.',
                    'method' => 'name',
                ];
            }

            similar_text($normName, $pName, $pct);
            $score = $pct / 100;
            // Require strong name similarity to avoid wrong catalog hits
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $product;
                $bestMethod = 'name_fuzzy';
            }
        }

        // Method 3 — detection keywords (exact token / phrase only — no blob fuzzy)
        if ($keywords !== '') {
            $parts = preg_split('/\s*,\s*/', $keywords) ?: [];
            foreach ($parts as $kw) {
                $kw = normalize_text($kw);
                if ($kw === '' || mb_strlen($kw) < 4) {
                    continue;
                }
                if ($normName === $kw || str_contains($normName, $kw)) {
                    $score = 0.82;
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $product;
                        $bestMethod = 'keywords';
                    }
                }
            }
        }
    }

    // Strict threshold — weak fuzzy matches caused wrong products (e.g. Thai inhaler)
    $threshold = 0.78;
    if ($best && $bestScore >= $threshold && $confidence >= 0.45) {
        return [
            'product' => $best,
            'confidence' => max($confidence, $bestScore),
            'reason' => $reason !== '' ? $reason : 'Matched by catalog text comparison.',
            'method' => $bestMethod,
        ];
    }

    return [
        'product' => null,
        'confidence' => $bestScore,
        'reason' => 'No confident catalog match.',
        'method' => null,
    ];
}

/**
 * Create a product.
 *
 * @param array<string, mixed> $data
 */
function create_product(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO products (sku, barcode, name, detect_keywords, price, description, image_path)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['sku'],
        $data['barcode'] !== '' ? $data['barcode'] : null,
        $data['name'],
        $data['detect_keywords'] !== '' ? $data['detect_keywords'] : null,
        $data['price'],
        $data['description'] !== '' ? $data['description'] : null,
        $data['image_path'] ?? null,
    ]);
    return (int) db()->lastInsertId();
}

/**
 * Update a product.
 *
 * @param array<string, mixed> $data
 */
function update_product(int $id, array $data): void
{
    $stmt = db()->prepare(
        'UPDATE products
         SET sku = ?, barcode = ?, name = ?, detect_keywords = ?, price = ?, description = ?, image_path = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $data['sku'],
        $data['barcode'] !== '' ? $data['barcode'] : null,
        $data['name'],
        $data['detect_keywords'] !== '' ? $data['detect_keywords'] : null,
        $data['price'],
        $data['description'] !== '' ? $data['description'] : null,
        $data['image_path'] ?? null,
        $id,
    ]);
}

/**
 * Delete a product and its image.
 */
function delete_product(int $id): bool
{
    $product = find_product_by_id($id);
    if (!$product) {
        return false;
    }
    delete_product_image($product['image_path'] ?? null);
    $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    return true;
}

/**
 * Validate product form fields.
 *
 * @return array{ok:bool, errors:array<string,string>, data?:array}
 */
function validate_product_input(array $input, ?int $ignoreId = null): array
{
    $errors = [];

    $sku = trim((string) ($input['sku'] ?? ''));
    $barcode = trim((string) ($input['barcode'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $keywords = trim((string) ($input['detect_keywords'] ?? ''));
    $priceRaw = trim((string) ($input['price'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));

    if ($sku === '' || !validate_sku($sku)) {
        $errors['sku'] = 'SKU is required (2–40 letters, numbers, - _ .).';
    } else {
        $sql = 'SELECT id FROM products WHERE sku = ?';
        $params = [$sku];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors['sku'] = 'This SKU already exists.';
        }
    }

    if ($barcode !== '' && !validate_barcode($barcode)) {
        $errors['barcode'] = 'Barcode contains invalid characters.';
    } elseif ($barcode !== '') {
        $barcode = normalize_barcode($barcode);
        $sql = 'SELECT id FROM products WHERE barcode = ?';
        $params = [$barcode];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors['barcode'] = 'This barcode already exists.';
        }
    }

    if ($name === '' || mb_strlen($name) > 120) {
        $errors['name'] = 'Product name is required (max 120 characters).';
    }

    if ($keywords !== '' && mb_strlen($keywords) > 255) {
        $errors['detect_keywords'] = 'Detection keywords must be at most 255 characters.';
    }

    if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
        $errors['price'] = 'Enter a valid non-negative price.';
    }

    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    return [
        'ok' => true,
        'errors' => [],
        'data' => [
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $name,
            'detect_keywords' => $keywords,
            'price' => number_format((float) $priceRaw, 2, '.', ''),
            'description' => $description,
        ],
    ];
}
