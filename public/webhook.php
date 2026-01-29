<?php
/**
 * Nimbbl Webhook Handler
 * 
 * This endpoint receives and processes webhook events from Nimbbl.
 * 
 * NOTE: Per Nimbbl documentation, webhook signature verification is done using the signature
 * in the payload (transaction.signature or nimbbl_signature), not from a header.
 * The signature is verified using the payment signature verification method (v3 format).
 * 
 * Documentation: https://nimbbl.biz/docs/standard-checkout/completing-integration/keeping-system-updated/
 * 
 * @throws \Exception If signature verification fails
 */

require __DIR__ . '/../bootstrap.php';
use Nimbbl\Api\Common\PayloadHelperUtils;
use Nimbbl\Api\Common\SignatureVerifier;
use Nimbbl\Api\Common\JsonKeys;
use Nimbbl\Api\Log\Logger;
use Nimbbl\Api\Common\SdkConstants;

// Event type constants
// ... (rest of defines)
define('EVENT_PAYMENT_SUCCESS', 'payment_success');
define('EVENT_PAYMENT_FAILED', 'payment_failed');
define('EVENT_PAYMENT_REVERSING', 'payment_reversing');
define('EVENT_PAYMENT_REVERSAL_FAILED', 'payment_reversal_failed');
define('EVENT_PAYMENT_REVERSED', 'payment_reversed');
define('EVENT_REFUND_SUCCESS', 'refund_success');
define('EVENT_REFUND_FAILED', 'refund_failed');
define('EVENT_REFUND_PENDING', 'refund_pending');

/**
 * (Webhook file logging removed)
 */

try {
    $raw = file_get_contents('php://input');
    $accessSecret = $config['access_secret'] ?? '';

    // Validate required parameters before verification
    // REQUIRED: Webhook payload must not be empty
    if (empty($raw)) {
        throw new \Exception('Webhook payload is empty');
    }

    // Parse and unwrap the payload using PayloadHelperUtils (handles encryption, unwrapping, etc.)
    $parsed = PayloadHelperUtils::parse($raw, $accessSecret);

    // Verify webhook signature
    $verifier = new SignatureVerifier();
    $result = $verifier->verifySignature($parsed, $accessSecret);

    if (!$result['success']) {
        throw new \Exception('Webhook signature verification failed: ' . ($result['message'] ?? 'Unknown error'));
    }

    // Process the webhook event based on event type
    processWebhookEvent($parsed);

    // Always return 200 OK (required within 15 seconds)
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'received' => true,
        'event_type' => $parsed['event_type'] ?? 'unknown',
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Process webhook event based on event type
 * 
 * @param array $parsed Parsed webhook data
 */
function processWebhookEvent($parsed)
{
    $eventType = $parsed[JsonKeys::EVENT_TYPE] ?? '';
    $orderId = $parsed[JsonKeys::NIMBBL_ORDER_ID] ?? $parsed[JsonKeys::ORDER_ID] ?? '';
    // Extract transaction_id only from transaction object
    $transactionId = $parsed[JsonKeys::TRANSACTION][JsonKeys::TRANSACTION_ID] ?? null;

    switch ($eventType) {
        case EVENT_PAYMENT_SUCCESS:
            handlePaymentSuccess($orderId, $transactionId, $parsed);
            break;

        case EVENT_PAYMENT_FAILED:
            handlePaymentFailed($orderId, $transactionId, $parsed);
            break;

        case EVENT_PAYMENT_REVERSING:
            handlePaymentReversing($orderId, $transactionId, $parsed);
            break;

        case EVENT_PAYMENT_REVERSAL_FAILED:
            handlePaymentReversalFailed($orderId, $transactionId, $parsed);
            break;

        case EVENT_PAYMENT_REVERSED:
            handlePaymentReversed($orderId, $transactionId, $parsed);
            break;

        case EVENT_REFUND_SUCCESS:
            handleRefundSuccess($orderId, $transactionId, $parsed);
            break;

        case EVENT_REFUND_FAILED:
            handleRefundFailed($orderId, $transactionId, $parsed);
            break;

        case EVENT_REFUND_PENDING:
            handleRefundPending($orderId, $transactionId, $parsed);
            break;

        default:
            // Unknown event type: ignore
            break;
    }
}

/**
 * Handle payment success event
 * TODO: Implement your business logic here
 */
function handlePaymentSuccess($orderId, $transactionId, $data)
{
    Logger::getInstance()->info("Payment successful - Order: {$orderId}, Transaction: {$transactionId}");

    // TODO: Add your business logic here
    // Examples:
    // - Update order status in database to 'paid'
    // - Send confirmation email to customer
    // - Trigger order fulfillment
    // - Update inventory
    // - Send notification to admin
}

/**
 * Handle payment failed event
 * TODO: Implement your business logic here
 */
function handlePaymentFailed($orderId, $transactionId, $data)
{
    Logger::getInstance()->error("Payment failed - Order: {$orderId}, Transaction: {$transactionId}");

    // Extract failure reason from transaction object
    $transaction = $data['transaction'] ?? [];
    $failureReason = $transaction['nimbbl_merchant_message']
        ?? $transaction['nimbbl_error_code']
        ?? $transaction['nimbbl_consumer_message']
        ?? $data['message']
        ?? 'Unknown';
    Logger::getInstance()->error("Failure reason: {$failureReason}");

    // TODO: Add your business logic here
    // Examples:
    // - Update order status to 'payment_failed'
    // - Send notification to customer
    // - Log failure reason
}

/**
 * Handle payment reversing event
 * TODO: Implement your business logic here
 */
function handlePaymentReversing($orderId, $transactionId, $data)
{
    Logger::getInstance()->warning("Payment reversing - Order: {$orderId}, Transaction: {$transactionId}");
    // TODO: Implement your business logic
}

/**
 * Handle payment reversal failed event
 * TODO: Implement your business logic here
 */
function handlePaymentReversalFailed($orderId, $transactionId, $data)
{
    Logger::getInstance()->error("Payment reversal failed - Order: {$orderId}, Transaction: {$transactionId}");
    // TODO: Implement your business logic
}

/**
 * Handle payment reversed event
 * TODO: Implement your business logic here
 */
function handlePaymentReversed($orderId, $transactionId, $data)
{
    Logger::getInstance()->info("Payment reversed - Order: {$orderId}, Transaction: {$transactionId}");
    // TODO: Implement your business logic
}

/**
 * Handle refund success event
 * TODO: Implement your business logic here
 */
function handleRefundSuccess($orderId, $transactionId, $data)
{
    $transaction = $data['transaction'] ?? [];
    $refundId = $data['nimbbl_refund_id'] ?? ($data['refund_transaction_id'] ?? ($transaction['transaction_id'] ?? ''));
    $refundAmount = $transaction['refund_amount'] ?? ($data['refund_amount'] ?? 0);
    Logger::getInstance()->info("Refund successful - Order: {$orderId}, Refund: {$refundId}, Amount: {$refundAmount}");
    // TODO: Implement your business logic
}

/**
 * Handle refund failed event
 * TODO: Implement your business logic here
 */
function handleRefundFailed($orderId, $transactionId, $data)
{
    $transaction = $data['transaction'] ?? [];
    $txnId = $transactionId ?: ($data['refund_transaction_id'] ?? ($transaction['transaction_id'] ?? ''));
    Logger::getInstance()->error("Refund failed - Order: {$orderId}, Transaction: {$txnId}");
    // TODO: Implement your business logic
}

/**
 * Handle refund pending event
 * TODO: Implement your business logic here
 */
function handleRefundPending($orderId, $transactionId, $data)
{
    $transaction = $data['transaction'] ?? [];
    $txnId = $transactionId ?: ($data['refund_transaction_id'] ?? ($transaction['transaction_id'] ?? ''));
    Logger::getInstance()->warning("Refund pending - Order: {$orderId}, Transaction: {$txnId}");
    // TODO: Implement your business logic
}

