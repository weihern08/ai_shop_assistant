<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

$action = (string) ($_GET['action'] ?? 'list');
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = [];
$form = [
    'sku' => '',
    'barcode' => '',
    'name' => '',
    'detect_keywords' => '',
    'price' => '',
    'description' => '',
    'image_path' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $postAction = (string) ($_POST['action'] ?? '');

    if ($postAction === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        if ($deleteId > 0 && delete_product($deleteId)) {
            flash_set('success', 'Product deleted.');
        } else {
            flash_set('error', 'Unable to delete product.');
        }
        redirect('admin/products.php');
    }

    $editId = (int) ($_POST['id'] ?? 0);
    $validated = validate_product_input($_POST, $editId > 0 ? $editId : null);

    if (!$validated['ok']) {
        $errors = $validated['errors'];
        $form = array_merge($form, [
            'sku' => (string) ($_POST['sku'] ?? ''),
            'barcode' => (string) ($_POST['barcode'] ?? ''),
            'name' => (string) ($_POST['name'] ?? ''),
            'detect_keywords' => (string) ($_POST['detect_keywords'] ?? ''),
            'price' => (string) ($_POST['price'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
        ]);
        $action = $editId > 0 ? 'edit' : 'add';
        $id = $editId;
        if ($editId > 0) {
            $existing = find_product_by_id($editId);
            $form['image_path'] = $existing['image_path'] ?? null;
        }
    } else {
        $data = $validated['data'];
        $imagePath = null;

        if ($editId > 0) {
            $existing = find_product_by_id($editId);
            if (!$existing) {
                flash_set('error', 'Product not found.');
                redirect('admin/products.php');
            }
            $imagePath = $existing['image_path'] ?? null;
        }

        if (!empty($_FILES['image']['name'])) {
            $upload = save_product_image($_FILES['image'], $editId > 0 ? $editId : null);
            if (!$upload['ok']) {
                $errors['image'] = $upload['error'] ?? 'Image upload failed.';
                $form = array_merge($form, $data, ['image_path' => $imagePath]);
                $action = $editId > 0 ? 'edit' : 'add';
                $id = $editId;
            } else {
                if ($imagePath) {
                    delete_product_image($imagePath);
                }
                $imagePath = $upload['path'];
            }
        }

        if (!$errors) {
            $data['image_path'] = $imagePath;

            if ($editId > 0) {
                update_product($editId, $data);
                flash_set('success', 'Product updated.');
            } else {
                create_product($data);
                flash_set('success', 'Product created.');
            }
            redirect('admin/products.php');
        }
    }
}

if (($action === 'edit' || $action === 'add') && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($action === 'edit') {
        $product = find_product_by_id($id);
        if (!$product) {
            flash_set('error', 'Product not found.');
            redirect('admin/products.php');
        }
        $form = [
            'sku' => $product['sku'],
            'barcode' => (string) ($product['barcode'] ?? ''),
            'name' => $product['name'],
            'detect_keywords' => (string) ($product['detect_keywords'] ?? ''),
            'price' => number_format((float) $product['price'], 2, '.', ''),
            'description' => (string) ($product['description'] ?? ''),
            'image_path' => $product['image_path'] ?? null,
        ];
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$products = ($action === 'list') ? list_products($q !== '' ? $q : null) : [];

$pageTitle = 'Manage Products';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h2 display-font mb-1">Manage Products</h1>
            <p class="text-muted mb-0">Add, edit, and remove catalog items.</p>
        </div>
        <?php if ($action === 'list'): ?>
            <a class="btn btn-brand" href="<?= e(url('admin/products.php?action=add')) ?>">
                <i class="fa-solid fa-plus me-1"></i>Add Product
            </a>
        <?php else: ?>
            <a class="btn btn-soft" href="<?= e(url('admin/products.php')) ?>">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to list
            </a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>
        <div class="surface-card p-3 p-md-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="h5 display-font mb-1"><i class="fa-solid fa-gauge-high me-2 text-success"></i>Admin dashboard</h2>
                    <p class="text-muted small mb-0">Signed in as <?= e(current_user()['email'] ?? '') ?></p>
                </div>
                <div class="alert alert-success border-0 mb-0 py-2 px-3">
                    <div class="fw-semibold small mb-1"><i class="fa-solid fa-key me-1"></i>Login credentials</div>
                    <div class="small mb-0">
                        <div>User: <code>admin@shop.local</code></div>
                        <div>Password: <code>Admin@12345</code></div>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="btn btn-soft btn-sm" href="<?= e(url('admin/settings.php')) ?>"><i class="fa-solid fa-robot me-1"></i>AI Settings</a>
                <a class="btn btn-soft btn-sm" href="<?= e(url('products.php')) ?>"><i class="fa-solid fa-store me-1"></i>Public Catalog</a>
                <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= e(url('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="surface-card p-4 p-md-5">
            <h2 class="h4 display-font mb-3"><?= $action === 'edit' ? 'Edit Product' : 'Add Product' ?></h2>
            <form method="post" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="sku">SKU *</label>
                        <input class="form-control <?= isset($errors['sku']) ? 'is-invalid' : '' ?>" id="sku" name="sku" required value="<?= e($form['sku']) ?>">
                        <?php if (isset($errors['sku'])): ?><div class="invalid-feedback"><?= e($errors['sku']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="barcode">Barcode</label>
                        <input class="form-control <?= isset($errors['barcode']) ? 'is-invalid' : '' ?>" id="barcode" name="barcode" value="<?= e($form['barcode']) ?>">
                        <?php if (isset($errors['barcode'])): ?><div class="invalid-feedback"><?= e($errors['barcode']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="price">Price (RM) *</label>
                        <input class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" id="price" name="price" required inputmode="decimal" value="<?= e($form['price']) ?>">
                        <?php if (isset($errors['price'])): ?><div class="invalid-feedback"><?= e($errors['price']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="name">Product Name *</label>
                        <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" required maxlength="120" value="<?= e($form['name']) ?>">
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="detect_keywords">AI Detection Keywords</label>
                        <input class="form-control <?= isset($errors['detect_keywords']) ? 'is-invalid' : '' ?>" id="detect_keywords" name="detect_keywords" maxlength="255"
                               value="<?= e($form['detect_keywords']) ?>" placeholder="comma, separated, keywords">
                        <?php if (isset($errors['detect_keywords'])): ?><div class="invalid-feedback"><?= e($errors['detect_keywords']) ?></div><?php endif; ?>
                        <div class="form-text">Helps the AI match photos to this product.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= e($form['description']) ?></textarea>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="image">Product Image</label>
                        <input class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <?php if (isset($errors['image'])): ?><div class="invalid-feedback d-block"><?= e($errors['image']) ?></div><?php endif; ?>
                        <div class="form-text">JPG, PNG, or WEBP · max 5 MB · max 2000px</div>
                    </div>
                    <div class="col-md-4">
                        <?php if (!empty($form['image_path'])): ?>
                            <label class="form-label">Current image</label>
                            <div>
                                <img src="<?= e(product_image_url($form['image_path'])) ?>" alt="" class="img-fluid rounded" style="max-height:120px">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save Product</button>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <form class="mb-3" method="get" action="">
            <div class="input-group" style="max-width:420px">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search products...">
                <button class="btn btn-brand" type="submit">Search</button>
            </div>
        </form>

        <div class="surface-card p-0 overflow-hidden">
            <?php if (!$products): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open fa-2x mb-3 text-success"></i>
                    <h2 class="h5">No products yet</h2>
                    <p class="mb-3">Add your first catalog item to start scanning.</p>
                    <a class="btn btn-brand" href="<?= e(url('admin/products.php?action=add')) ?>">Add Product</a>
                </div>
            <?php else: ?>
                <div class="table-responsive admin-table">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>SKU / Barcode</th>
                                <th>Price</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <img class="thumb-sm" src="<?= e(product_image_url($product['image_path'] ?? null)) ?>" alt="">
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($product['name']) ?></div>
                                    <?php if (!empty($product['detect_keywords'])): ?>
                                        <div class="small text-muted"><?= e(mb_strimwidth((string) $product['detect_keywords'], 0, 60, '…')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= e($product['sku']) ?></div>
                                    <div class="small text-muted"><?= e($product['barcode'] ?: '—') ?></div>
                                </td>
                                <td class="fw-bold">RM <?= e(number_format((float) $product['price'], 2)) ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-soft" href="<?= e(url('admin/products.php?action=edit&id=' . (int) $product['id'])) ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
