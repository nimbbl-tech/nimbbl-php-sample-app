<?php
/**
 * Payment Callback Handler
 * 
 * This file handles:
 * - Redirect mode callback (GET ?response=base64)
 * - Popup mode callback helper (POST JSON) used to decrypt/parse callback payloads
 */

require __DIR__ . '/../bootstrap.php';
use Nimbbl\Api\Common\PayloadHelperUtils;
use Nimbbl\Api\Common\SignatureVerifier;
use Nimbbl\Api\Common\JsonKeys;
use Nimbbl\Api\Log\Logger;
use Nimbbl\Api\Common\SdkConstants;

/**
 * Parse the "response" param using PayloadHelperUtils::parseResponse()
 * Handles base64, JSON, encryption, and unwrapping automatically
 */
function parseNimbblResponseParam(string $responseParam, string $secret): array
{
    try {
        // PayloadHelperUtils::parseResponse() automatically handles:
        // - Base64 decoding
        // - JSON parsing
        // - Encryption decryption
        // - globalHandleCheckoutResponse unwrapping
        return PayloadHelperUtils::parseResponse($responseParam, $secret);
    } catch (\Exception $e) {
        throw new \RuntimeException('Invalid response format: ' . $e->getMessage());
    }
}

/**
 * Shared redirect routing for both GET and non-JSON POST callbacks.
 */
function handleRedirectCallback(array $config, ?string $responseParam, ?string $encryptedResponseParam): void
{
    $responseParam = is_string($responseParam) ? $responseParam : '';
    $encryptedResponseParam = is_string($encryptedResponseParam) ? $encryptedResponseParam : '';

    if ($responseParam === '' && $encryptedResponseParam === '') {
        header('Location: /index.php');
        exit;
    }

    try {
        $accessSecret = $config['access_secret'] ?? '';
        
        // Build parsed response structure using PayloadHelperUtils
        if ($responseParam !== '') {
            $parsedResponse = parseNimbblResponseParam($responseParam, $accessSecret);
        } elseif ($encryptedResponseParam !== '') {
            // Sometimes integrations send encrypted_response directly
            $parsedResponse = PayloadHelperUtils::parseResponse($encryptedResponseParam, $accessSecret);
        } else {
            throw new \RuntimeException('No response or encrypted_response parameter provided');
        }

        // Extract payment status - prioritize transaction.status
        $status = null;
        if (isset($parsedResponse[JsonKeys::TRANSACTION][JsonKeys::STATUS]) && is_string($parsedResponse[JsonKeys::TRANSACTION][JsonKeys::STATUS])) {
            $status = $parsedResponse[JsonKeys::TRANSACTION][JsonKeys::STATUS];
        } elseif (isset($parsedResponse[JsonKeys::STATUS]) && is_string($parsedResponse[JsonKeys::STATUS])) {
            $status = $parsedResponse[JsonKeys::STATUS];
        }
        
        if (!is_string($status) || $status === '') {
            $status = 'failed';
        }

        $orderId = $parsedResponse[JsonKeys::NIMBBL_ORDER_ID] ?? $parsedResponse[JsonKeys::ORDER_ID] ?? '';
        // Extract transaction_id only from transaction object
        $transactionId = $parsedResponse[JsonKeys::TRANSACTION][JsonKeys::TRANSACTION_ID] ?? null;
        $message = $parsedResponse[JsonKeys::MESSAGE] ?? '';

        // Verify signature using verifyCallbackSignature
        $signatureValid = false;
        $verifier = new SignatureVerifier();
        $res = $verifier->verifyCallbackSignature($parsedResponse, $accessSecret);
        $signatureValid = $res['success'];

        // Check for success statuses (both "success" and "succeeded" indicate success)
        $isSuccess = ($status === 'success' || $status === 'succeeded' || $status === 'completed');
        $params = http_build_query([
            'response' => $responseParam,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'message' => $message,
        ]);

        if ($isSuccess) {
            header('Location: /payment-success.php?' . $params);
        } else {
            $params = http_build_query([
                'response' => $responseParam,
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
                'message' => $message,
                'status' => $status,
            ]);
            header('Location: /payment-failed.php?' . $params);
        }
        exit;
    } catch (\Throwable $e) {
        Logger::getInstance()->error("Payment callback error: " . $e->getMessage());
        header('Location: /payment-failed.php?error=1');
        exit;
    }
}

// Unified endpoint:
// - GET: redirect callback handler
// - POST: decrypt/parse helper for popup callback JS
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
    // If this is a JSON POST from our popup callback handler, respond with JSON.
    // Otherwise, treat it as a redirect callback (some integrations POST form-encoded).
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $looksLikeJsonPopupHelper =
        is_array($body) &&
        (str_contains($contentType, 'application/json') || isset($body['callback']) || isset($body['encrypted_response']));

    if (!$looksLikeJsonPopupHelper) {
        // Redirect callback via POST (form submit) OR unknown POST: try to route like GET.
        handleRedirectCallback($config, $_POST['response'] ?? $_GET['response'] ?? null, $_POST['encrypted_response'] ?? $_GET['encrypted_response'] ?? null);
        exit;
    }

    header('Content-Type: application/json');

    try {
        Logger::getInstance()->info("PaymentCallback(POST) received");
        Logger::getInstance()->debug("PaymentCallback(POST) raw payload: " . $raw);

        // Use PayloadHelperUtils::parseResponse() to handle the raw payload
        // This automatically handles base64, JSON, encryption, and unwrapping
        $accessSecret = $config['access_secret'] ?? '';
        $parsed = PayloadHelperUtils::parseResponse($raw, $accessSecret);

        $result = [
            'received' => true,
            'parsed' => $parsed,
        ];

        // Event type (optional; useful to avoid double redirects on close events client-side)
        $eventType = $parsed[JsonKeys::EVENT_TYPE] ?? null;
        $result['event_type'] = $eventType;

        // Verify signature using verifyCallbackSignature
        $verifier = new SignatureVerifier();
        $verifyResult = $verifier->verifyCallbackSignature($parsed, $accessSecret);
        $signatureValid = $verifyResult['success'];

        $result['signature_valid'] = $signatureValid;
        $result['signature_message'] = $signatureValid ? 'Signature valid' : 'Signature invalid';

        // Log signature verification result
        Logger::getInstance()->info("PaymentCallback(POST) signature verification: " . ($signatureValid ? "valid" : "invalid") . " [event_type=" . ($eventType ?? 'N/A') . "]");

        echo json_encode($result);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        Logger::getInstance()->error("Payment callback POST error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to process popup callback.']);
        exit;
    }
}

// GET redirect flow (and fallback for non-JSON POSTs handled above)
handleRedirectCallback($config, $_GET['response'] ?? null, $_GET['encrypted_response'] ?? null);






