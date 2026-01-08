<?php

// Suppress deprecation notices from legacy dependencies (e.g., Requests 1.8)
error_reporting(E_ALL & ~E_DEPRECATED);

// Load .env file if it exists (simple loader without external dependencies)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            // Only set if not already set as environment variable
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Prefer local vendor autoload; fallback to repo root vendor
$localAutoload = __DIR__ . '/vendor/autoload.php';
$rootAutoload  = __DIR__ . '/../vendor/autoload.php';
if (file_exists($localAutoload)) {
    require $localAutoload;
} elseif (file_exists($rootAutoload)) {
    require $rootAutoload;
} else {
    throw new \RuntimeException('vendor/autoload.php not found. Run `composer install` in this repository root.');
}

// Load config
// - Recommended: environment variables (or .env file loaded above)
// - Optional: config.php for local development (do NOT commit it)
$config = [];
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
    $config = [
        'access_key' => getenv('NIMBBL_ACCESS_KEY') ?: '',
        'access_secret' => getenv('NIMBBL_ACCESS_SECRET') ?: '',
        'api_url' => getenv('NIMBBL_API_URL') ?: 'https://api.nimbbl.tech/api/',
        'api_version' => getenv('NIMBBL_API_VERSION') ?: 'v3',
        'api_host' => getenv('NIMBBL_API_HOST') ?: null,
        'checkout_host' => getenv('NIMBBL_CHECKOUT_HOST') ?: null,
        'samunnaya_endpoint' => getenv('NIMBBL_SAMUNNAYA_ENDPOINT') ?: null,
        'enable_logging' => getenv('NIMBBL_ENABLE_LOGGING') !== false
            ? filter_var(getenv('NIMBBL_ENABLE_LOGGING') ?: 'true', FILTER_VALIDATE_BOOLEAN)
            : true,
        'log_file' => getenv('NIMBBL_LOG_FILE') ?: 'php://stderr',
        'debug_logging' => filter_var(getenv('NIMBBL_DEBUG_LOGGING') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ];
}

// Validate required credentials
$accessKey = $config['access_key'] ?? '';
$accessSecret = $config['access_secret'] ?? '';
if (!$accessKey || !$accessSecret) {
    throw new \RuntimeException(
        "Missing credentials. Set NIMBBL_ACCESS_KEY and NIMBBL_ACCESS_SECRET (recommended via .env),\n" .
        "or create config.php from config.php.example (do NOT commit config.php)."
    );
}

// Determine logging sink: enable/disable via config flag
$enableLogging = $config['enable_logging'] ?? true;
if ($enableLogging) {
    \Nimbbl\Api\Logger::enableLogging();
} else {
    \Nimbbl\Api\Logger::disableLogging();
}
$logFile = $enableLogging ? ($config['log_file'] ?? 'php://stderr') : 'php://memory';

// Core API client for S2S (token/order/enquiry)
$api = new \Nimbbl\Api\Api(
    $accessKey,
    $accessSecret,
    $config['api_url'] ?? null,
    $config['api_version'] ?? null,
    null,
    $logFile
);

// Checkout launcher helper
$checkoutLauncher = new \Nimbbl\ClientWrapper\CheckoutClient($config);

// Debug logging: read only from config flag
$debugFlagConfig = $config['debug_logging'] ?? null;
$debugEnabled = $debugFlagConfig !== null
    ? filter_var($debugFlagConfig, FILTER_VALIDATE_BOOLEAN)
    : false;

if ($debugEnabled) {
    \Nimbbl\Api\Logger::enableDebugLogging();
} else {
    \Nimbbl\Api\Logger::disableDebugLogging();
}

