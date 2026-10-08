<?php
/**
 * Application settings stored in the database.
 */

declare(strict_types=1);

/**
 * Get a setting value.
 */
function setting_get(string $key, ?string $default = null): ?string
{
    if (!isset($GLOBALS['_settings_cache']) || !is_array($GLOBALS['_settings_cache'])) {
        $GLOBALS['_settings_cache'] = [];
    }
    $cache = &$GLOBALS['_settings_cache'];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string) $row['setting_value'] : $default;
    } catch (Throwable $e) {
        error_log('setting_get failed: ' . $e->getMessage());
        $cache[$key] = $default;
    }

    return $cache[$key];
}

/**
 * Set a setting value.
 */
function setting_set(string $key, ?string $value): void
{
    if (($GLOBALS['_db_driver'] ?? '') === 'sqlite') {
        $stmt = db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
        );
    } else {
        $stmt = db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
    }
    $stmt->execute([$key, $value]);

    if (!isset($GLOBALS['_settings_cache']) || !is_array($GLOBALS['_settings_cache'])) {
        $GLOBALS['_settings_cache'] = [];
    }
    $GLOBALS['_settings_cache'][$key] = $value;
}

/**
 * Get multiple settings.
 *
 * @param list<string> $keys
 * @return array<string, string|null>
 */
function settings_get_many(array $keys): array
{
    $result = [];
    foreach ($keys as $key) {
        $result[$key] = setting_get($key);
    }
    return $result;
}

/**
 * Mask an API key for display (show last 4 chars).
 */
function mask_secret(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $len = strlen($value);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', max(12, $len - 4)) . substr($value, -4);
}

/**
 * Default AI setting keys.
 *
 * @return list<string>
 */
function ai_setting_keys(): array
{
    return [
        'ai_provider',
        'agnes_api_url',
        'agnes_api_key',
        'agnes_model',
        'agnes_fallback_models',
        'gemini_api_url',
        'gemini_api_key',
        'gemini_model',
        'gemini_fallback_models',
    ];
}

/**
 * Seed default settings if missing.
 */
function ensure_default_settings(): void
{
    $provider = strtolower((string) (getenv('AI_PROVIDER') ?: 'gemini'));
    if (!in_array($provider, ['agnes', 'gemini'], true)) {
        $provider = 'gemini';
    }

    $defaults = [
        'ai_provider' => $provider,
        'agnes_api_url' => (string) (getenv('AGNES_API_URL') ?: 'https://apihub.agnes-ai.com/v1'),
        'agnes_api_key' => (string) (getenv('AGNES_API_KEY') ?: ''),
        'agnes_model' => (string) (getenv('AGNES_MODEL') ?: 'agnes-3.0-flash'),
        'agnes_fallback_models' => '',
        'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'gemini_api_key' => (string) (getenv('GEMINI_API_KEY') ?: ''),
        'gemini_model' => (string) (getenv('GEMINI_MODEL') ?: 'gemini-3.6-flash'),
        'gemini_fallback_models' => (string) (getenv('GEMINI_FALLBACK_MODELS') ?: 'gemini-3.7-flash,gemini-3.8-flash'),
    ];

    foreach ($defaults as $key => $value) {
        $stmt = db()->prepare('SELECT setting_key FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        if (!$stmt->fetch()) {
            setting_set($key, $value);
        }
    }

    // Keep DB keys in sync with config.local.php when env keys are present
    sync_ai_keys_from_env();
}

/**
 * Push API keys from environment / config.local.php into the settings table.
 */
function sync_ai_keys_from_env(): void
{
    $agnes = (string) (getenv('AGNES_API_KEY') ?: '');
    $agnesUrl = (string) (getenv('AGNES_API_URL') ?: '');
    $agnesModel = (string) (getenv('AGNES_MODEL') ?: '');
    $gemini = (string) (getenv('GEMINI_API_KEY') ?: '');
    $geminiModel = (string) (getenv('GEMINI_MODEL') ?: '');
    $geminiFallbacks = (string) (getenv('GEMINI_FALLBACK_MODELS') ?: '');
    $provider = strtolower((string) (getenv('AI_PROVIDER') ?: ''));

    if ($agnes !== '') {
        setting_set('agnes_api_key', $agnes);
    }
    if ($agnesUrl !== '') {
        setting_set('agnes_api_url', $agnesUrl);
    }
    if ($agnesModel !== '') {
        setting_set('agnes_model', $agnesModel);
    }
    if ($gemini !== '') {
        setting_set('gemini_api_key', $gemini);
    }
    if ($geminiModel !== '') {
        setting_set('gemini_model', $geminiModel);
    }
    if ($geminiFallbacks !== '') {
        setting_set('gemini_fallback_models', $geminiFallbacks);
    }
    if (in_array($provider, ['agnes', 'gemini'], true)) {
        setting_set('ai_provider', $provider);
    }
}

/**
 * Resolve a setting with environment fallback (for API keys).
 */
function setting_get_secret(string $key, string $envName, ?string $default = null): ?string
{
    $value = setting_get($key, null);
    if ($value !== null && $value !== '') {
        return $value;
    }
    $env = getenv($envName);
    if ($env !== false && $env !== '') {
        return (string) $env;
    }
    return $default;
}
