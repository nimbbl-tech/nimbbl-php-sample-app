## Nimbbl Sonic Checkout Merchant Sample (PHP)

This sample is for merchant integrations using Nimbbl Standard Checkout (Sonic).
It shows server-side order creation, Checkout launch using `order_token`, callback handling, and webhook verification.

### Merchant Setup (Recommended)

Use the published SDK package for merchant integration and UAT/production validation.

```bash
# from this repo root
cp composer.published.json composer.json
composer install

cp config.php.example config.php
# Edit config.php and fill access_key, access_secret, and api_host

php -S 0.0.0.0:8000 -t public
```

Open http://<your-local-ip>:8000 in your browser (example: `http://192.168.1.20:8000`).

Use IP/HTTPS callback URLs in your order request configuration. Avoid `localhost` callback URLs, as create-order validation may fail for non-routable callback hosts.

**Important:** This sample app uses `config.php` for credentials. Never commit `config.php`.

### Integration Flow

1) Merchant checkout action posts to `public/index.php`.
2) Server generates merchant token and creates order using `nimbbl-php-sdk`.
3) `order_token` is passed to Sonic Checkout JS.
4) Callback endpoint parses payload and verifies callback signature.
5) Webhook endpoint verifies event signature for backend state sync.

### Endpoints Used in This Sample App

Merchants can use their own routes/pages in production integrations. The endpoints below are specific to this sample application.

- `public/index.php` — create order + launch checkout.
- `public/payment-callback.php` — callback handler (popup/redirect return path).
- `public/webhook.php` — server webhook endpoint for payment/refund events.
- `public/payment-success.php` — success display page.
- `public/payment-failed.php` — failure display page.

### Security and Verification

- Callback payload parsing uses `PayloadHelperUtils::parseResponse()`.
- Callback signature verification uses `verifyCallbackSignature()`.
- Webhook signature verification uses `verifySignature()`.
- Payment status should be finalized by verified callback/webhook data, not only browser redirects.

### Go-Live Checklist (Merchant)

- Use production `access_key`, `access_secret`, and correct `api_host`.
- Expose callback and webhook URLs over HTTPS.
- Verify callback signatures and webhook signatures in all environments.
- Store and reconcile `order_id`, `transaction_id`, and status transitions.
- Handle retry/idempotency in your order update logic.

### Useful Verification Commands

```bash
composer show nimbbl/nimbbl-sdk
php -r "require 'vendor/autoload.php'; echo 'SDK Version: ' . \Nimbbl\Api\RestClient\NimbblClient::VERSION . PHP_EOL;"
```

