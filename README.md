## Nimbbl Sonic Checkout Merchant Sample (PHP)

PHP-only demo (sonicshop-like) that creates orders via S2S using `nimbbl-php-sdk` and launches Sonic Checkout with the returned `order_token`.

### Setup
```bash
# from this repo
composer install

cp config.php.example config.php
# Edit config.php and fill in access_key, access_secret (and optionally api_host)

php -S localhost:8000 -t public
```
Open http://localhost:8000 in the browser.
This sample app uses `.php` endpoints like `/payment-callback.php` so it works with the plain built-in server command above.

**Note:** This sample app is **config.php-only**. Do NOT commit `config.php`.

### Flow
1) Form POST hits `public/index.php` (server-side PHP).
2) Server uses `nimbbl-php-sdk` to generate merchant token and create order.
3) Returned `order_token` is passed into the Sonic Checkout JS per Standard Checkout web integration.
4) Callback handler shows the response; for production, send it to your server and verify.

### Files
- `public/index.php` – server-side order creation + Sonic Checkout launch (no extra API endpoints).
- `bootstrap.php` – loads config and initializes `\Nimbbl\Api\RestClient\NimbblClient`.
- `public/payment-callback.php` – payment callback handler for popup/redirect modes. Uses `PayloadHelperUtils::parseResponse()` and `verifyCallbackSignature()`.
- `public/webhook.php` – webhook handler endpoint for receiving payment events. Uses `PayloadHelperUtils::parse()` and `verifySignature()`.
- `public/payment-success.php` – success page that displays payment details.
- `public/payment-failed.php` – failure page that displays payment error details.

### Key Features
- **Automatic Payload Parsing**: Uses `PayloadHelperUtils::parseResponse()` to automatically handle base64, JSON, encryption, and unwrapping
- **Signature Verification**: Uses `verifyCallbackSignature()` for payment callbacks and `verifySignature()` for webhooks
- **Transaction ID Extraction**: Extracts `transaction_id` only from the `transaction` object (no fallbacks)
- **Status Priority**: Prioritizes `transaction.status` as the authoritative payment status

