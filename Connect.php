<?php
/** Abhinav Multipanel license verification endpoint. POST: game, user_key, serial. */
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/signed_response.php';

function connectReply(bool $status, string $message, array $data = [], int $httpStatus = 200): never {
    $payload = ['status' => $status ? 'success' : 'error', 'message' => $message];
    if ($data) $payload['data'] = $data;
    sendSignedJson($payload, $httpStatus);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') connectReply(false, 'POST request required', [], 405);
$game = trim((string)($_POST['game'] ?? ''));
$userKey = trim((string)($_POST['user_key'] ?? $_POST['key'] ?? ''));
$serial = trim((string)($_POST['serial'] ?? $_POST['hwid'] ?? ''));
if ($game === '' || $userKey === '' || $serial === '') connectReply(false, 'game, user_key and serial are required', [], 400);
if (strlen($game) > 100 || strlen($userKey) > 100 || strlen($serial) > 255) connectReply(false, 'Invalid request', [], 400);

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT lk.*, m.name AS mod_name, m.status AS mod_status
        FROM license_keys lk INNER JOIN mods m ON m.id = lk.mod_id
        WHERE lk.license_key = ? LIMIT 1");
    $stmt->execute([$userKey]);
    $license = $stmt->fetch();
    if (!$license) connectReply(false, 'Invalid license key', [], 401);
    if (strcasecmp((string)$license['mod_name'], $game) !== 0) connectReply(false, 'License key does not match this game', [], 403);
    if ($license['mod_status'] !== 'active') connectReply(false, 'This mod is currently disabled', [], 403);
    if ($license['status'] !== 'sold' || empty($license['sold_to'])) connectReply(false, 'License key has not been activated or sold', [], 403);
    if (empty($license['sold_at'])) connectReply(false, 'License activation date is missing', [], 403);

    $unit = strtolower((string)$license['duration_type']);
    if (!in_array($unit, ['hours', 'days', 'months'], true)) connectReply(false, 'Invalid license duration', [], 403);
    $expiresAt = (new DateTimeImmutable((string)$license['sold_at']))->modify('+' . (int)$license['duration'] . ' ' . $unit);
    if (!$expiresAt || new DateTimeImmutable('now') > $expiresAt) connectReply(false, 'License key has expired', [], 403);

    if (empty($license['device_id'])) {
        $bind = $pdo->prepare("UPDATE license_keys SET device_id = ? WHERE id = ? AND (device_id IS NULL OR device_id = '')");
        $bind->execute([$serial, $license['id']]);
        $check = $pdo->prepare('SELECT device_id FROM license_keys WHERE id = ?');
        $check->execute([$license['id']]);
        $boundDevice = (string)$check->fetchColumn();
        if (!hash_equals($boundDevice, $serial)) connectReply(false, 'License key was activated on another device', [], 403);
    } elseif (!hash_equals((string)$license['device_id'], $serial)) {
        connectReply(false, 'License key is locked to another device', [], 403);
    }

    connectReply(true, 'License key validated successfully', [
        'mod_name' => $license['mod_name'],
        'duration' => (int)$license['duration'] . ' ' . $unit,
        'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
        'device_id' => $serial
    ]);
} catch (Throwable $e) {
    error_log('Connect.php verification error: ' . $e->getMessage());
    connectReply(false, 'Server database connection error', [], 500);
}
