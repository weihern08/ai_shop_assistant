<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$q = trim((string) ($_GET['q'] ?? ''));
$products = list_products($q !== '' ? $q : null);

$pageTitle = 'Product Catalog';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h2 display-font mb-1">Product Catalog</h1>
            <p class="text-muted mb-0">Browse store products, prices, and details.</p>
        </div>
        <form class="d-flex gap-2 w-100" style="max-width:420px" method="get" action="">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search products...">
                <button class="btn btn-brand" type="submit">Search</button>
            </div>
        </form>
    </div>

    <?php if (!$products): ?>
        <div class="surface-card empty-state">
            <i class="fa-solid fa-box-open fa-2x mb-3 text-success"></i>
            <h2 class="h5">No products found</h2>
            <p class="mb-0"><?= $q !== '' ? 'Try a different search term.' : 'The catalog is empty. Administrators can add products after login.' ?></p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <article class="product-card">
                        <img class="thumb" src="<?= e(product_image_url($product['image_path'] ?? null)) ?>" alt="<?= e($product['name']) ?>" loading="lazy"
                             onerror="this.onerror=null;this.src='<?= e(asset('assets/css/placeholder.svg')) ?>';">
                        <div class="p-3">
                            <div class="price-tag mb-2"><i class="fa-solid fa-tag"></i> RM <?= e(number_format((float) $product['price'], 2)) ?></div>
                            <h2 class="h6 display-font mb-2"><?= e($product['name']) ?></h2>
                            <div class="mb-2">
                                <span class="meta-chip">SKU: <?= e($product['sku']) ?></span>
                                <?php if (!empty($product['barcode'])): ?>
                                    <span class="meta-chip"><i class="fa-solid fa-barcode"></i> <?= e($product['barcode']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($product['description'])): ?>
                                <p class="small text-muted mb-0"><?= e(mb_strimwidth((string) $product['description'], 0, 110, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
