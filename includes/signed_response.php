<?php
/** Detached RSA-SHA256 signatures for API response payloads. */
declare(strict_types=1);

function sendSignedJson(array $payload, int $httpStatus = 200): never {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    // Read secrets only from the Railway service environment; never bundle private keys.
    $privatePem = trim((string)(getenv('RSA_PRIVATE_KEY') ?: ''));
    if ($privatePem === '') {
        $encoded = trim((string)(getenv('RSA_PRIVATE_KEY_BASE64') ?: ''));
        if ($encoded !== '') {
            // Permit wrapped Base64 copied from terminals as well as a single-line value.
            $encoded = preg_replace('/\\s+/', '', $encoded) ?? $encoded;
            $decoded = base64_decode($encoded, true);
            if ($decoded !== false) $privatePem = $decoded;
        }
    }
    // Railway may store PEM line breaks as literal backslash-n characters.
    $privatePem = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $privatePem);
    $privatePem = trim($privatePem);

    if (!function_exists('openssl_pkey_get_private') || !function_exists('openssl_sign')) {
        error_log('RSA signing unavailable: PHP OpenSSL extension is missing');
        http_response_code(500);
        echo json_encode(['error' => 'PHP OpenSSL extension is unavailable', 'signed' => false]);
        exit;
    }
    if ($privatePem === '') {
        error_log('RSA signing key missing: set RSA_PRIVATE_KEY_BASE64 in the Railway web service Variables');
        http_response_code(503);
        echo json_encode(['error' => 'RSA signing key is not configured on the server', 'signed' => false], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $privateKey = openssl_pkey_get_private($privatePem);
    if ($privateKey === false) {
        error_log('RSA signing key is present but invalid or malformed');
        http_response_code(503);
        echo json_encode(['error' => 'RSA signing key is configured but invalid; check RSA_PRIVATE_KEY_BASE64', 'signed' => false], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($rawPayload === false || !openssl_sign($rawPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log('RSA response signing failed');
        http_response_code(500);
        echo json_encode(['error' => 'Could not sign API response', 'signed' => false]);
        exit;
    }

    echo json_encode([
        'algorithm' => 'RS256',
        'payload' => $payload,
        'signed_payload' => base64_encode($rawPayload),
        'signature' => base64_encode($signature),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
