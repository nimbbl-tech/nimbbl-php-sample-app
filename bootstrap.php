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

// Local wrapper: provide CheckoutClient within this repo so the sample app doesn't depend on extra packages
if (!class_exists('\\Nimbbl\\ClientWrapper\\CheckoutClient')) {
    $localCheckoutClient = __DIR__ . '/src/ClientWrapper/CheckoutClient.php';
    if (file_exists($localCheckoutClient)) {
        require_once $localCheckoutClient;
    }
}

// Validate required credentials
$accessKey = $config['access_key'] ?? '';
$accessSecret = $config['access_secret'] ?? '';
if (!$accessKey || !$accessSecret) {
    throw new \RuntimeException(
        "Missing credentials in config.php. Copy config.php.example to config.php and fill access_key/access_secret.\n" .
        "Do NOT commit config.php."
    );
}

// Logging
// NOTE: The SDK Logger in nimbbl/nimbbl-sdk writes to a file path (it mkdir's dirname()).
// So avoid stream targets like php://stderr; use a real file path (or /dev/null to disable).
$enableLogging = (bool)($config['enable_logging'] ?? true);
$defaultLogFile = __DIR__ . '/storage/nimbbl_debug.log';
$logFile = $enableLogging
    ? ($config['log_file'] ?? $defaultLogFile)
    : '/dev/null';

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

// debug_logging is currently a no-op here; SDK logger doesn't expose enable/disable toggles.

