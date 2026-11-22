<?php
/** Detached RSA-SHA256 signatures for API response payloads. */
declare(strict_types=1);

function sendSignedJson(array $payload, int $httpStatus = 200): never {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    $privatePem = getenv('RSA_PRIVATE_KEY') ?: '';
    if ($privatePem === '' && ($encoded = getenv('RSA_PRIVATE_KEY_BASE64'))) {
        $decoded = base64_decode($encoded, true);
        if ($decoded !== false) $privatePem = $decoded;
    }
    // Railway variables commonly store PEM newlines as literal \\n.
    $privatePem = str_replace('\\n', "\n", $privatePem);
    $privateKey = $privatePem !== '' ? openssl_pkey_get_private($privatePem) : false;

    if ($privateKey === false) {
        http_response_code(503);
        echo json_encode([
            'error' => 'RSA signing key is not configured on the server',
            'signed' => false
        ], JSON_UNESCAPED_SLASHES);
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
