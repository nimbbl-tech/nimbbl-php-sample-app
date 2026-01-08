<?php
/**
 * Payment Callback Handler
 * 
 * This file handles the redirect callback from Nimbbl checkout
 * and routes to appropriate success/failed pages based on payment status.
 */

require __DIR__ . '/../bootstrap.php';
use Nimbbl\Api\Encryption;
use Nimbbl\Api\Util;
use Nimbbl\Api\Logger;
use Nimbbl\Api\SdkConstants;

// Get response parameter from URL
$responseParam = $_GET['response'] ?? '';

if (empty($responseParam)) {
    // No response parameter, redirect to home
    header('Location: /index.php');
    exit;
}

try {
    // Decode base64 response
    $decodedResponse = base64_decode($responseParam);
    $parsedResponse = json_decode($decodedResponse, true);
    
    if (!$parsedResponse) {
        throw new \Exception('Invalid response format');
    }
    
    // Decrypt encrypted_response if present
    if (isset($parsedResponse['encrypted_response'])) {
        $enc = new Encryption($config['access_secret'] ?? '');
        $decrypted = $enc->decrypt($parsedResponse['encrypted_response'], true);
        $parsedResponse['payload'] = json_decode($decrypted, true);
    }
    
    // Extract payment status
    $payload = $parsedResponse['payload'] ?? $parsedResponse;
    $status = $payload['status'] ?? $parsedResponse['status'] ?? 'failed';
    $orderId = $payload['nimbbl_order_id'] ?? $payload['order_id'] ?? '';
    $transactionId = $payload['nimbbl_transaction_id'] ?? $payload['transaction_id'] ?? '';
    $message = $payload['message'] ?? '';
    
    // Verify signature if attributes present
    $signatureValid = false;
    if (isset($parsedResponse['attributes']) && is_array($parsedResponse['attributes'])) {
        $amount = isset($parsedResponse['amount']) ? (int) $parsedResponse['amount'] : null;
        $util = new Util();
        $verificationResult = $util->verifySignature(
            $parsedResponse['attributes'],
            $amount,
            $config['access_secret'] ?? null
        );
        $signatureValid = is_array($verificationResult) && isset($verificationResult['success']) && $verificationResult['success'] === true;
    }
    
    // Route based on status - only success/succeeded go to success page, everything else goes to failed
    if ($status === 'success' || $status === 'succeeded') {
        // Redirect to success page with data
        $params = http_build_query([
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'message' => $message,
            'signature_valid' => $signatureValid ? '1' : '0',
        ]);
        header('Location: /payment-success.php?' . $params);
        exit;
    } else {
        // Redirect to failed page with data (including unknown status)
        $params = http_build_query([
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'message' => $message,
            'status' => $status,
        ]);
        header('Location: /payment-failed.php?' . $params);
        exit;
    }
    
} catch (\Throwable $e) {
    // On error, redirect to failed page
    $isProduction = (getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod');
    Logger::getInstance()->log("Payment callback error: " . $e->getMessage(), SdkConstants::LOG_ERROR, SdkConstants::COMPONENT_REQUEST);
    // Don't expose error details in URL - use generic error flag
    header('Location: /payment-failed.php?error=1');
    exit;
}






