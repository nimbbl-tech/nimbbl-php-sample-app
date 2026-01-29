<?php

namespace Nimbbl\ClientWrapper;

use Nimbbl\Api\Log\Logger;

/**
 * Thin wrapper over nimbbl-php-sdk for Sonic Checkout flows.
 */
class CheckoutClient
{
  /**
   * @var array
   */
  private $config;

  public function __construct(array $config = [])
  {
    $this->config = $config;
  }

  /**
   * Render inline JS to launch checkout directly on the page (popup mode by default).
   *
   * @param string $orderToken order token from create order
   * @param array $options optional checkout options; if callback_url is provided, redirect mode is used; otherwise handler_post_url is used to post callback to backend (default handle-callback.php)
   */
  public function renderInlineLauncher(string $orderToken, array $options = []): string
  {
    // Debug logging
    Logger::getInstance()->log("=== CheckoutClient::renderInlineLauncher DEBUG ===", Logger::LOG_INFO, 'CheckoutClient');
    Logger::getInstance()->log("Order Token: " . $orderToken, Logger::LOG_INFO, 'CheckoutClient');
    Logger::getInstance()->log("Options (raw): " . json_encode($options, JSON_PRETTY_PRINT), Logger::LOG_DEBUG, 'CheckoutClient');

    $opts = $this->filterOptions($options);
    Logger::getInstance()->log("Options (filtered): " . json_encode($opts, JSON_PRETTY_PRINT), Logger::LOG_DEBUG, 'CheckoutClient');
    // Use the same unified endpoint as the .NET sample app:
    // - POST /payment-callback.php normalizes/decrypts callback payloads
    $handlerPostUrl = $opts['handler_post_url'] ?? '/payment-callback.php';
    $customHandlerJs = $opts['callback_handler_js'] ?? null;

    // Remove handler_post_url from JS options; used only for handler posting
    unset($opts['handler_post_url'], $opts['callback_handler_js']);

    $jsonOptions = json_encode($opts, JSON_UNESCAPED_SLASHES);
    $handlerJs = $customHandlerJs ?: <<<JS
async function(response) {
  try {
    await fetch("{$handlerPostUrl}", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(response || {})
    });
  } catch (e) {
    console.error("Failed to POST callback to backend", e);
  }
  alert("Payment status: " + (response?.status || 'unknown'));
}
JS;

    // Build checkout constructor config from config values
    $checkoutConfig = array_filter([
      'token' => $orderToken,
      'apiHost' => $this->config['api_host'] ?? null,
      'checkoutHost' => $this->config['checkout_host'] ?? null,
    ]);
    $checkoutConfigJson = json_encode($checkoutConfig, JSON_UNESCAPED_SLASHES);

    Logger::getInstance()->log("Handler Post URL: " . $handlerPostUrl, Logger::LOG_INFO, 'CheckoutClient');
    Logger::getInstance()->log("Checkout Config (JSON): " . $checkoutConfigJson, Logger::LOG_DEBUG, 'CheckoutClient');
    Logger::getInstance()->log("Options (JSON): " . $jsonOptions, Logger::LOG_DEBUG, 'CheckoutClient');
    Logger::getInstance()->log("=== END DEBUG ===", Logger::LOG_INFO, 'CheckoutClient');

    return <<<HTML
<script type="module">
// Use jsDelivr ESM build; without `+esm` the package may not be importable as a module.
import Checkout from "https://cdn.jsdelivr.net/npm/nimbbl_sonic@latest/+esm";
const checkout = new Checkout({$checkoutConfigJson});
const options = {$jsonOptions} || {};
if (!options.callback_url) {
  options.callback_handler = {$handlerJs};
}
checkout.open(options);
</script>
HTML;
  }

  /**
   * Allow only known checkout options.
   *
   * @param array $options
   * @return array
   */
  private function filterOptions(array $options): array
  {
    $allowed = [
      'callback_url',
      'handler_post_url',
      'callback_handler_js',
      'payment_mode_code',
      'bank_code',
      'wallet_code',
      'payment_flow',
      'upi_id',
      'upi_app_code',
      'emi_code',
    ];
    return array_intersect_key($options, array_flip($allowed));
  }
}

