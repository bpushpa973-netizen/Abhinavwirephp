<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();
$pdo=getDBConnection();
if(empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_admin']; $ok=''; $err='';
function setting(PDO $pdo,string $key,string $default=''): string { $q=$pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=?');$q->execute([$key]);$v=$q->fetchColumn();return $v===false?$default:(string)$v; }
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new RuntimeException('Security token invalid. Refresh and try again.');
  $action=(string)($_POST['action']??'');
  if($action==='maintenance'){
   $v=isset($_POST['enabled'])?'1':'0';$q=$pdo->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(\'maintenance_mode\',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$q->execute([$v]);$ok=$v==='1'?'Maintenance mode enabled.':'Maintenance mode disabled.';
  }elseif($action==='create_user'){
   $u=trim((string)($_POST['username']??''));$email=trim((string)($_POST['email']??''));$pw=(string)($_POST['password']??'');$bal=round((float)($_POST['balance']??0),2);
   if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$u)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pw)<8||$bal<0)throw new RuntimeException('Enter valid username, email, password (8+ chars) and balance.');
   $q=$pdo->prepare('INSERT INTO users(username,email,password,balance,role,is_banned) VALUES(?,?,?, ?,\'user\',0)');$q->execute([$u,$email,password_hash($pw,PASSWORD_DEFAULT),$bal]);$id=(int)$pdo->lastInsertId();
   if($bal>0){$q=$pdo->prepare("INSERT INTO transactions(user_id,type,amount,status,reference,created_at) VALUES(?,'balance_add',?,'completed','Opening balance by admin',NOW())");$q->execute([$id,$bal]);}
   $ok='User created. User ID: '.$id;
  }elseif($action==='balance'){
   $id=(int)($_POST['user_id']??0);$amount=round((float)($_POST['amount']??0),2);if($id<1||$amount<=0)throw new RuntimeException('Select a user and enter an amount greater than zero.');
   $pdo->beginTransaction();$q=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('User not found.');
   $q=$pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?');$q->execute([$amount,$id]);$q=$pdo->prepare("INSERT INTO transactions(user_id,type,amount,status,reference,created_at) VALUES(?,'balance_add',?,'completed','Admin balance credit',NOW())");$q->execute([$id,$amount]);$pdo->commit();$ok='Balance added successfully.';
  }elseif($action==='ban'){
   $id=(int)($_POST['user_id']??0);$ban=(int)($_POST['ban']??1);if($id<1||$id===(int)$_SESSION['user_id'])throw new RuntimeException('Invalid user selection.');$q=$pdo->prepare("UPDATE users SET is_banned=? WHERE id=? AND role<>'admin'");$q->execute([$ban?1:0,$id]);$ok=$ban?'User banned.':'User unbanned.';
  }elseif($action==='extend_keys'){
   $days=(int)($_POST['days']??0);if($days<1||$days>3650)throw new RuntimeException('Enter days between 1 and 3650.');
   // Only keys that have not expired are extended. Expired keys remain untouched.
   $q=$pdo->prepare("UPDATE license_keys SET duration=duration + CASE duration_type WHEN 'hours' THEN ?*24 WHEN 'months' THEN GREATEST(1,CEIL(?/30)) ELSE ? END WHERE status='sold' AND sold_at IS NOT NULL AND NOW() <= CASE duration_type WHEN 'hours' THEN DATE_ADD(sold_at, INTERVAL duration HOUR) WHEN 'months' THEN DATE_ADD(sold_at, INTERVAL duration MONTH) ELSE DATE_ADD(sold_at, INTERVAL duration DAY) END");$q->execute([$days,$days,$days]);$ok='Added '.$days.' day(s) to '.$q->rowCount().' active, unexpired key(s). Expired keys were not changed.';
  }else throw new RuntimeException('Unknown action.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e instanceof RuntimeException?$e->getMessage():'Action failed. Check database configuration.';}
}
$users=$pdo->query('SELECT id,username,email,role,balance,is_banned,created_at FROM users ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$maintenance=setting($pdo,'maintenance_mode','0')==='1';
$page_title='Admin Control Panel - '.SITE_NAME;require 'includes/header.php';
?>
<div class="mb-7"><h2 class="text-2xl font-extrabold">Admin Control Panel</h2><p class="text-sm text-brand-muted mt-1">Site, license and reseller account controls</p></div>
<?php if($ok):?><div class="mb-5 rounded-xl p-4 bg-brand-success/10 text-brand-success border border-brand-success/20"><?=htmlspecialchars($ok)?></div><?php endif;?>
<?php if($err):?><div class="mb-5 rounded-xl p-4 bg-brand-error/10 text-brand-error border border-brand-error/20"><?=htmlspecialchars($err)?></div><?php endif;?>
<div class="grid md:grid-cols-2 gap-5 mb-6">
<section class="glass-card rounded-2xl p-6"><h3 class="font-bold text-lg mb-2">Site Maintenance</h3><p class="text-sm text-brand-muted mb-4">When enabled, non-admin login is blocked. Admin access remains available.</p><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="maintenance"><label class="flex items-center gap-3 mb-4"><input type="checkbox" name="enabled" value="1" <?=$maintenance?'checked':''?>> <span>Enable maintenance mode</span></label><button class="px-5 py-3 rounded-xl bg-brand-primary text-white font-bold">Save Maintenance Setting</button></form></section>
<section class="glass-card rounded-2xl p-6"><h3 class="font-bold text-lg mb-2">Add Days to Active Keys</h3><p class="text-sm text-brand-muted mb-4">Extends sold keys that are still valid. Already expired keys are excluded.</p><form method="post" class="space-y-3"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="extend_keys"><input type="number" name="days" min="1" max="3650" required placeholder="Number of days" class="w-full px-4 py-3 bg-brand-bg border border-brand-border rounded-xl"><button class="px-5 py-3 rounded-xl bg-brand-primary text-white font-bold">Add Days to Keys</button></form></section>
<section class="glass-card rounded-2xl p-6"><h3 class="font-bold text-lg mb-4">Create User / Reseller</h3><form method="post" class="space-y-3"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="create_user"><input name="username" required minlength="3" placeholder="Username" class="w-full px-4 py-3 bg-brand-bg border border-brand-border rounded-xl"><input type="email" name="email" required placeholder="Email" class="w-full px-4 py-3 bg-brand-bg border border-brand-border rounded-xl"><input type="password" name="password" required minlength="8" placeholder="Password (8+ characters)" class="w-full px-4 py-3 bg-brand-bg border border-brand-border rounded-xl"><input type="number" name="balance" min="0" step="0.01" value="0" placeholder="Opening balance ₹" class="w-full px-4 py-3 bg-brand-bg border border-brand-border rounded-xl"><button class="w-full px-5 py-3 rounded-xl bg-brand-primary text-white font-bold">Create User</button></form></section>
<section class="glass-card rounded-2xl p-6"><h3 class="font-bold text-lg mb-4">Add User Balance</h3><form method="post" class="space-y-3"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="balance"><select name="user_id" required class="w-full px-4 py-3 bg-brand-bg border border-brand-border rounded-xl"><option value="">Select user</option><?php foreach($users as $u):?><option value="<?=$u['id']?>">#<?=$u['id']?> · <?=htmlspecialchars($u['username'])?></option><?php endforeach;?></select><input type="number" name="amount" min="0.01" step="0.01" required placeholder="Amount ₹" class="w-full px-4 py-3 bg-brand-bg border border-brand-border rounded-xl"><button class="w-full px-5 py-3 rounded-xl bg-brand-primary text-white font-bold">Add Balance</button></form></section>
</div>
<section class="glass-card rounded-2xl p-6"><h3 class="font-bold text-lg mb-4">User Management · ID / Ban / Unban</h3><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-brand-muted border-b border-brand-border"><th class="py-3">User ID</th><th>Username</th><th>Role</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($users as $u):?><tr class="border-b border-brand-border/40"><td class="py-3">#<?= (int)$u['id']?></td><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['role'])?></td><td>₹<?=number_format((float)$u['balance'],2)?></td><td><?=$u['is_banned']?'Banned':'Active'?></td><td><?php if($u['role']!=='admin'):?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="ban"><input type="hidden" name="user_id" value="<?=$u['id']?>"><input type="hidden" name="ban" value="<?=$u['is_banned']?0:1?>"><button class="px-3 py-2 rounded-lg <?=$u['is_banned']?'bg-brand-success/20 text-brand-success':'bg-brand-error/20 text-brand-error'?>"><?=$u['is_banned']?'Unban':'Ban'?></button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require 'includes/footer.php';?>
