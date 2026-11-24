<?php
declare(strict_types=1);

function envValue(string $name, ?string $fallback = null): ?string {
    $value = getenv($name);
    if ($value === false || $value === '') $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
    return ($value === false || $value === '') ? $fallback : (string)$value;
}

function railwayDatabaseConfig(): array {
    $host = envValue('MYSQLHOST', envValue('MYSQL_HOST', envValue('DB_HOST')));
    $port = envValue('MYSQLPORT', envValue('MYSQL_PORT', envValue('DB_PORT', '3306')));
    $name = envValue('MYSQLDATABASE', envValue('MYSQL_DATABASE', envValue('DB_NAME')));
    $user = envValue('MYSQLUSER', envValue('MYSQL_USER', envValue('DB_USER')));
    $pass = envValue('MYSQLPASSWORD', envValue('MYSQL_PASSWORD', envValue('DB_PASS')));
    $url = envValue('DATABASE_URL');
    if ($url) {
        $parts = parse_url($url);
        if (is_array($parts) && !empty($parts['host'])) {
            $host = $parts['host']; $port = (string)($parts['port'] ?? $port ?? '3306');
            $name = isset($parts['path']) ? ltrim($parts['path'], '/') : $name;
            $user = isset($parts['user']) ? rawurldecode($parts['user']) : $user;
            $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : $pass;
        }
    }
    foreach (['host'=>$host, 'database'=>$name, 'username'=>$user] as $field=>$value) {
        if ($value === null || $value === '') throw new RuntimeException('Missing database setting: '.$field.'. Add Railway MySQL variables to this web service.');
    }
    return ['host'=>$host, 'port'=>$port ?: '3306', 'database'=>$name, 'username'=>$user, 'password'=>$pass ?? ''];
}

if (!defined('SITE_NAME')) define('SITE_NAME', envValue('SITE_NAME', 'Abhinav Wire'));
if (!defined('SITE_SUBTITLE')) define('SITE_SUBTITLE', envValue('SITE_SUBTITLE', 'Abhinav Gaming'));

/** Create the app's tables on first connection. Safe to run on every deploy/request. */
function ensureDatabaseSchema(PDO $pdo): void {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0.00,
            role ENUM('admin','user') DEFAULT 'user',
            referral_code VARCHAR(20) DEFAULT NULL UNIQUE,
            referred_by VARCHAR(20) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS mods (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS license_keys (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            mod_id INT NOT NULL,
            license_key VARCHAR(100) NOT NULL UNIQUE,
            duration INT NOT NULL,
            duration_type ENUM('hours','days','months') DEFAULT 'days',
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('available','sold') DEFAULT 'available',
            sold_to INT DEFAULT NULL,
            sold_at TIMESTAMP NULL DEFAULT NULL,
            device_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_license_keys_mod_id (mod_id), KEY idx_license_keys_status (status), KEY idx_license_keys_sold_to (sold_to),
            CONSTRAINT fk_license_mod FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            CONSTRAINT fk_license_user FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS mod_apks (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            mod_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL DEFAULT 0,
            uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_mod_apks_mod_id (mod_id),
            CONSTRAINT fk_mod_apks_mod FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS referral_codes (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL UNIQUE,
            created_by INT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_referral_codes_created_by (created_by),
            CONSTRAINT fk_referral_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS transactions (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type ENUM('purchase','balance_add','refund') NOT NULL,
            reference VARCHAR(100) DEFAULT NULL,
            status ENUM('pending','completed','failed') DEFAULT 'pending',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_transactions_user_id (user_id), KEY idx_transactions_type (type), KEY idx_transactions_status (status),
            CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_sessions (
            user_id INT NOT NULL PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_sessions_created_at (created_at),
            CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($tables as $sql) $pdo->exec($sql);
    // Safe forward migration for automatic key generation device limits.
    $check = $pdo->query("SHOW COLUMNS FROM license_keys LIKE 'max_devices'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE license_keys ADD COLUMN max_devices INT NOT NULL DEFAULT 1 AFTER device_id");
    }
}

function ensureEnvironmentAdmin(PDO $pdo): void {
    static $done = false; if ($done) return; $done = true;
    $password = envValue('ADMIN_PASSWORD');
    if ($password === null || $password === '') return;
    if (strlen($password) < 12) { error_log('ADMIN_PASSWORD must be at least 12 characters; admin bootstrap skipped.'); return; }
    $username = envValue('ADMIN_USERNAME', 'admin'); $email = envValue('ADMIN_EMAIL', 'admin@example.com');
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $find = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'); $find->execute([$username, $email]);
    $id = $find->fetchColumn();
    if ($id) { $q=$pdo->prepare("UPDATE users SET username=?, email=?, password=?, role='admin' WHERE id=?"); $q->execute([$username,$email,$hash,$id]); }
    else { $q=$pdo->prepare("INSERT INTO users (username,email,password,balance,role) VALUES (?,?,?,0,'admin')"); $q->execute([$username,$email,$hash]); }
}

function getDBConnection(): PDO {
    static $pdo = null; if ($pdo instanceof PDO) return $pdo;
    try {
        $cfg = railwayDatabaseConfig();
        $dsn = 'mysql:host='.$cfg['host'].';port='.$cfg['port'].';dbname='.$cfg['database'].';charset=utf8mb4';
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>10]);
        ensureDatabaseSchema($pdo);
        ensureEnvironmentAdmin($pdo);
        return $pdo;
    } catch (Throwable $e) {
        error_log('Abhinav Wire DB/bootstrap error: '.$e->getMessage());
        throw new RuntimeException('Database setup failed. Check Railway MySQL Variables and ensure the MySQL service is reachable from this web service.');
    }
}
