<?php
/** Router for Railway's PHP built-in web server; supports clean URLs. */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'app' => 'Abhinav Multipanel']);
    return true;
}
if ($path === '/api' || $path === '/api/') { require __DIR__ . '/api.php'; return true; }
if ($path === '/connect' || $path === '/connect/') { require __DIR__ . '/Connect.php'; return true; }

$clean = rawurldecode($path);
if (str_contains($clean, "\0") || str_contains($clean, '..')) {
    http_response_code(400); echo 'Bad request'; return true;
}
$file = __DIR__ . $clean;
if ($clean !== '/' && is_file($file)) return false; // Let PHP serve static assets.

$target = $clean === '/' ? __DIR__ . '/index.php' : __DIR__ . rtrim($clean, '/') . '.php';
if (is_file($target)) { require $target; return true; }
http_response_code(404);
if (is_file(__DIR__ . '/404.php')) require __DIR__ . '/404.php'; else echo 'Not found';
return true;
