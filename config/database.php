<?php
/** Environment-driven database configuration (Railway + local development). */
declare(strict_types=1);

function envValue(string $name, ?string $fallback = null): ?string {
    $value = getenv($name);
    return ($value === false || $value === '') ? $fallback : $value;
}

// Railway MySQL plugin variables are preferred; DB_* also works locally.
$dbHost = envValue('MYSQLHOST', envValue('DB_HOST', '127.0.0.1'));
$dbPort = envValue('MYSQLPORT', envValue('DB_PORT', '3306'));
$dbName = envValue('MYSQLDATABASE', envValue('DB_NAME', 'abhinav_multipanel'));
$dbUser = envValue('MYSQLUSER', envValue('DB_USER', 'root'));
$dbPass = envValue('MYSQLPASSWORD', envValue('DB_PASS', ''));

if (!defined('SITE_NAME')) define('SITE_NAME', envValue('SITE_NAME', 'Abhinav'));
if (!defined('SITE_SUBTITLE')) define('SITE_SUBTITLE', envValue('SITE_SUBTITLE', 'Multipanel'));

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = envValue('MYSQLHOST', envValue('DB_HOST', '127.0.0.1'));
    $port = envValue('MYSQLPORT', envValue('DB_PORT', '3306'));
    $name = envValue('MYSQLDATABASE', envValue('DB_NAME', 'abhinav_multipanel'));
    $user = envValue('MYSQLUSER', envValue('DB_USER', 'root'));
    $pass = envValue('MYSQLPASSWORD', envValue('DB_PASS', ''));

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new RuntimeException('Database connection failed. Check Railway MySQL variables.');
    }
}
