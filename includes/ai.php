<?php
/**
 * AI provider abstraction (OpenAI-compatible / Agnes AI and Google Gemini).
 * API keys never leave the server.
 */

declare(strict_types=1);

/**
 * System prompt for product recognition.
 */
function ai_product_system_prompt(): string
{
    return <<<'PROMPT'
You are a retail product recognition assistant.

Analyze the provided product image.
Identify the product based only on the available image and the supplied product catalog.
Return JSON only.
Do not invent prices.
Do not invent SKU values.
Do not guess. If unsure, set sku to null.

If a barcode number is clearly visible in the image, put those digits in "barcode".
If the photo is mostly a barcode / price label and you cannot see the product packaging, set sku to null and still return the barcode digits when readable.
Only set sku when you can confidently match the visible product packaging to ONE catalog item.

Return exactly this JSON shape:
{
  "sku": "...",
  "barcode": "...",
  "product_name": "...",
  "confidence": 0.0,
  "reason": "..."
}
PROMPT;
}

/**
 * High-level chat that routes to the configured provider.
 *
 * @param list<array{role:string, content:mixed}> $messages
 * @return array{ok:bool, content?:string, error?:string, provider?:string, model?:string}
 */
function ai_chat(array $messages, ?string $modelOverride = null): array
{
    $provider = strtolower((string) (
        setting_get('ai_provider', null)
        ?: (getenv('AI_PROVIDER') ?: 'gemini')
    ));

    if ($provider === 'gemini') {
        return gemini_chat($messages, $modelOverride);
    }

    return openai_chat($messages, $modelOverride);
}

/**
 * OpenAI-compatible chat (Agnes AI and similar).
 *
 * @param list<array{role:string, content:mixed}> $messages
 */
function openai_chat(array $messages, ?string $modelOverride = null, ?int $timeoutOverride = null): array
{
    $apiUrl = rtrim((string) setting_get(
        'agnes_api_url',
        getenv('AGNES_API_URL') ?: 'https://apihub.agnes-ai.com/v1'
    ), '/');
    // Migrate deprecated host automatically
    if (stripos($apiUrl, 'api.agnes-ai.com') !== false) {
        $apiUrl = 'https://apihub.agnes-ai.com/v1';
    }
    $apiKey = (string) setting_get_secret('agnes_api_key', 'AGNES_API_KEY', '');
    $model = $modelOverride
        ?: (string) setting_get('agnes_model', getenv('AGNES_MODEL') ?: 'agnes-3.0-flash');
    $fallbacks = parse_fallback_models(
        (string) setting_get('agnes_fallback_models', 'agnes-2.5-flash')
    );

    if ($apiUrl === '' || $apiKey === '') {
        return ['ok' => false, 'error' => 'AI API URL or API key is not configured.', 'provider' => 'agnes'];
    }

    $endpoint = str_ends_with($apiUrl, '/chat/completions')
        ? $apiUrl
        : $apiUrl . '/chat/completions';

    $timeout = $timeoutOverride ?? AI_TIMEOUT_SECONDS;
    $models = array_values(array_unique(array_filter(array_merge([$model], $fallbacks))));
    if ($timeoutOverride !== null) {
        $models = array_slice($models, 0, 1); // connection test: one model only
    }
    $lastError = 'AI request failed.';

    foreach ($models as $tryModel) {
        $payload = [
            'model' => $tryModel,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => $timeoutOverride !== null ? 32 : 800,
        ];

        $response = http_json_request('POST', $endpoint, $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ], $timeout);

        if (!$response['ok']) {
            $lastError = $response['error'] ?? $lastError;
            continue;
        }

        $data = $response['data'] ?? [];
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $lastError = 'AI returned an empty response.';
            continue;
        }

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'agnes',
            'model' => $tryModel,
        ];
    }

    return ['ok' => false, 'error' => $lastError, 'provider' => 'agnes'];
}

/**
 * Google Gemini generateContent chat with optional vision.
 *
 * @param list<array{role:string, content:mixed}> $messages
 */
function gemini_chat(array $messages, ?string $modelOverride = null, ?int $timeoutOverride = null): array
{
    $apiUrl = rtrim((string) setting_get('gemini_api_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    $apiKey = (string) setting_get_secret('gemini_api_key', 'GEMINI_API_KEY', '');
    $model = $modelOverride
        ?: (string) setting_get('gemini_model', getenv('GEMINI_MODEL') ?: 'gemini-3.6-flash');
    $fallbacks = parse_fallback_models(
        (string) setting_get(
            'gemini_fallback_models',
            getenv('GEMINI_FALLBACK_MODELS') ?: 'gemini-3.7-flash,gemini-3.8-flash'
        )
    );

    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Gemini API key is not configured.', 'provider' => 'gemini'];
    }

    $timeout = $timeoutOverride ?? AI_TIMEOUT_SECONDS;

    // Connection test: exactly one model (no slow cascade)
    if ($timeoutOverride !== null || ($modelOverride !== null && $modelOverride !== '')) {
        $models = [$modelOverride ?: $model];
    } else {
        $models = array_values(array_unique(array_filter(array_merge([$model], $fallbacks))));
        $models = array_slice($models, 0, 2);
    }
    $lastError = 'AI request failed.';

    // Convert OpenAI-style messages into Gemini contents
    $systemText = '';
    $parts = [];
    $wantsJson = false;

    foreach ($messages as $message) {
        $role = $message['role'] ?? 'user';
        $content = $message['content'] ?? '';

        if ($role === 'system') {
            $systemText .= (is_string($content) ? $content : '') . "\n";
            $wantsJson = true;
            continue;
        }

        if (is_string($content)) {
            $parts[] = ['text' => $content];
            if (stripos($content, 'json') !== false) {
                $wantsJson = true;
            }
            continue;
        }

        if (is_array($content)) {
            $wantsJson = true; // vision / structured recognition
            foreach ($content as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $parts[] = ['text' => (string) ($part['text'] ?? '')];
                } elseif (($part['type'] ?? '') === 'image_url') {
                    $url = $part['image_url']['url'] ?? '';
                    if (preg_match('#^data:(image/[\w+.-]+);base64,(.+)$#', $url, $m)) {
                        $parts[] = [
                            'inlineData' => [
                                'mimeType' => $m[1],
                                'data' => $m[2],
                            ],
                        ];
                    }
                }
            }
        }
    }

    if ($systemText !== '') {
        array_unshift($parts, ['text' => trim($systemText)]);
    }

    $generationConfig = [
        'temperature' => 0.1,
        'maxOutputTokens' => $timeoutOverride !== null ? 32 : 800,
    ];
    // responseMimeType can 400 on simple ping / some models — only for recognition JSON
    if ($wantsJson) {
        $generationConfig['responseMimeType'] = 'application/json';
    }

    $body = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => $parts,
            ],
        ],
        'generationConfig' => $generationConfig,
    ];

    foreach ($models as $tryModel) {
        // AQ.* / AIza keys: x-goog-api-key (never Authorization: Bearer on native generateContent)
        $endpoint = $apiUrl . '/models/' . rawurlencode($tryModel) . ':generateContent';

        $response = http_json_request('POST', $endpoint, $body, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ], $timeout);

        $status = (int) ($response['status'] ?? 0);
        // Also try ?key= on auth failures (some proxies strip custom headers)
        if (!$response['ok'] && in_array($status, [0, 401, 403], true) && $timeoutOverride === null) {
            $endpointWithKey = $endpoint . '?key=' . rawurlencode($apiKey);
            $response = http_json_request('POST', $endpointWithKey, $body, [
                'Content-Type: application/json',
            ], $timeout);
            $status = (int) ($response['status'] ?? 0);
        }

        if (!$response['ok']) {
            $lastError = $response['error'] ?? $lastError;
            if ($status === 503 && $timeoutOverride === null) {
                usleep(250000);
            }
            continue;
        }

        $data = $response['data'] ?? [];
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $lastError = 'AI returned an empty response.';
            continue;
        }

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'gemini',
            'model' => $tryModel,
        ];
    }

    return ['ok' => false, 'error' => $lastError, 'provider' => 'gemini'];
}

/**
 * Test AI connection with a simple text prompt.
 *
 * @return array{ok:bool, message:string, provider?:string, model?:string}
 */
function test_ai_connection(): array
{
    $provider = strtolower((string) (
        setting_get('ai_provider', null)
        ?: (getenv('AI_PROVIDER') ?: 'gemini')
    ));
    $providerLabel = $provider === 'gemini' ? 'Gemini' : 'Agnes AI';

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'message' => 'Connection failed: PHP cURL extension is disabled on this hosting.',
            'provider' => $providerLabel,
            'hint' => 'In cPanel → Select PHP Version → Extensions, enable curl and openssl.',
        ];
    }

    $key = $provider === 'gemini'
        ? (string) setting_get_secret('gemini_api_key', 'GEMINI_API_KEY', '')
        : (string) setting_get_secret('agnes_api_key', 'AGNES_API_KEY', '');

    if ($key === '') {
        return [
            'ok' => false,
            'message' => 'Connection failed: API key is empty.',
            'provider' => $providerLabel,
            'hint' => 'Paste your Gemini/Agnes API key in AI Settings and click Save, then Test again.',
        ];
    }

    // Connection test — allow enough time for slow shared-host outbound routes
    $messages = [[
        'role' => 'user',
        'content' => 'Reply with exactly the word OK',
    ]];
    $testTimeout = 25;

    // Ensure Agnes uses the current API hub URL
    $agnesUrl = (string) setting_get('agnes_api_url', '');
    if ($agnesUrl === '' || stripos($agnesUrl, 'api.agnes-ai.com') !== false) {
        setting_set('agnes_api_url', 'https://apihub.agnes-ai.com/v1');
    }
    $agnesModel = (string) setting_get('agnes_model', '');
    if ($agnesModel === '' || $agnesModel === 'agnes-2.0-flash') {
        setting_set('agnes_model', 'agnes-3.0-flash');
    }

    if ($provider === 'gemini') {
        $model = (string) setting_get('gemini_model', getenv('GEMINI_MODEL') ?: 'gemini-3.6-flash');
        $result = gemini_chat($messages, $model, $testTimeout);
    } else {
        $result = openai_chat($messages, null, $testTimeout);
    }

    // If forced IPv4 timed out, retry once with default IP resolve (some hosts prefer IPv6)
    $err = (string) ($result['error'] ?? '');
    $timedOut = !$result['ok'] && (stripos($err, 'timeout') !== false || stripos($err, 'timed out') !== false);
    if ($timedOut && getenv('AI_FORCE_IPV4') !== '0') {
        $prevForce = getenv('AI_FORCE_IPV4');
        putenv('AI_FORCE_IPV4=0');
        if ($provider === 'gemini') {
            $model = (string) setting_get('gemini_model', getenv('GEMINI_MODEL') ?: 'gemini-3.6-flash');
            $result = gemini_chat($messages, $model, $testTimeout);
        } else {
            $result = openai_chat($messages, null, $testTimeout);
        }
        if ($prevForce === false) {
            putenv('AI_FORCE_IPV4');
        } else {
            putenv('AI_FORCE_IPV4=' . $prevForce);
        }
        $err = (string) ($result['error'] ?? '');
    }

    if (!$result['ok']) {
        $hint = 'Check API URL, API key, and model. Agnes URL must be https://apihub.agnes-ai.com/v1';
        if (stripos($err, 'SSL') !== false || stripos($err, 'certificate') !== false) {
            $hint = 'SSL certificate problem on hosting. Ensure includes/certs/cacert.pem was uploaded.';
        } elseif (stripos($err, 'timeout') !== false || stripos($err, 'timed out') !== false) {
            $hint = 'Outbound HTTPS timed out. Ask your host to allow outbound access to apihub.agnes-ai.com and generativelanguage.googleapis.com.';
        } elseif (stripos($err, '404') !== false || stripos($err, 'not found') !== false) {
            $hint = $provider === 'agnes'
                ? 'Agnes 404: set API URL to https://apihub.agnes-ai.com/v1 and model agnes-3.0-flash, then Save.'
                : 'Gemini model not found. Use gemini-3.6-flash (or newer), then Save.';
        } elseif (stripos($err, '503') !== false) {
            $hint = 'Provider is overloaded (503). Wait a minute or switch to Agnes AI.';
        } elseif (stripos($err, '401') !== false || stripos($err, '403') !== false) {
            $hint = 'API key rejected. Paste a fresh key and Save.';
        }

        return [
            'ok' => false,
            'message' => 'Connection failed' . ($err !== '' ? (': ' . $err) : '.'),
            'provider' => $providerLabel,
            'hint' => $hint,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Connection successful.',
        'provider' => $providerLabel,
        'model' => $result['model'] ?? '',
    ];
}

/**
 * Recognize a product from an image file path using AI + catalog matching.
 *
 * @return array{ok:bool, found?:bool, product?:array, message?:string, confidence?:float}
 */
function recognize_product_from_image(string $imagePath): array
{
    if (!is_file($imagePath)) {
        return ['ok' => false, 'message' => 'Unable to identify the product. Please try another photo.'];
    }

    $binary = file_get_contents($imagePath);
    if ($binary === false) {
        return ['ok' => false, 'message' => 'Unable to identify the product. Please try another photo.'];
    }

    $mime = 'image/jpeg';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($imagePath);
    if (is_string($detected) && str_starts_with($detected, 'image/')) {
        $mime = $detected;
    }

    $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);
    $catalog = catalog_for_ai();

    if (!$catalog) {
        return ['ok' => false, 'message' => 'Product not found in the store catalog.'];
    }

    $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $messages = [
        [
            'role' => 'system',
            'content' => ai_product_system_prompt(),
        ],
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Product catalog (JSON):\n" . $catalogJson . "\n\nIdentify the product in the image. Return JSON only.",
                ],
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => $dataUrl],
                ],
            ],
        ],
    ];

    $aiResult = ai_chat($messages);

    if (!$aiResult['ok']) {
        $err = $aiResult['error'] ?? '';
        if (stripos($err, 'timeout') !== false || stripos($err, 'timed out') !== false) {
            return ['ok' => false, 'message' => 'The AI service took too long to respond. Please try again.'];
        }
        return ['ok' => false, 'message' => 'Unable to identify the product. Please try another photo.'];
    }

    $parsed = parse_ai_json((string) ($aiResult['content'] ?? ''));
    if ($parsed === null) {
        return ['ok' => false, 'message' => 'Unable to identify the product. Please try another photo.'];
    }

    $match = match_ai_result($parsed);
    if (!$match['product']) {
        return [
            'ok' => true,
            'found' => false,
            'message' => 'Product not found in the store catalog.',
        ];
    }

    // Price ALWAYS comes from MySQL via format_product
    $product = format_product(
        $match['product'],
        (float) $match['confidence'],
        (string) $match['reason']
    );

    return [
        'ok' => true,
        'found' => true,
        'message' => 'Product found.',
        'product' => $product,
        'confidence' => $product['confidence'] ?? null,
    ];
}

/**
 * Extract and validate JSON object from AI text.
 */
function parse_ai_json(string $content): ?array
{
    $content = trim($content);

    // Strip markdown fences if present
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $content, $m)) {
        $content = $m[1];
    } elseif (preg_match('/\{.*\}/s', $content, $m)) {
        $content = $m[0];
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return null;
    }

    $sku = $data['sku'] ?? null;
    if ($sku === null || $sku === '' || strtolower((string) $sku) === 'null') {
        $data['sku'] = null;
    } else {
        $data['sku'] = trim((string) $sku);
    }

    $barcode = trim((string) ($data['barcode'] ?? ''));
    $barcode = preg_replace('/[^0-9A-Za-z]/', '', $barcode) ?? '';
    $data['barcode'] = $barcode !== '' ? $barcode : null;

    $data['product_name'] = trim((string) ($data['product_name'] ?? $data['name'] ?? ''));
    $data['confidence'] = isset($data['confidence']) ? (float) $data['confidence'] : 0.0;
    $data['reason'] = trim((string) ($data['reason'] ?? ''));

    // Never trust AI price fields
    unset($data['price'], $data['amount'], $data['cost']);

    return $data;
}

/**
 * @return list<string>
 */
function parse_fallback_models(string $raw): array
{
    $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
    $models = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $models[] = $part;
        }
    }
    return $models;
}

/**
 * HTTP JSON helper using cURL.
 *
 * @param array<string, mixed> $payload
 * @param list<string> $headers
 * @return array{ok:bool, data?:array, error?:string, status?:int}
 */
function http_json_request(string $method, string $url, array $payload, array $headers, int $timeout): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is not enabled on this server.'];
    }

    $ch = curl_init($url);
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $connectTimeout = defined('AI_CONNECT_TIMEOUT') ? (int) AI_CONNECT_TIMEOUT : 20;
    // Fast path for connection tests (timeout <= 15s)
    if ($timeout <= 15) {
        $connectTimeout = min(5, max(3, $timeout - 2));
    }
    if ($connectTimeout < 3) {
        $connectTimeout = 3;
    }
    if ($timeout < $connectTimeout) {
        $timeout = $connectTimeout;
    }

    $curlOpts = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    // Avoid long DNS hangs on some Linux PHP builds
    if (defined('CURLOPT_NOSIGNAL')) {
        $curlOpts[CURLOPT_NOSIGNAL] = true;
    }

    // Force IPv4 — many cPanel hosts have broken/slow IPv6 routes to Google
    $forceV4 = getenv('AI_FORCE_IPV4');
    if (
        $forceV4 !== '0'
        && $forceV4 !== 'false'
        && defined('CURL_IPRESOLVE_V4')
        && defined('CURLOPT_IPRESOLVE')
    ) {
        $curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }

    // Shared hosting / Windows PHP often need an explicit CA bundle
    $caBundle = ROOT_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';
    if (is_file($caBundle)) {
        $curlOpts[CURLOPT_CAINFO] = $caBundle;
    }

    // Last-resort for broken CA stores on some cPanel hosts (enable via config.local.php)
    if (getenv('AI_SSL_INSECURE') === '1' || getenv('AI_SSL_INSECURE') === 'true') {
        $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
        $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
    }

    curl_setopt_array($ch, $curlOpts);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Retry once without peer verify if SSL handshake failed (common on misconfigured cPanel)
    $sslFailed = $raw === false && (
        (defined('CURLE_SSL_CACERT') && $errno === CURLE_SSL_CACERT)
        || (defined('CURLE_SSL_PEER_CERTIFICATE') && $errno === CURLE_SSL_PEER_CERTIFICATE)
        || (defined('CURLE_SSL_CONNECT_ERROR') && $errno === CURLE_SSL_CONNECT_ERROR)
        || stripos((string) $error, 'SSL') !== false
        || stripos((string) $error, 'certificate') !== false
    );
    if ($sslFailed && getenv('AI_SSL_INSECURE') !== '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw !== false) {
            error_log('AI HTTP: SSL verify failed; succeeded with relaxed SSL (host CA issue).');
        }
    }

    // On connect/timeout with forced IPv4, retry once with default IP family
    $forceV4Active = isset($curlOpts[CURLOPT_IPRESOLVE]);
    $ipTimedOut = $raw === false && $forceV4Active && (
        (defined('CURLE_OPERATION_TIMEDOUT') && $errno === CURLE_OPERATION_TIMEDOUT)
        || (defined('CURLE_COULDNT_CONNECT') && $errno === CURLE_COULDNT_CONNECT)
        || stripos((string) $error, 'timed out') !== false
    );
    if ($ipTimedOut && defined('CURLOPT_IPRESOLVE')) {
        $whatever = defined('CURL_IPRESOLVE_WHATEVER') ? CURL_IPRESOLVE_WHATEVER : 0;
        curl_setopt($ch, CURLOPT_IPRESOLVE, $whatever);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    curl_close($ch);

    if ($errno === CURLE_OPERATION_TIMEDOUT) {
        return ['ok' => false, 'error' => 'Request timed out.', 'status' => $status];
    }

    if ($raw === false) {
        error_log('AI HTTP error: ' . $error);
        $friendly = 'Unable to reach AI service.';
        if (stripos($error, 'SSL') !== false || stripos($error, 'certificate') !== false) {
            $friendly = 'SSL certificate error talking to AI service.';
        } elseif ($error !== '') {
            $friendly = 'Unable to reach AI service (' . $error . ').';
        }
        return ['ok' => false, 'error' => $friendly, 'status' => $status];
    }

    $data = json_decode($raw, true);
    if ($status >= 400) {
        $msg = 'AI service error.';
        if (is_array($data)) {
            if (is_array($data['error'] ?? null)) {
                $msg = (string) ($data['error']['message'] ?? $data['error']['status'] ?? $msg);
            } elseif (is_string($data['error'] ?? null)) {
                $msg = (string) $data['error'];
            } elseif (is_string($data['message'] ?? null)) {
                $msg = (string) $data['message'];
            }
        }
        // Never leak API keys from error bodies
        $msg = preg_replace('/(key|token|Bearer)\s*[=:]\s*\S+/i', '$1=[redacted]', $msg) ?? $msg;
        $msg = preg_replace('/AQ\.[A-Za-z0-9_\-]+/', 'AQ.[redacted]', $msg) ?? $msg;
        $msg = preg_replace('/AIza[A-Za-z0-9_\-]+/', 'AIza[redacted]', $msg) ?? $msg;
        $msg = preg_replace('/sk-[A-Za-z0-9_\-]+/', 'sk-[redacted]', $msg) ?? $msg;
        if (strlen($msg) > 220) {
            $msg = substr($msg, 0, 217) . '...';
        }
        error_log('AI HTTP status ' . $status . ': ' . $msg);
        return [
            'ok' => false,
            'error' => 'HTTP ' . $status . ' — ' . $msg,
            'status' => $status,
        ];
    }

    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid AI response.', 'status' => $status];
    }

    return ['ok' => true, 'data' => $data, 'status' => $status];
}
