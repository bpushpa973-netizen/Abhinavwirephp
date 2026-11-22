<?php
/** GET /api?key=...&device_id=... - RSA-signed license validation response. */
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/signed_response.php';

$key = trim((string)($_GET['key'] ?? ''));
$deviceId = trim((string)($_GET['device_id'] ?? ''));
if ($key === '') sendSignedJson(['status' => 'error', 'message' => 'License key is required'], 400);
if (strlen($key) > 100 || strlen($deviceId) > 255) sendSignedJson(['status' => 'error', 'message' => 'Invalid request'], 400);

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT lk.*, m.name AS mod_name, m.status AS mod_status
        FROM license_keys lk INNER JOIN mods m ON lk.mod_id = m.id
        WHERE lk.license_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $license = $stmt->fetch();
    if (!$license) sendSignedJson(['status' => 'error', 'message' => 'Invalid license key'], 401);
    if ($license['mod_status'] !== 'active') sendSignedJson(['status' => 'error', 'message' => 'This mod is currently disabled'], 403);
    if ($license['status'] !== 'sold' || empty($license['sold_to'])) sendSignedJson(['status' => 'error', 'message' => 'License key has not been activated or sold yet'], 403);
    if (empty($license['sold_at'])) sendSignedJson(['status' => 'error', 'message' => 'License activation date is missing'], 403);

    $unit = strtolower((string)$license['duration_type']);
    if (!in_array($unit, ['hours', 'days', 'months'], true)) sendSignedJson(['status' => 'error', 'message' => 'Invalid license duration'], 403);
    $expiresAt = (new DateTimeImmutable((string)$license['sold_at']))->modify('+' . (int)$license['duration'] . ' ' . $unit);
    if (!$expiresAt || new DateTimeImmutable('now') > $expiresAt) sendSignedJson(['status' => 'error', 'message' => 'License key has expired'], 403);

    if ($deviceId !== '') {
        if (empty($license['device_id'])) {
            $bind = $pdo->prepare("UPDATE license_keys SET device_id = ? WHERE id = ? AND (device_id IS NULL OR device_id = '')");
            $bind->execute([$deviceId, $license['id']]);
            $check = $pdo->prepare('SELECT device_id FROM license_keys WHERE id = ?');
            $check->execute([$license['id']]);
            $bound = (string)$check->fetchColumn();
            if (!hash_equals($bound, $deviceId)) sendSignedJson(['status' => 'error', 'message' => 'License key is locked to another device'], 403);
            $license['device_id'] = $bound;
        } elseif (!hash_equals((string)$license['device_id'], $deviceId)) {
            sendSignedJson(['status' => 'error', 'message' => 'License key is locked to another device'], 403);
        }
    } elseif (!empty($license['device_id'])) {
        sendSignedJson(['status' => 'error', 'message' => 'Device ID is required for this locked license key'], 403);
    }

    sendSignedJson(['status' => 'success', 'message' => 'License key validated successfully', 'data' => [
        'mod_name' => $license['mod_name'],
        'duration' => (int)$license['duration'] . ' ' . $unit,
        'sold_at' => $license['sold_at'],
        'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
        'device_id' => $license['device_id']
    ]]);
} catch (Throwable $e) {
    error_log('api.php verification error: ' . $e->getMessage());
    sendSignedJson(['status' => 'error', 'message' => 'Server database connection error'], 500);
}
