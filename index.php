<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Home';
// QR always points at LAN IP + HTTPS when configured in config.local.php
$scanUrl = public_url('scan.php');
$isAdmin = is_admin();
$productCount = 0;

try {
    $productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
} catch (Throwable $e) {
    // installer will handle
}

require __DIR__ . '/includes/header.php';
?>

<div class="container py-4 py-lg-5">
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-7">
            <section class="hero-panel h-100">
                <div class="hero-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Retail AI Vision</div>
                <h1 class="hero-title"><?= e(APP_NAME) ?></h1>
                <p class="hero-lead mb-4">
                    Scan a product and instantly check its price and information.
                    Use barcode recognition or AI image identification — no account required.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-accent" href="<?= e($scanUrl) ?>">
                        <i class="fa-solid fa-camera me-2"></i>Open Scanner
                    </a>
                    <a class="btn btn-light" href="<?= e(url('products.php')) ?>">
                        <i class="fa-solid fa-store me-2"></i>Browse Catalog
                    </a>
                </div>
                <div class="mt-4 small" style="opacity:.8">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>
                    <?= (int) $productCount ?> products in catalog
                    · Phone scanner: HTTPS + LAN IP
                    <?php if (APP_PUBLIC_HOST !== ''): ?>
                        (<?= e(APP_PUBLIC_HOST) ?>)
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="qr-card h-100 d-flex flex-column justify-content-center">
                <h2 class="h4 display-font mb-2">Scan the QR code with your phone</h2>
                <p class="text-muted mb-3">
                    Scan with your phone using the HTTPS link below.
                    First time: tap Advanced → Proceed (self-signed certificate), then Allow camera.
                </p>
                <div id="qrcode" class="mb-3 d-flex justify-content-center" aria-label="Customer scanner QR code"></div>
                <div class="qr-url mb-3">
                    <div class="small text-uppercase fw-semibold mb-1">Customer scan URL</div>
                    <a href="<?= e($scanUrl) ?>"><?= e($scanUrl) ?></a>
                </div>
                <a class="btn btn-brand w-100" href="<?= e($scanUrl) ?>">
                    <i class="fa-solid fa-qrcode me-2"></i>Open Scanner
                </a>
            </section>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="surface-card p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1 display-font"><i class="fa-solid fa-gauge-high me-2 text-success"></i>Admin dashboard</h2>
                        <span class="text-muted small">Signed in as <?= e(current_user()['email'] ?? '') ?></span>
                    </div>
                    <div class="alert alert-success border-0 mb-0 py-2 px-3">
                        <div class="fw-semibold small mb-1"><i class="fa-solid fa-key me-1"></i>Login credentials</div>
                        <div class="small mb-0">
                            <div>User: <code>admin@shop.local</code></div>
                            <div>Password: <code>Admin@12345</code></div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-soft" href="<?= e(url('products.php')) ?>"><i class="fa-solid fa-store me-1"></i>Product Catalog</a>
                    <a class="btn btn-soft" href="<?= e(url('admin/products.php')) ?>"><i class="fa-solid fa-boxes-stacked me-1"></i>Manage Products</a>
                    <a class="btn btn-soft" href="<?= e(url('admin/settings.php')) ?>"><i class="fa-solid fa-robot me-1"></i>AI Settings</a>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= e(url('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <div class="surface-card p-4 h-100">
                <div class="mb-2 text-success"><i class="fa-solid fa-barcode fa-lg"></i></div>
                <h3 class="h5 display-font">Barcode first</h3>
                <p class="text-muted mb-0">Point at a retail barcode for an instant catalog lookup from MySQL.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="surface-card p-4 h-100">
                <div class="mb-2 text-success"><i class="fa-solid fa-robot fa-lg"></i></div>
                <h3 class="h5 display-font">AI fallback</h3>
                <p class="text-muted mb-0">Capture a product photo and let vision AI match it to your store catalog.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="surface-card p-4 h-100">
                <div class="mb-2 text-success"><i class="fa-solid fa-shield-halved fa-lg"></i></div>
                <h3 class="h5 display-font">Secure pricing</h3>
                <p class="text-muted mb-0">Prices always come from your database — never invented by the AI model.</p>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = [
    asset('assets/js/vendor/qrcode.min.js'),
];
require __DIR__ . '/includes/footer.php';
?>
<script>
(function () {
    var el = document.getElementById('qrcode');
    if (!el || typeof QRCode === 'undefined') return;
    new QRCode(el, {
        text: <?= json_encode($scanUrl, JSON_UNESCAPED_SLASHES) ?>,
        width: 220,
        height: 220,
        colorDark: '#0b3d2e',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
})();
</script>
