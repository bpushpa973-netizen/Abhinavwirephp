<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();
$pdo = getDBConnection();
$uid = (int)$_SESSION['user_id'];
$stmt=$pdo->prepare('SELECT id,username,role,balance FROM users WHERE id=? LIMIT 1');
$stmt->execute([$uid]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$user){ session_destroy(); header('Location: login.php'); exit; }
$success=''; $error=''; $generated=[];
$plans=[
  '1:days'=>['label'=>'24 Hours','duration'=>1,'type'=>'days','rate'=>0.50],
  '3:days'=>['label'=>'72 Hours','duration'=>3,'type'=>'days','rate'=>0.80],
  '7:days'=>['label'=>'168 Hours','duration'=>7,'type'=>'days','rate'=>1.00],
  '15:days'=>['label'=>'360 Hours','duration'=>15,'type'=>'days','rate'=>2.00],
  '30:days'=>['label'=>'720 Hours','duration'=>30,'type'=>'days','rate'=>3.00],
];
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['generate_keys'])){
  $modId=(int)($_POST['mod_id']??0); $planKey=(string)($_POST['plan']??'');
  $count=(int)($_POST['count']??1); $devices=(int)($_POST['max_devices']??1);
  $prefix=strtoupper(preg_replace('/[^A-Z0-9]/','', (string)($_POST['prefix']??'ABHINAV')));
  $plan=$plans[$planKey]??null;
  if(!$plan || $modId<1) $error='Please select a valid mod and duration.';
  elseif($count<1 || $count>100) $error='Bulk count must be between 1 and 100.';
  elseif($devices<1 || $devices>100) $error='Max devices must be between 1 and 100.';
  elseif(strlen($prefix)<2 || strlen($prefix)>12) $error='Prefix must contain 2 to 12 letters or numbers.';
  else {
    $unitPrice=(float)$plan['rate']; $total=round($unitPrice*$count*$devices,2);
    try {
      $pdo->beginTransaction();
      $lock=$pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE'); $lock->execute([$uid]);
      $balance=(float)$lock->fetchColumn();
      if($balance<$total) throw new Exception('Insufficient balance. Required: ₹'.number_format($total,2));
      $m=$pdo->prepare("SELECT id,name FROM mods WHERE id=? AND status='active' LIMIT 1"); $m->execute([$modId]); $mod=$m->fetch(PDO::FETCH_ASSOC);
      if(!$mod) throw new Exception('Selected mod is not active.');
      $ins=$pdo->prepare("INSERT INTO license_keys (mod_id,license_key,duration,duration_type,price,status,sold_to,sold_at,max_devices) VALUES (?,?,?,?,?,'sold',?,NOW(),?)");
      for($i=0;$i<$count;$i++){
        do { $key=$prefix.'-'.strtoupper(substr(preg_replace('/[^A-Z0-9]/','',$mod['name']),0,5)).'-'.strtoupper(bin2hex(random_bytes(5))); $q=$pdo->prepare('SELECT id FROM license_keys WHERE license_key=?'); $q->execute([$key]); $exists=$q->fetchColumn(); } while($exists);
        $ins->execute([$modId,$key,$plan['duration'],$plan['type'],$unitPrice*$devices,$uid,$devices]);
        $generated[]=$key;
      }
      $debit=$pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?'); $debit->execute([$total,$uid]);
      $tr=$pdo->prepare("INSERT INTO transactions (user_id,type,amount,reference,status,created_at) VALUES (?,'purchase',?,?, 'completed',NOW())");
      $tr->execute([$uid,-$total,'Auto Key Generation: '.$count.' x '.$plan['label'].' / '.$mod['name']]);
      $pdo->commit(); $success='Successfully generated '.$count.' license key(s). Total charged: ₹'.number_format($total,2);
      $stmt=$pdo->prepare('SELECT id,username,role,balance FROM users WHERE id=?'); $stmt->execute([$uid]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $generated=[]; $error=$e->getMessage(); }
  }
}
$mods=$pdo->query("SELECT id,name FROM mods WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$my=$pdo->prepare('SELECT lk.license_key,lk.duration,lk.duration_type,lk.max_devices,lk.price,lk.sold_at,m.name AS mod_name FROM license_keys lk LEFT JOIN mods m ON m.id=lk.mod_id WHERE lk.sold_to=? ORDER BY lk.id DESC LIMIT 50');
$my->execute([$uid]); $myKeys=$my->fetchAll(PDO::FETCH_ASSOC);
$page_title='Generate Keys - '.SITE_NAME.' '.SITE_SUBTITLE;
require_once 'includes/header.php';
?>
<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
 <div><h2 class="text-2xl font-bold tracking-tight text-glow-primary">Generate Keys</h2><p class="text-sm text-brand-muted mt-1">Create your own license keys instantly. No manual key entry required.</p></div>
 <div class="glass-card rounded-xl px-5 py-3 border border-brand-border"><div class="text-[10px] uppercase font-bold text-brand-muted">Balance</div><div class="text-xl font-extrabold">₹<?=number_format((float)$user['balance'],2)?></div></div>
</div>
<?php if($success): ?><div class="bg-brand-success/10 border border-brand-success/20 text-brand-success rounded-xl p-4 mb-5"><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="bg-brand-error/10 border border-brand-error/20 text-brand-error rounded-xl p-4 mb-5"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($generated): ?><div class="glass-card rounded-2xl p-5 border border-brand-primary/40 mb-6"><h3 class="font-bold mb-3">Your Generated License Keys</h3><div class="space-y-2"><?php foreach($generated as $key): ?><div class="flex gap-2 items-center"><code class="flex-1 break-all rounded-lg bg-brand-bg p-3 text-sm"><?=htmlspecialchars($key)?></code><button type="button" onclick="navigator.clipboard.writeText('<?=htmlspecialchars($key,ENT_QUOTES)?>')" class="px-3 py-2 rounded-lg bg-brand-primary text-white text-xs">Copy</button></div><?php endforeach; ?></div></div><?php endif; ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
 <div class="lg:col-span-2 glass-card rounded-2xl p-6 border border-brand-border/60">
  <h3 class="text-lg font-bold mb-6">⚡ Configure Key</h3>
  <form method="POST" class="space-y-5">
   <div><label class="block text-xs font-bold text-brand-muted mb-2">Select Mod *</label><select name="mod_id" required class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl"><option value="">Choose a mod...</option><?php foreach($mods as $mod): ?><option value="<?=$mod['id']?>"><?=htmlspecialchars($mod['name'])?></option><?php endforeach; ?></select></div>
   <div><label class="block text-xs font-bold text-brand-muted mb-2">Duration *</label><select id="plan" name="plan" required onchange="updateCost()" class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl"><?php foreach($plans as $k=>$p): ?><option value="<?=htmlspecialchars($k)?>" data-rate="<?=$p['rate']?>"><?=htmlspecialchars($p['label'])?> — ₹<?=$p['rate']?> / device</option><?php endforeach; ?></select></div>
   <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div><label class="block text-xs font-bold text-brand-muted mb-2">Prefix</label><input name="prefix" maxlength="12" value="ABHINAV" class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl uppercase"></div>
    <div><label class="block text-xs font-bold text-brand-muted mb-2">Bulk Count</label><input id="count" name="count" type="number" min="1" max="100" value="1" oninput="updateCost()" required class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl"></div>
    <div><label class="block text-xs font-bold text-brand-muted mb-2">Max Devices / Key</label><input id="devices" name="max_devices" type="number" min="1" max="100" value="1" oninput="updateCost()" required class="w-full px-4 py-3 bg-brand-bg/50 border border-brand-border rounded-xl"></div>
   </div>
   <div class="rounded-2xl bg-brand-surface/70 border border-brand-border p-5 text-center"><div class="text-xs uppercase font-bold text-brand-muted">Total Cost</div><div id="totalCost" class="text-4xl font-black mt-2">₹0.50</div><button name="generate_keys" value="1" class="mt-5 w-full py-4 rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary text-white font-extrabold shadow-glow">⚡ Generate Key(s)</button><p class="text-[11px] text-brand-muted mt-3">Amount is deducted from your wallet after successful generation.</p></div>
  </form>
 </div>
 <div class="glass-card rounded-2xl p-6 border border-brand-border/60 h-fit"><h3 class="font-bold mb-4">Pricing</h3><div class="space-y-3 text-sm"><?php foreach($plans as $p): ?><div class="flex justify-between border-b border-brand-border/60 pb-3"><span class="text-brand-muted"><?=htmlspecialchars($p['label'])?></span><b>₹<?=$p['rate']?>/device</b></div><?php endforeach; ?></div></div>
</div>
<div class="glass-card rounded-2xl p-6 border border-brand-border/60 mt-7"><h3 class="font-bold mb-4">My Generated Keys</h3><div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead><tr class="text-brand-muted border-b border-brand-border"><th class="py-3">Mod</th><th>License Key</th><th>Duration</th><th>Devices</th><th>Price</th></tr></thead><tbody><?php foreach($myKeys as $k): ?><tr class="border-b border-brand-border/40"><td class="py-3"><?=htmlspecialchars($k['mod_name']??'')?></td><td class="font-mono"><?=htmlspecialchars($k['license_key'])?></td><td><?=$k['duration'].' '.htmlspecialchars($k['duration_type'])?></td><td><?=isset($k['max_devices'])?(int)$k['max_devices']:1?></td><td>₹<?=number_format((float)$k['price'],2)?></td></tr><?php endforeach; ?><?php if(!$myKeys): ?><tr><td colspan="5" class="py-6 text-center text-brand-muted">No generated keys yet.</td></tr><?php endif; ?></tbody></table></div></div>
<script>function updateCost(){const s=document.getElementById('plan'),r=Number(s.options[s.selectedIndex].dataset.rate||0),c=Math.max(1,Math.min(100,Number(document.getElementById('count').value||1))),d=Math.max(1,Math.min(100,Number(document.getElementById('devices').value||1)));document.getElementById('totalCost').textContent='₹'+(r*c*d).toFixed(2)}updateCost();</script>
<?php require_once 'includes/footer.php'; ?>
