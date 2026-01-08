<?php

require __DIR__ . '/../../bootstrap.php';
use Nimbbl\Api\Encryption;
use Nimbbl\Api\Util;
use Nimbbl\Api\Logger;
use Nimbbl\Api\SdkConstants;

// Read raw callback payload from popup handler
$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$result = [
    'received' => true,
    'raw' => $body,
];

try {
    // Decrypt encrypted_response if present
    if (isset($body['encrypted_response'])) {
        $enc = new Encryption($config['access_secret'] ?? '');
        $decrypted = $enc->decrypt($body['encrypted_response'], true);
        $result['decrypted'] = $decrypted;
        
        // Also parse and include the parsed payload for easier access
        $parsed = json_decode($decrypted, true);
        if ($parsed) {
            $result['parsed'] = $parsed;
        }
    } else {
        // If no encrypted_response, the response might already be in the correct format
        // Check if it has payload structure
        if (isset($body['payload'])) {
            $result['parsed'] = $body;
        } elseif (isset($body['status'])) {
            // Wrap in payload structure to match React app
            $result['parsed'] = ['payload' => $body];
        } else {
            $result['parsed'] = $body;
        }
    }

    // Verify signature if attributes present
    if (isset($body['attributes']) && is_array($body['attributes'])) {
        // Optional: pass amount if available
        $amount = isset($body['amount']) ? (int) $body['amount'] : null;
        $util = new Util();
        $verificationResult = $util->verifySignature(
            $body['attributes'],
            $amount,
            $config['access_secret'] ?? null
        );
        if (isset($verificationResult['success']) && $verificationResult['success'] === true) {
            $result['signature_valid'] = true;
            $result['signature_message'] = $verificationResult['message'] ?? '';
        } else {
            $result['signature_valid'] = false;
            if (isset($verificationResult['error'])) {
                $result['signature_error'] = $verificationResult['error'];
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($result);
} catch (\Throwable $e) {
    http_response_code(500);
    Logger::getInstance()->log("Checkout response error: " . $e->getMessage(), SdkConstants::LOG_ERROR, SdkConstants::COMPONENT_REQUEST);
    // Don't expose internal error details in production
    $isProduction = (getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod');
    $errorMessage = $isProduction ? 'Internal server error' : $e->getMessage();
    header('Content-Type: application/json');
    echo json_encode(['error' => $errorMessage]);
}

