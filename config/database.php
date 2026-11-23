<?php
declare(strict_types=1);

/** Read an environment variable, supporting Railway's common naming variants. */
function envValue(string $name, ?string $fallback = null): ?string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
    }
    return ($value === false || $value === '') ? $fallback : (string)$value;
}

function railwayDatabaseConfig(): array {
    // Railway MySQL plugin variables (plus underscore aliases used by some templates).
    $host = envValue('MYSQLHOST', envValue('MYSQL_HOST', envValue('DB_HOST')));
    $port = envValue('MYSQLPORT', envValue('MYSQL_PORT', envValue('DB_PORT', '3306')));
    $name = envValue('MYSQLDATABASE', envValue('MYSQL_DATABASE', envValue('DB_NAME')));
    $user = envValue('MYSQLUSER', envValue('MYSQL_USER', envValue('DB_USER')));
    $pass = envValue('MYSQLPASSWORD', envValue('MYSQL_PASSWORD', envValue('DB_PASS')));

    // Also accept a standard MySQL connection URL when supplied by the host.
    $url = envValue('DATABASE_URL');
    if ($url) {
        $parts = parse_url($url);
        if (is_array($parts) && !empty($parts['host'])) {
            $host = $parts['host'];
            $port = (string)($parts['port'] ?? $port ?? '3306');
            $name = isset($parts['path']) ? ltrim($parts['path'], '/') : $name;
            $user = isset($parts['user']) ? rawurldecode($parts['user']) : $user;
            $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : $pass;
        }
    }

    foreach (['host' => $host, 'database' => $name, 'username' => $user] as $field => $value) {
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing database setting: ' . $field . '. Link the Railway MySQL service to this web service and expose MYSQLHOST, MYSQLDATABASE and MYSQLUSER.');
        }
    }
    return ['host' => $host, 'port' => $port ?: '3306', 'database' => $name, 'username' => $user, 'password' => $pass ?? ''];
}

if (!defined('SITE_NAME')) define('SITE_NAME', envValue('SITE_NAME', 'Abhinav'));
if (!defined('SITE_SUBTITLE')) define('SITE_SUBTITLE', envValue('SITE_SUBTITLE', 'Multipanel'));

/** Optional one-time bootstrap/update of the admin account using Railway Variables. */
function ensureEnvironmentAdmin(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $password = envValue('ADMIN_PASSWORD');
    if ($password === null || $password === '') return;
    if (strlen($password) < 12) {
        error_log('ADMIN_PASSWORD was provided but is shorter than 12 characters; admin bootstrap skipped.');
        return;
    }
    $username = envValue('ADMIN_USERNAME', 'admin');
    $email = envValue('ADMIN_EMAIL', 'admin@example.com');
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $find = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $find->execute([$username, $email]);
    $id = $find->fetchColumn();
    if ($id) {
        $update = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = 'admin' WHERE id = ?");
        $update->execute([$username, $email, $hash, $id]);
    } else {
        $insert = $pdo->prepare("INSERT INTO users (username, email, password, balance, role) VALUES (?, ?, ?, 0, 'admin')");
        $insert->execute([$username, $email, $hash]);
    }
}

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    try {
        $cfg = railwayDatabaseConfig();
        $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['database'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        ensureEnvironmentAdmin($pdo);
        return $pdo;
    } catch (Throwable $e) {
        // Keep credentials out of browser output; detailed cause is available in Railway logs.
        error_log('Abhinav Multipanel DB error: ' . $e->getMessage());
        throw new RuntimeException('Database connection failed. Check Railway MySQL Variables and confirm the database service is linked.');
    }
}
