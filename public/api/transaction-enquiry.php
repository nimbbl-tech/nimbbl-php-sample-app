<?php

require __DIR__ . '/../../bootstrap.php';

use Nimbbl\Api\Logger;
use Nimbbl\Api\SdkConstants;

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];
$transactionId = $body['transaction_id'] ?? ($body['nimbbl_transaction_id'] ?? null);
$merchantToken = $body['merchant_token'] ?? null;

// Validate input format
if ($transactionId && !preg_match('/^[a-zA-Z0-9_-]+$/', $transactionId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid transaction_id format']);
    exit;
}

if ($merchantToken && strlen($merchantToken) > 500) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid merchant_token format']);
    exit;
}

if (!$transactionId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'transaction_id is required']);
    exit;
}

if (!$merchantToken) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'merchant_token is required']);
    exit;
}

try {

    $resp = $api->transactions()->transactionEnquiry([
        'transaction_id' => $transactionId
    ], $merchantToken);

    header('Content-Type: application/json');
    echo json_encode($resp);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    Logger::getInstance()->log("Transaction enquiry error: " . $e->getMessage(), SdkConstants::LOG_ERROR, SdkConstants::COMPONENT_REQUEST);
    // Don't expose internal error details in production
    $isProduction = (getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod');
    $errorMessage = $isProduction ? 'Internal server error' : $e->getMessage();
    echo json_encode(['error' => $errorMessage]);
}

