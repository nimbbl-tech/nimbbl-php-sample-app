<?php

namespace Nimbbl\ClientWrapper;

/**
 * Minimal Checkout client wrapper for Sonic Checkout.
 *
 * Kept inside the sample app to avoid requiring an additional composer package.
 */
class CheckoutClient
{
    /**
     * Render inline JS to launch checkout directly on the page (popup mode by default).
     *
     * @param string $orderToken order token from create order
     * @param array $options optional checkout options; if callback_url is provided, redirect mode is used; otherwise callback_handler is used
     * @param array $env optional environment overrides (apiHost, checkoutHost, samunnayaEndPoint)
     */
    public function renderInlineLauncher(string $orderToken, array $options = [], array $env = []): string
    {
        $opts = $this->filterOptions($options);
        $handlerPostUrl = $opts['handler_post_url'] ?? 'handle-callback.php';
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

        // Build checkout constructor config with optional host overrides
        $checkoutConfig = array_filter([
            'token' => $orderToken,
            'apiHost' => $env['apiHost'] ?? null,
            'checkoutHost' => $env['checkoutHost'] ?? null,
            'samunnayaEndPoint' => $env['samunnayaEndPoint'] ?? null,
        ]);
        $checkoutConfigJson = json_encode($checkoutConfig, JSON_UNESCAPED_SLASHES);

        return <<<HTML
<script type="module">
import Checkout from "https://cdn.jsdelivr.net/npm/nimbbl_sonic@latest";
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

