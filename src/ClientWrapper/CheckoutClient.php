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
    Logger::getInstance()->info("=== CheckoutClient::renderInlineLauncher DEBUG ===");
    Logger::getInstance()->info("Order Token: " . $this->maskTokenForLog($orderToken));
    Logger::getInstance()->debug("Options (raw): " . $this->safeJsonForLog($options));

    $opts = $this->filterOptions($options);
    Logger::getInstance()->debug("Options (filtered): " . $this->safeJsonForLog($opts));
    // Use the same unified endpoint:
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

    Logger::getInstance()->info("Handler Post URL: " . $handlerPostUrl);
    Logger::getInstance()->debug("Checkout Config (JSON): " . $this->safeJsonStringForLog($checkoutConfigJson));
    Logger::getInstance()->debug("Options (JSON): " . $this->safeJsonStringForLog($jsonOptions));
    Logger::getInstance()->info("=== END DEBUG ===");

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

  private function maskTokenForLog(string $token): string
  {
    if ($token === '') {
      return $token;
    }
    if (strlen($token) <= 16) {
      return str_repeat('*', strlen($token));
    }
    return substr($token, 0, 8) . str_repeat('*', max(4, strlen($token) - 16)) . substr($token, -8);
  }

  private function safeJsonForLog(array $payload): string
  {
    $encoded = json_encode($payload, JSON_PRETTY_PRINT);
    return $this->safeJsonStringForLog($encoded !== false ? $encoded : '{}');
  }

  private function safeJsonStringForLog(string $json): string
  {
    // Avoid logging large callback JS and token-like values in sample app debug logs.
    if (strlen($json) > 2000) {
      return substr($json, 0, 2000) . '... [truncated]';
    }
    return $json;
  }
}

