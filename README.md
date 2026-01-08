## Nimbbl Sonic Checkout Merchant Sample (PHP)

PHP-only demo (sonicshop-like) that creates orders via S2S using `nimbbl-php-sdk` and launches Sonic Checkout with the returned `order_token`.

### Setup
```bash
# from this repo
composer install

# Option 1: Use .env file (recommended)
cp env.example .env
# Edit .env and fill in your credentials

# Option 2: Use config.php (for development)
cp config.php.example config.php
# Edit config.php and fill in access_key, access_secret (and optionally api_url/api_version)

php -S localhost:8000 -t public
```
Open http://localhost:8000 in the browser.

**Note:** The application supports both `.env` file and `config.php`. Environment variables from `.env` take precedence over `config.php`.

**Required environment variables:**
- `NIMBBL_ACCESS_KEY` - Your Nimbbl access key
- `NIMBBL_ACCESS_SECRET` - Your Nimbbl access secret

**Optional environment variables:**
- `NIMBBL_API_URL` - API base URL (defaults to production)
- `NIMBBL_API_VERSION` - API version (defaults to v3)
- `APP_ENV` - Set to 'production' for production mode

For production, use environment variables or `.env` file (ensure `.env` is in `.gitignore`).

### Flow
1) Form POST hits `public/index.php` (server-side PHP).
2) Server uses `nimbbl-php-sdk` to generate merchant token and create order.
3) Returned `order_token` is passed into the Sonic Checkout JS per Standard Checkout web integration.
4) Callback handler shows the response; for production, send it to your server and verify.

### Files
- `public/index.php` – server-side order creation + Sonic Checkout launch (no extra API endpoints).
- `bootstrap.php` – loads config and initializes `\Nimbbl\Api\Api`.
- `public/webhook.php` – webhook handler endpoint for receiving payment events. Requires `X-Nimbbl-Signature` header for verification.

