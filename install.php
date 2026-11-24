<?php
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$error = ''; $success = false;
try {
    $pdo = getDBConnection();
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminCount === 0) {
        $token = (string)($_POST['csrf'] ?? '');
        if (empty($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'], $token)) throw new RuntimeException('Session expired. Refresh this page.');
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) throw new RuntimeException('Username must be 3–50 characters (letters, numbers, dot, underscore or dash).');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
        if (strlen($password) < 12) throw new RuntimeException('Password must be at least 12 characters.');
        $q=$pdo->prepare('SELECT COUNT(*) FROM users WHERE username=? OR email=?'); $q->execute([$username,$email]);
        if ((int)$q->fetchColumn() > 0) throw new RuntimeException('Username or email already exists.');
        $q=$pdo->prepare("INSERT INTO users (username,email,password,balance,role) VALUES (?,?,?,0,'admin')");
        $q->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $success=true; $adminCount=1; unset($_SESSION['install_csrf']);
    }
} catch (Throwable $e) { $error=$e->getMessage(); error_log('Installer: '.$e->getMessage()); }
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf']=bin2hex(random_bytes(32));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Abhinav Wire Setup</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b1020;color:#edf2ff;font:15px Arial,sans-serif;padding:20px}.card{width:min(440px,100%);background:#151d31;border:1px solid #2b3958;border-radius:18px;padding:28px;box-shadow:0 20px 70px #0008}h1{margin:0 0 8px;font-size:24px}.muted{color:#9eacc8;line-height:1.5}.field{display:block;margin:16px 0 6px;color:#cbd5e1}input{width:100%;padding:13px;border:1px solid #354563;border-radius:10px;background:#0d1527;color:white;font-size:15px}button,a.btn{display:block;width:100%;margin-top:20px;padding:13px;border:0;border-radius:10px;background:linear-gradient(90deg,#635bff,#06b6d4);color:white;text-align:center;text-decoration:none;font-weight:bold;font-size:15px}.msg{padding:12px;border-radius:10px;margin:16px 0;background:#3b1f2a;color:#ffc7d2}.ok{background:#12372e;color:#a7f3d0}.small{font-size:12px;color:#8fa0bf;margin-top:18px}</style></head><body><main class="card"><h1>Abhinav Wire</h1><p class="muted">Railway database setup. Required tables are created automatically when the database connection succeeds.</p>
<?php if ($success): ?><div class="msg ok">Admin account created successfully. You can now log in.</div><a class="btn" href="/login">Open Login</a><p class="small">For security, remove install.php from your deployed files after setup.</p>
<?php elseif (!empty($adminCount)): ?><div class="msg ok">Database is ready and an admin account already exists.</div><a class="btn" href="/login">Open Login</a><p class="small">Setup is locked because an admin account already exists.</p>
<?php else: ?><?php if ($error): ?><div class="msg"><?php echo htmlspecialchars($error,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['install_csrf'],ENT_QUOTES,'UTF-8'); ?>"><label class="field">Admin username</label><input name="username" required minlength="3" maxlength="50" value="Abhinav"><label class="field">Admin email</label><input type="email" name="email" required><label class="field">Admin password (12+ characters)</label><input type="password" name="password" required minlength="12"><button type="submit">Create Database &amp; Admin</button></form><p class="small">No SQL upload/import required. Keep your password private.</p><?php endif; ?></main></body></html>
