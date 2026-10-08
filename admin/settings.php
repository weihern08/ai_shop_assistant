<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

ensure_default_settings();
sync_ai_keys_from_env();

$testResult = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $formAction = (string) ($_POST['form_action'] ?? 'save');

    if ($formAction === 'test') {
        // Use the provider selected in the form (without requiring Save first)
        $testProvider = strtolower(trim((string) ($_POST['ai_provider'] ?? '')));
        if (in_array($testProvider, ['agnes', 'gemini'], true)) {
            setting_set('ai_provider', $testProvider);
        }
        $testResult = test_ai_connection();
    } else {
        $provider = strtolower(trim((string) ($_POST['ai_provider'] ?? 'agnes')));
        if (!in_array($provider, ['agnes', 'gemini'], true)) {
            $errors[] = 'Invalid AI provider.';
            $provider = 'agnes';
        }

        setting_set('ai_provider', $provider);
        setting_set('agnes_api_url', trim((string) ($_POST['agnes_api_url'] ?? '')));
        setting_set('agnes_model', trim((string) ($_POST['agnes_model'] ?? '')));
        setting_set('agnes_fallback_models', trim((string) ($_POST['agnes_fallback_models'] ?? '')));
        setting_set('gemini_api_url', trim((string) ($_POST['gemini_api_url'] ?? '')));
        setting_set('gemini_model', trim((string) ($_POST['gemini_model'] ?? '')));
        setting_set('gemini_fallback_models', trim((string) ($_POST['gemini_fallback_models'] ?? '')));

        $agnesKey = trim((string) ($_POST['agnes_api_key'] ?? ''));
        if ($agnesKey !== '' && !str_contains($agnesKey, '*')) {
            setting_set('agnes_api_key', $agnesKey);
        }

        $geminiKey = trim((string) ($_POST['gemini_api_key'] ?? ''));
        if ($geminiKey !== '' && !str_contains($geminiKey, '*')) {
            setting_set('gemini_api_key', $geminiKey);
        }

        if (!$errors) {
            flash_set('success', 'AI settings saved.');
            redirect('admin/settings.php');
        }
    }
}

$settings = settings_get_many(ai_setting_keys());
$pageTitle = 'AI Settings';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="container py-4">
    <div class="mb-4">
        <h1 class="h2 display-font mb-1">AI Settings</h1>
        <p class="text-muted mb-0">Configure the vision provider used for product recognition. API keys never appear in the customer scanner.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($testResult): ?>
        <div class="alert <?= $testResult['ok'] ? 'alert-success' : 'alert-danger' ?>">
            <strong><?= e($testResult['message']) ?></strong>
            <?php if ($testResult['ok']): ?>
                <div class="mt-1 small">
                    Provider: <?= e($testResult['provider'] ?? '') ?>
                    <?php if (!empty($testResult['model'])): ?>
                        · Model: <?= e($testResult['model']) ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($testResult['hint'])): ?>
                    <div class="mt-1 small"><?= e($testResult['hint']) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="mt-1 small">
                    <?= e($testResult['hint'] ?? 'Please check: API URL, API key, model, and internet connection.') ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="post" class="surface-card p-4 p-md-5">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save">

                <div class="mb-4">
                    <label class="form-label fw-semibold" for="ai_provider">AI Provider</label>
                    <select class="form-select form-select-lg" id="ai_provider" name="ai_provider">
                        <option value="agnes" <?= ($settings['ai_provider'] ?? '') === 'agnes' ? 'selected' : '' ?>>Agnes AI (OpenAI-compatible)</option>
                        <option value="gemini" <?= ($settings['ai_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                    </select>
                </div>

                <div class="border rounded-4 p-3 p-md-4 mb-4">
                    <h2 class="h5 display-font mb-3"><i class="fa-solid fa-bolt me-2 text-success"></i>Agnes AI / OpenAI-compatible</h2>
                    <div class="mb-3">
                        <label class="form-label" for="agnes_api_url">API URL</label>
                        <input class="form-control" id="agnes_api_url" name="agnes_api_url" value="<?= e($settings['agnes_api_url'] ?? '') ?>" placeholder="https://apihub.agnes-ai.com/v1">
                        <div class="form-text">Must be <code>https://apihub.agnes-ai.com/v1</code> (not api.agnes-ai.com).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="agnes_api_key">API Key</label>
                        <input class="form-control settings-mask" id="agnes_api_key" name="agnes_api_key"
                               value="<?= e(mask_secret($settings['agnes_api_key'] ?? '')) ?>"
                               placeholder="Leave blank to keep current key" autocomplete="off">
                        <div class="form-text">Saved keys are masked. Enter a new key only when changing it.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="agnes_model">Model</label>
                        <input class="form-control" id="agnes_model" name="agnes_model" value="<?= e($settings['agnes_model'] ?? '') ?>" placeholder="agnes-3.0-flash">
                    </div>
                    <div>
                        <label class="form-label" for="agnes_fallback_models">Fallback Models</label>
                        <textarea class="form-control" id="agnes_fallback_models" name="agnes_fallback_models" rows="3" placeholder="model-a&#10;model-b"><?= e($settings['agnes_fallback_models'] ?? '') ?></textarea>
                        <div class="form-text">One model per line (or comma-separated).</div>
                    </div>
                </div>

                <div class="border rounded-4 p-3 p-md-4 mb-4">
                    <h2 class="h5 display-font mb-3"><i class="fa-solid fa-gem me-2 text-success"></i>Google Gemini</h2>
                    <div class="mb-3">
                        <label class="form-label" for="gemini_api_url">API URL</label>
                        <input class="form-control" id="gemini_api_url" name="gemini_api_url" value="<?= e($settings['gemini_api_url'] ?? '') ?>" placeholder="https://generativelanguage.googleapis.com/v1beta">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="gemini_api_key">API Key</label>
                        <input class="form-control settings-mask" id="gemini_api_key" name="gemini_api_key"
                               value="<?= e(mask_secret($settings['gemini_api_key'] ?? '')) ?>"
                               placeholder="Leave blank to keep current key" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="gemini_model">Model</label>
                        <input class="form-control" id="gemini_model" name="gemini_model" value="<?= e($settings['gemini_model'] ?? '') ?>" placeholder="gemini-3.8-flash">
                    </div>
                    <div>
                        <label class="form-label" for="gemini_fallback_models">Fallback Models</label>
                        <textarea class="form-control" id="gemini_fallback_models" name="gemini_fallback_models" rows="3" placeholder="gemini-3.7-flash"><?= e($settings['gemini_fallback_models'] ?? '') ?></textarea>
                    </div>
                </div>

                <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save Settings</button>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="surface-card p-4 mb-3">
                <h2 class="h5 display-font">Test AI Connection</h2>
                <p class="text-muted small">Sends a lightweight request using the currently saved provider settings.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="test">
                    <button class="btn btn-accent w-100" type="submit">
                        <i class="fa-solid fa-plug me-1"></i>Test AI Connection
                    </button>
                </form>
            </div>
            <div class="surface-card p-4">
                <h2 class="h6 display-font">Security notes</h2>
                <ul class="small text-muted mb-0 ps-3">
                    <li>API keys stay on the server only</li>
                    <li>Customer scanner never receives credentials</li>
                    <li>Displayed prices always come from MySQL</li>
                    <li>Use HTTPS in production</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
