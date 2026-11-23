<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/database.php';

$tables = [
"CREATE TABLE IF NOT EXISTS users (
 id INT NOT NULL AUTO_INCREMENT,
 username VARCHAR(50) NOT NULL,
 email VARCHAR(100) NOT NULL,
 password VARCHAR(255) NOT NULL,
 balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 role ENUM('admin','user') NOT NULL DEFAULT 'user',
 referral_code VARCHAR(20) DEFAULT NULL,
 referred_by VARCHAR(20) DEFAULT NULL,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_users_username (username),
 UNIQUE KEY uq_users_email (email),
 UNIQUE KEY uq_users_referral_code (referral_code),
 KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS mods (
 id INT NOT NULL AUTO_INCREMENT,
 name VARCHAR(100) NOT NULL,
 description TEXT DEFAULT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS license_keys (
 id INT NOT NULL AUTO_INCREMENT,
 mod_id INT NOT NULL,
 license_key VARCHAR(100) NOT NULL,
 duration INT NOT NULL,
 duration_type ENUM('hours','days','months') NOT NULL DEFAULT 'days',
 price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 status ENUM('available','sold') NOT NULL DEFAULT 'available',
 sold_to INT DEFAULT NULL,
 sold_at TIMESTAMP NULL DEFAULT NULL,
 device_id VARCHAR(255) DEFAULT NULL,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_license_keys_key (license_key),
 KEY idx_license_keys_mod_id (mod_id),
 KEY idx_license_keys_status (status),
 KEY idx_license_keys_sold_to (sold_to),
 CONSTRAINT fk_license_keys_mod FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
 CONSTRAINT fk_license_keys_user FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS mod_apks (
 id INT NOT NULL AUTO_INCREMENT,
 mod_id INT NOT NULL,
 file_name VARCHAR(255) NOT NULL,
 file_path VARCHAR(500) NOT NULL,
 file_size INT NOT NULL DEFAULT 0,
 uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_mod_apks_mod_id (mod_id),
 CONSTRAINT fk_mod_apks_mod FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS referral_codes (
 id INT NOT NULL AUTO_INCREMENT,
 code VARCHAR(20) NOT NULL,
 created_by INT NOT NULL,
 expires_at TIMESTAMP NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_referral_codes_code (code),
 KEY idx_referral_codes_created_by (created_by),
 KEY idx_referral_codes_status (status),
 CONSTRAINT fk_referral_codes_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS transactions (
 id INT NOT NULL AUTO_INCREMENT,
 user_id INT NOT NULL,
 amount DECIMAL(10,2) NOT NULL,
 type ENUM('purchase','balance_add','refund') NOT NULL,
 reference VARCHAR(100) DEFAULT NULL,
 status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_transactions_user_id (user_id),
 KEY idx_transactions_type (type),
 KEY idx_transactions_status (status),
 CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS user_sessions (
 user_id INT NOT NULL,
 session_id VARCHAR(255) NOT NULL,
 ip_address VARCHAR(45) DEFAULT NULL,
 user_agent VARCHAR(255) DEFAULT NULL,
 created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id),
 KEY idx_user_sessions_created_at (created_at),
 CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$error = '';
$success = '';
$pdo = null;
try {
    $pdo = getDBConnection();
    $adminExists = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($check->fetchColumn()) {
            $checkAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $adminExists = ((int)$checkAdmin->fetchColumn() > 0);
        }
    } catch (Throwable $ignored) {}
    if ($adminExists) {
        $success = 'Database setup is already complete. An admin account exists.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim((string)($_POST['username'] ?? 'Abhinav'));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new RuntimeException('Enter a username, valid email, and password of at least 8 characters.');
        }
        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username,email,password,balance,role) VALUES (?,?,?,0.00,'admin')");
        $stmt->execute([$username, $email, $hash]);
        $success = 'All 7 database tables were created and your admin account is ready. Delete install.php from your deployed files now.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Abhinav Multipanel Database Setup</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#0b1020;color:#edf2ff;font:15px system-ui;min-height:100vh;display:grid;place-items:center;padding:20px}
main{width:min(100%,480px);background:#151d32;border:1px solid #293653;border-radius:20px;padding:26px;box-shadow:0 20px 60px #0006}
h1{margin:0 0 8px;font-size:23px}p{color:#aebbd4;line-height:1.55}.tag{color:#9d9eff;font-size:12px;font-weight:700;letter-spacing:1px}
label{display:block;margin:15px 0 6px;color:#cbd5e1}input{width:100%;padding:13px;border-radius:10px;border:1px solid #405071;background:#0d1528;color:white;font-size:15px}
button{width:100%;margin-top:20px;padding:14px;border:0;border-radius:11px;background:linear-gradient(100deg,#6658ee,#11a8c7);color:white;font-weight:800;font-size:15px}
.notice{padding:13px;border-radius:10px;margin-top:15px;background:#103a31;color:#a7f3d0}.err{background:#451d2a;color:#fecdd3}
small{color:#94a3b8}
</style></head><body><main>
<div class="tag">RAILWAY MYSQL • ONE-TIME INSTALLER</div><h1>Abhinav Multipanel Setup</h1>
<p>This page creates the 7 required tables automatically. Create your first admin account below.</p>
<?php if ($error !== ''): ?><div class="notice err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="notice"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($success === '' || strpos($success, 'already complete') !== false): ?>
<form method="post">
<label>Admin username</label><input name="username" value="Abhinav" required>
<label>Admin email</label><input name="email" type="email" placeholder="you@example.com" required>
<label>Admin password (minimum 8 characters)</label><input name="password" type="password" minlength="8" required>
<button type="submit">Create Database & Admin</button>
</form>
<?php endif; ?>
<p><small>After successful setup, remove install.php from the deployed project for security.</small></p>
</main></body></html>
