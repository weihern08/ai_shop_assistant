<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

// Phone camera requires HTTPS — redirect LAN HTTP → https://YOUR-IP/...
enforce_https_for_scan();

$pageTitle = 'Scanner';
$bodyClass = 'scanner-page';
$hideNav = true;

require __DIR__ . '/includes/header.php';
?>

<div class="scanner-shell">
    <div class="scanner-top">
        <a href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Home</a>
        <strong class="display-font"><?= e(APP_NAME) ?></strong>
        <span style="width:3.5rem"></span>
    </div>

    <div class="camera-stage" id="cameraStage">
        <video id="cameraVideo" playsinline muted autoplay></video>
        <canvas id="captureCanvas"></canvas>
        <img id="capturedPreview" alt="Captured product" hidden>
        <div class="scan-frame" id="scanFrame"></div>
        <div class="scan-line" id="scanLine"></div>
        <div class="status-pill" id="scanStatus">Starting camera...</div>
    </div>

    <div class="scanner-controls">
        <button type="button" class="btn btn-secondary-dark" id="btnSwitch" title="Switch camera">
            <i class="fa-solid fa-camera-rotate d-block mb-1"></i>Switch
        </button>
        <button type="button" class="btn btn-capture" id="btnCapture">
            <i class="fa-solid fa-camera d-block mb-1"></i>Capture
        </button>
        <button type="button" class="btn btn-secondary-dark" id="btnRetake" disabled>
            <i class="fa-solid fa-rotate-left d-block mb-1"></i>Retake
        </button>
    </div>

    <div id="resultPanel" class="result-panel" hidden></div>

    <p class="small text-center mt-3 mb-0" style="color:rgba(234,247,241,.65)">
        Hold the barcode steady inside the yellow frame — lookup is <strong>automatic</strong> (no button needed).
        Capture is only for AI photo recognition when there is no barcode.
    </p>
    <?php if (!request_is_https() && APP_FORCE_HTTPS): ?>
    <div class="alert alert-warning mt-3 mb-0 small">
        You are on HTTP — camera will not work on a phone.
        Open: <a href="<?= e(public_url('scan.php')) ?>"><?= e(public_url('scan.php')) ?></a>
    </div>
    <?php endif; ?>
</div>

<script>
window.SCAN_CONFIG = {
    barcodeApi: <?= json_encode(url('api/barcode.php'), JSON_UNESCAPED_SLASHES) ?>,
    recognizeApi: <?= json_encode(url('api/recognize.php'), JSON_UNESCAPED_SLASHES) ?>,
    placeholder: <?= json_encode(asset('assets/css/placeholder.svg'), JSON_UNESCAPED_SLASHES) ?>
};
</script>
<?php
$extraScripts = [
    asset('assets/js/vendor/zxing.min.js'),
    asset('assets/js/scan.js') . '?v=2',
];
require __DIR__ . '/includes/footer.php';
?>
