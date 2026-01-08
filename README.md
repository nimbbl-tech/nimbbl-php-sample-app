## Nimbbl Sonic Checkout Merchant Sample (PHP)

PHP-only demo (sonicshop-like) that creates orders via S2S using `nimbbl-php-sdk` and launches Sonic Checkout with the returned `order_token`.

### Setup
```bash
# from this repo
composer install

cp config.php.example config.php
# Edit config.php and fill in access_key, access_secret (and optionally api_url/api_version)

php -S localhost:8000 -t public
```
Open http://localhost:8000 in the browser.

**Note:** This sample app is **config.php-only** (no `.env` support). Do NOT commit `config.php`.

### Flow
1) Form POST hits `public/index.php` (server-side PHP).
2) Server uses `nimbbl-php-sdk` to generate merchant token and create order.
3) Returned `order_token` is passed into the Sonic Checkout JS per Standard Checkout web integration.
4) Callback handler shows the response; for production, send it to your server and verify.

### Files
- `public/index.php` – server-side order creation + Sonic Checkout launch (no extra API endpoints).
- `bootstrap.php` – loads config and initializes `\Nimbbl\Api\Api`.
- `public/webhook.php` – webhook handler endpoint for receiving payment events. Requires `X-Nimbbl-Signature` header for verification.

