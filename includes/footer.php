<?php
/**
 * Shared HTML footer.
 *
 * @var list<string>|null $extraScripts
 */

declare(strict_types=1);

$extraScripts = $extraScripts ?? [];
?>
</main>
<footer class="app-footer">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            <strong><?= e(APP_NAME) ?></strong>
            <span class="text-muted">· v<?= e(APP_VERSION) ?></span>
        </div>
        <div class="small text-muted">
            Scan · Identify · Shop smarter
        </div>
    </div>
</footer>
<script src="<?= e(asset('assets/js/vendor/bootstrap.bundle.min.js')) ?>"></script>
<?php foreach ($extraScripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
