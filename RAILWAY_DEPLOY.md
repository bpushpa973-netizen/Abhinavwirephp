# Abhinav Wire — Railway deployment

## Deploy
1. Push this folder's contents to a GitHub repository and create a Railway project from that repository (or deploy the folder with the Railway CLI).
2. Add a Railway MySQL database service and link it to the web service. Railway provides `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, and `MYSQLPASSWORD`; this app reads those variables automatically. It also accepts `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, or a standard `DATABASE_URL`. Make sure the MySQL service is linked to the PHP web service (Variables → Add Reference).
3. No SQL import is required: the app creates its seven required tables automatically on the first successful database connection. Open `https://YOUR-DOMAIN/install.php` and create your admin username, email, and password (12+ characters). If you prefer environment bootstrap, set `ADMIN_USERNAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` (12+ characters) in the web service Variables. Remove `install.php` after setup. Use Railway MySQL variables; do not use localhost for a separate Railway MySQL service.
4. The included `Dockerfile` installs PHP 8.3 CLI and `pdo_mysql`, then listens on `0.0.0.0:$PORT`. Railway supplies `PORT` and its generated domain provides HTTPS.
5. In Railway, generate a domain under the web service's Networking settings. Test `https://YOUR-DOMAIN/health`, then `/login` and `/api`.

## RSA response signing
The API endpoints `/api` and `/connect` sign the exact JSON payload bytes using RSA-SHA256 (RS256). The response envelope contains:
- `algorithm`: `RS256`
- `payload`: decoded JSON payload for convenient inspection (do not trust it until verified)
- `signed_payload`: base64 of the exact JSON bytes that were signed
- `signature`: base64 RSA signature

Generate a key pair locally (do not commit either private key or deployment secrets):

```sh
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out rsa_private.pem
openssl pkey -in rsa_private.pem -pubout -out rsa_public.pem
```

Set the private key in Railway Variables as `RSA_PRIVATE_KEY_BASE64`, using the base64 output of:

```sh
base64 -w 0 rsa_private.pem
```

On macOS use `base64 < rsa_private.pem | tr -d '\n'`. Alternatively set `RSA_PRIVATE_KEY` to the PEM text; replace each newline with the literal `\\n` if the dashboard requires a single-line value. Keep `rsa_private.pem` secret. Distribute only `rsa_public.pem` to the client that verifies responses.

Example PHP client verification:

```php
$reply = json_decode($httpResponseBody, true);
$raw = base64_decode($reply['signed_payload'] ?? '', true);
$sig = base64_decode($reply['signature'] ?? '', true);
$pub = openssl_pkey_get_public(file_get_contents('rsa_public.pem'));
if ($raw === false || $sig === false || !$pub ||
    openssl_verify($raw, $sig, $pub, OPENSSL_ALGO_SHA256) !== 1) {
    throw new RuntimeException('Untrusted license API response');
}
$verifiedPayload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
```

The private key signs responses; it does not encrypt them for secrecy. If confidential response contents are needed, use HTTPS (already provided by Railway) or encrypt to a client-held public key separately. Never put the server private key in a mod, browser, mobile app, or public repository.

## Endpoints
- `GET /api?key=LICENSE_KEY&device_id=DEVICE_ID`
- `POST /connect` with form fields `game`, `user_key`, `serial` (also accepts `key` and `hwid` aliases)
- `GET /health`

The PHP app's existing session/login pages and admin features remain served by the same runtime. Apache `.htaccess` is retained for Apache deployments; `router.php` supplies equivalent clean PHP routes on Railway.


## Admin login bootstrap
Set `ADMIN_USERNAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in the Railway web service Variables. The password must be at least 12 characters. The app hashes it with PHP `password_hash()` and creates/updates the admin account on the first successful DB connection. Do not put the password in source code or commit it. The SQL dump's legacy email is `admin@example.com`; the bootstrap settings can update it.
