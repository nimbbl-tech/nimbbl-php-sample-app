<?php

// Suppress deprecation notices from legacy dependencies (e.g., Requests 1.8)
error_reporting(E_ALL & ~E_DEPRECATED);

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

// Load config (required)
// - Create config.php from config.php.example (do NOT commit config.php)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    throw new \RuntimeException('config.php missing. Copy config.php.example to config.php and fill your credentials.');
}
$config = require $configPath;

// Validate required credentials
$accessKey = $config['access_key'] ?? '';
$accessSecret = $config['access_secret'] ?? '';
if (!$accessKey || !$accessSecret) {
    throw new \RuntimeException(
        "Missing credentials in config.php. Copy config.php.example to config.php and fill access_key/access_secret.\n" .
        "Do NOT commit config.php."
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

