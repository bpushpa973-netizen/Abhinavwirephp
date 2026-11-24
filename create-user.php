<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();
$success=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $username=trim((string)($_POST['username']??''));
    $email=trim((string)($_POST['email']??''));
    $password=(string)($_POST['password']??'');
    $initial=max(0,(float)($_POST['balance']??0));
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)) $error='Username must be 3-50 characters (letters, numbers, dot, underscore or hyphen).';
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Enter a valid email address.';
    elseif (strlen($password)<8) $error='Password must be at least 8 characters.';
    else {
        try {
            $pdo=getDBConnection();
            $pdo->beginTransaction();
            $check=$pdo->prepare('SELECT COUNT(*) FROM users WHERE username=? OR email=?');
            $check->execute([$username,$email]);
            if ((int)$check->fetchColumn()>0) throw new RuntimeException('Username or email already exists.');
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $ins=$pdo->prepare("INSERT INTO users (username,email,password,balance,role) VALUES (?,?,?,?,'user')");
            $ins->execute([$username,$email,$hash,$initial]);
            $newId=(int)$pdo->lastInsertId();
            if ($initial>0) {
                $tx=$pdo->prepare("INSERT INTO transactions (user_id,type,amount,status,reference,created_at) VALUES (?,'balance_add',?,'completed','Initial balance set by admin',NOW())");
                $tx->execute([$newId,$initial]);
            }
            $pdo->commit(); $success='Reseller account created successfully.';
        } catch(Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            $error=$e instanceof RuntimeException ? $e->getMessage() : 'Could not create account. Please check the details and try again.';
        }
    }
}
$page_title='Create Reseller - '.SITE_NAME.' '.SITE_SUBTITLE;
require 'includes/header.php';
?>
<div class="max-w-2xl mx-auto">
 <div class="mb-7"><h2 class="text-2xl font-bold">Create Reseller Account</h2><p class="text-sm text-brand-muted mt-1">Only admins can create accounts. Public registration is disabled.</p></div>
 <?php if($success): ?><div class="mb-5 rounded-xl p-4 text-sm bg-brand-success/10 border border-brand-success/20 text-brand-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
 <?php if($error): ?><div class="mb-5 rounded-xl p-4 text-sm bg-brand-error/10 border border-brand-error/20 text-brand-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
 <form method="post" class="glass-card rounded-2xl p-6 border border-brand-border/60 space-y-4">
  <div><label class="block text-xs font-semibold text-brand-muted mb-1.5">Username *</label><input name="username" required minlength="3" maxlength="50" class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl" placeholder="Reseller username"></div>
  <div><label class="block text-xs font-semibold text-brand-muted mb-1.5">Email *</label><input type="email" name="email" required class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl" placeholder="reseller@example.com"></div>
  <div><label class="block text-xs font-semibold text-brand-muted mb-1.5">Password * (minimum 8 characters)</label><input type="password" name="password" required minlength="8" class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl" placeholder="Set login password"></div>
  <div><label class="block text-xs font-semibold text-brand-muted mb-1.5">Opening Balance (₹)</label><input type="number" name="balance" min="0" step="0.01" value="0" class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl"></div>
  <button class="w-full py-3 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary text-white font-bold" type="submit"><i class="fa-solid fa-user-plus mr-2"></i>Create Reseller Account</button>
  <a href="users" class="block text-center text-sm text-brand-muted">Back to Manage Users</a>
 </form>
</div>
<?php require 'includes/footer.php'; ?>
