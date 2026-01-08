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
use Nimbbl\Api\Util;
use Nimbbl\Api\Logger;
use Nimbbl\Api\SdkConstants;

// Event type constants
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
    $util = new Util();
    
    // Validate required parameters before verification
    // REQUIRED: Webhook payload must not be empty
    if (empty($raw)) {
        throw new \Exception('Webhook payload is empty');
    }
    
    // Parse and verify using SDK helper (prefers payload signature)
    $parsed = $util->verifyAndParseWebhook($raw, $config['access_secret'] ?? '', '');
    if (!$parsed) {
        throw new \Exception('Webhook verification failed');
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
function processWebhookEvent($parsed) {
    $eventType = $parsed['event_type'] ?? '';
    $orderId = $parsed['nimbbl_order_id'] ?? '';
    $transactionId = $parsed['nimbbl_transaction_id']
        ?? ($parsed['transaction']['transaction_id'] ?? '');
    
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
function handlePaymentSuccess($orderId, $transactionId, $data) {
    Logger::getInstance()->log("Payment successful - Order: {$orderId}, Transaction: {$transactionId}", SdkConstants::LOG_INFO, SdkConstants::COMPONENT_WEBHOOK);
    
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
function handlePaymentFailed($orderId, $transactionId, $data) {
    Logger::getInstance()->log("Payment failed - Order: {$orderId}, Transaction: {$transactionId}", SdkConstants::LOG_ERROR, SdkConstants::COMPONENT_WEBHOOK);
    
    // Extract failure reason from transaction object
    $transaction = $data['transaction'] ?? [];
    $failureReason = $transaction['nimbbl_merchant_message'] 
        ?? $transaction['nimbbl_error_code'] 
        ?? $transaction['nimbbl_consumer_message']
        ?? $data['message']
        ?? 'Unknown';
    Logger::getInstance()->log("Failure reason: {$failureReason}", SdkConstants::LOG_ERROR, SdkConstants::COMPONENT_WEBHOOK);
    
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
function handlePaymentReversing($orderId, $transactionId, $data) {
    Logger::getInstance()->log("Payment reversing - Order: {$orderId}, Transaction: {$transactionId}", SdkConstants::LOG_WARNING, SdkConstants::COMPONENT_WEBHOOK);
    // TODO: Implement your business logic
}

/**
 * Handle payment reversal failed event
 * TODO: Implement your business logic here
 */
function handlePaymentReversalFailed($orderId, $transactionId, $data) {
    Logger::getInstance()->log("Payment reversal failed - Order: {$orderId}, Transaction: {$transactionId}", SdkConstants::LOG_ERROR, SdkConstants::COMPONENT_WEBHOOK);
    // TODO: Implement your business logic
}

/**
 * Handle payment reversed event
 * TODO: Implement your business logic here
 */
function handlePaymentReversed($orderId, $transactionId, $data) {
    Logger::getInstance()->log("Payment reversed - Order: {$orderId}, Transaction: {$transactionId}", SdkConstants::LOG_INFO, SdkConstants::COMPONENT_WEBHOOK);
    // TODO: Implement your business logic
}

/**
 * Handle refund success event
 * TODO: Implement your business logic here
 */
function handleRefundSuccess($orderId, $transactionId, $data) {
    $transaction = $data['transaction'] ?? [];
    $refundId = $data['nimbbl_refund_id'] ?? ($data['refund_transaction_id'] ?? ($transaction['transaction_id'] ?? ''));
    $refundAmount = $transaction['refund_amount'] ?? ($data['refund_amount'] ?? 0);
    Logger::getInstance()->log("Refund successful - Order: {$orderId}, Refund: {$refundId}, Amount: {$refundAmount}", SdkConstants::LOG_INFO, SdkConstants::COMPONENT_WEBHOOK);
    // TODO: Implement your business logic
}

/**
 * Handle refund failed event
 * TODO: Implement your business logic here
 */
function handleRefundFailed($orderId, $transactionId, $data) {
    $transaction = $data['transaction'] ?? [];
    $txnId = $transactionId ?: ($data['refund_transaction_id'] ?? ($transaction['transaction_id'] ?? ''));
    Logger::getInstance()->log("Refund failed - Order: {$orderId}, Transaction: {$txnId}", SdkConstants::LOG_ERROR, SdkConstants::COMPONENT_WEBHOOK);
    // TODO: Implement your business logic
}

/**
 * Handle refund pending event
 * TODO: Implement your business logic here
 */
function handleRefundPending($orderId, $transactionId, $data) {
    $transaction = $data['transaction'] ?? [];
    $txnId = $transactionId ?: ($data['refund_transaction_id'] ?? ($transaction['transaction_id'] ?? ''));
    Logger::getInstance()->log("Refund pending - Order: {$orderId}, Transaction: {$txnId}", SdkConstants::LOG_WARNING, SdkConstants::COMPONENT_WEBHOOK);
    // TODO: Implement your business logic
}

