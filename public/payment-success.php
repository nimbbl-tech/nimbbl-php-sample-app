<?php
/**
 * Payment Success Page
 * 
 * Displayed after a successful payment
 */

require __DIR__ . '/../bootstrap.php';
use Nimbbl\Api\Common\PayloadHelperUtils;
use Nimbbl\Api\Common\JsonKeys;
use Nimbbl\Api\Log\Logger;

// Initialize variables
$orderId = '';
$transactionId = '';
$message = 'Payment successful!';
$amount = null;
$currency = '';
$paymentMode = '';
$userName = '';
$parsedResponse = null;

// Handle response parameter from popup mode (base64 encoded or JSON)
$responseParam = $_GET['response'] ?? '';
if ($responseParam) {
  try {
    $accessSecret = $config['access_secret'] ?? '';
    // Use PayloadHelperUtils::parseResponse() to handle base64, JSON, encryption, and unwrapping
    $parsedResponse = PayloadHelperUtils::parseResponse($responseParam, $accessSecret);

    // Extract payment details - prioritize transaction.status
    $status = $parsedResponse[JsonKeys::TRANSACTION][JsonKeys::STATUS] ?? $parsedResponse[JsonKeys::STATUS] ?? null;
    $orderId = $parsedResponse[JsonKeys::NIMBBL_ORDER_ID] ?? $parsedResponse[JsonKeys::ORDER_ID] ?? '';
    // Extract transaction_id only from transaction object
    $transactionId = $parsedResponse[JsonKeys::TRANSACTION][JsonKeys::TRANSACTION_ID] ?? null;
    $message = $parsedResponse[JsonKeys::MESSAGE] ?? 'Payment successful!';

    // Extract order details
    $order = $parsedResponse[JsonKeys::ORDER] ?? [];
    $amount = $order['grand_total'] ?? null;
    $currency = $order[JsonKeys::CURRENCY] ?? '';

    // Extract transaction details
    $transaction = $parsedResponse[JsonKeys::TRANSACTION] ?? [];
    $paymentMode = $transaction['payment_mode'] ?? '';

    // Extract user details
    $user = $parsedResponse[JsonKeys::USER] ?? [];
    $userName = $user['name'] ?? '';
  } catch (\Exception $e) {
    Logger::getInstance()->warning("Payment success page error: " . $e->getMessage());
    // Fall back to URL parameters
    $orderId = $_GET['order_id'] ?? '';
    $transactionId = $_GET['transaction_id'] ?? '';
    $message = $_GET['message'] ?? 'Payment successful!';
  }
} else {
  // Fall back to URL parameters (for redirect mode)
  $orderId = $_GET['order_id'] ?? '';
  $transactionId = $_GET['transaction_id'] ?? '';
  $message = $_GET['message'] ?? 'Payment successful!';
}

// Format amount
$formattedAmount = '';
if ($amount !== null && $currency) {
  $formattedAmount = number_format((float) $amount, 2, '.', ',');
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payment Success - Nimbbl</title>
  <style>
    @font-face {
      font-family: "Gordita";
      src: url("/assets/fonts/Gordita-Regular.otf") format("opentype"),
        url("/assets/fonts/Gordita-Regular.ttf") format("truetype"),
        url("/assets/fonts/Gordita-Regular.woff") format("woff"),
        url("/assets/fonts/Gordita-Regular.woff2") format("woff2");
      font-display: swap;
    }

    @font-face {
      font-family: "Gordita-Bold";
      src: url("/assets/fonts/Gordita-Bold.otf") format("opentype"),
        url("/assets/fonts/Gordita-Bold.ttf") format("truetype"),
        url("/assets/fonts/Gordita-Bold.woff") format("woff");
      font-display: swap;
    }

    @font-face {
      font-family: "Gordita-Medium";
      src: url("/assets/fonts/Gordita-Medium.otf") format("opentype"),
        url("/assets/fonts/Gordita-Medium.woff") format("woff");
      font-display: swap;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: "Gordita", "Inter", -apple-system, sans-serif;
      background: #ECF0FD;
      color: #101010;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .container {
      min-width: 300px;
      max-width: 500px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .success-header {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
      border-radius: 8px 8px 0 0;
      gap: 8px;
      background: #04550D;
      color: #BDF2CA;
    }

    .success-header img {
      width: 48px;
      height: 48px;
    }

    .success-header h1 {
      font-family: "Gordita-Medium";
      font-size: 24px;
      text-align: center;
      margin: 0;
    }

    .success-header .amount {
      font-size: 18px;
      margin-top: 4px;
    }

    .zigzag-box {
      text-align: center;
      clip-path: polygon(0% 0%,
          100% 0%,
          100% 95%,
          95% 100%,
          90% 95%,
          85% 100%,
          80% 95%,
          75% 100%,
          70% 95%,
          65% 100%,
          60% 95%,
          55% 100%,
          50% 95%,
          45% 100%,
          40% 95%,
          35% 100%,
          30% 95%,
          25% 100%,
          20% 95%,
          15% 100%,
          10% 95%,
          5% 100%,
          0% 95%);
      background: white;
      padding: 16px 8px 40px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .transaction-details {
      width: 100%;
      background: #FAFAFC;
      border: 1px solid #ECF0FD;
      border-radius: 12px;
      padding: 16px;
      margin: 8px 8px 16px;
    }

    .transaction-details-title {
      font-family: "Gordita-Medium";
      font-size: 14px;
      margin-bottom: 8px;
      color: #101010;
    }

    .transaction-details-content {
      display: flex;
      flex-direction: column;
      gap: 8px;
      padding-top: 8px;
      border-top: 1px solid #ECF0FD;
      font-size: 12px;
    }

    .detail-item {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 4px;
    }

    .detail-label {
      color: #6C7F9A;
      font-size: 12px;
    }

    .detail-value {
      font-family: "Gordita-Medium";
      color: #101010;
      font-size: 12px;
    }

    .redirect-button {
      width: calc(100% - 16px);
      background: #000;
      color: #fff;
      padding: 8px;
      border-radius: 6px;
      border: none;
      font-family: "Gordita-Medium";
      font-size: 14px;
      cursor: pointer;
      margin-bottom: 8px;
      text-decoration: none;
      display: block;
      text-align: center;
    }

    .redirect-button:hover {
      background: #333;
    }

    .redirect-timer {
      font-family: "Gordita-Medium";
      font-size: 14px;
      color: #101010;
    }

    @media (max-width: 768px) {
      .container {
        min-width: 280px;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="success-header">
      <img src="/assets/img/SuccessIcon.svg" alt="Success" />
      <h1>Payment Successful</h1>
      <?php if ($formattedAmount && $currency): ?>
        <p class="amount">for <?php echo htmlspecialchars($currency); ?>   <?php echo htmlspecialchars($formattedAmount); ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="zigzag-box">
      <?php if ($transactionId || $paymentMode || $userName): ?>
        <div class="transaction-details">
          <p class="transaction-details-title">Transaction Details</p>
          <div class="transaction-details-content">
            <?php if ($transactionId): ?>
              <div class="detail-item">
                <span class="detail-label">Transaction ID</span>
                <span class="detail-value"><?php echo htmlspecialchars($transactionId); ?></span>
              </div>
            <?php endif; ?>
            <?php if ($paymentMode): ?>
              <div class="detail-item">
                <span class="detail-label">Mode of Payment</span>
                <span class="detail-value"><?php echo htmlspecialchars($paymentMode); ?></span>
              </div>
            <?php endif; ?>
            <?php if ($userName): ?>
              <div class="detail-item">
                <span class="detail-label">Name of the Sender</span>
                <span class="detail-value"><?php echo htmlspecialchars($userName); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <a href="/index.php" class="redirect-button" id="redirectButton">Go to Merchant Sample App</a>
      <p class="redirect-timer" id="redirectTimer">Redirecting to merchant sample app in..<span id="counter">5</span>
      </p>
    </div>
  </div>

  <script>
    let counter = 5;
    const counterElement = document.getElementById('counter');
    const timerElement = document.getElementById('redirectTimer');
    const redirectButton = document.getElementById('redirectButton');

    const timer = setInterval(() => {
      counter--;
      if (counterElement) {
        counterElement.textContent = counter;
      }
      if (counter <= 0) {
        clearInterval(timer);
        window.location.href = '/index.php';
      }
    }, 1000);

    if (redirectButton) {
      redirectButton.addEventListener('click', () => {
        clearInterval(timer);
        window.location.href = '/index.php';
      });
    }
  </script>
</body>

</html>