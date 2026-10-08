<?php
/**
 * Copy this file to config.local.php and fill in your values.
 * Do not commit config.local.php (it may contain secrets).
 */
declare(strict_types=1);

$dbHost = 'localhost';
$dbName = 'ai_shop_assistant';
$dbUser = 'root';
$dbPass = '';

putenv('DB_HOST=' . $dbHost);
putenv('DB_NAME=' . $dbName);
putenv('DB_USER=' . $dbUser);
putenv('DB_PASS=' . $dbPass);

// Leave empty to auto-detect path (works for subdirectory installs)
putenv('APP_BASE_PATH=');
putenv('APP_PUBLIC_URL=');
putenv('APP_FORCE_HTTPS=0');

// Optional AI defaults (keys can also be saved in Admin → AI Settings)
putenv('AI_PROVIDER=agnes');
putenv('AGNES_API_URL=https://apihub.agnes-ai.com/v1');
putenv('AGNES_API_KEY=');
putenv('AGNES_MODEL=agnes-3.0-flash');
putenv('GEMINI_API_KEY=');
putenv('GEMINI_MODEL=gemini-3.6-flash');

putenv('AI_TIMEOUT_SECONDS=45');
putenv('AI_CONNECT_TIMEOUT=10');
putenv('AI_FORCE_IPV4=1');
// putenv('AI_SSL_INSECURE=1'); // only if host CA store is broken
