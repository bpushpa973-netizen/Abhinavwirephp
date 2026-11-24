<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();
$pdo = getDBConnection();
$uid = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id,username,role,balance FROM users WHERE id=? LIMIT 1');
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { session_destroy(); header('Location: login.php'); exit; }
$success = ''; $error = ''; $generated = [];
$plans = [
 '1:days'=>['label'=>'24 Hours','duration'=>1,'type'=>'days','rate'=>0.50],
 '3:days'=>['label'=>'72 Hours','duration'=>3,'type'=>'days','rate'=>0.80],
 '7:days'=>['label'=>'168 Hours','duration'=>7,'type'=>'days','rate'=>1.00],
 '15:days'=>['label'=>'360 Hours','duration'=>15,'type'=>'days','rate'=>2.00],
 '30:days'=>['label'=>'720 Hours','duration'=>30,'type'=>'days','rate'=>3.00],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_keys'])) {
 $modId=(int)($_POST['mod_id']??0); $planKey=(string)($_POST['plan']??'');
 $count=(int)($_POST['count']??1); $devices=(int)($_POST['max_devices']??1);
 $prefix=strtoupper(preg_replace('/[^A-Z0-9]/','',(string)($_POST['prefix']??'ABHINAV')));
 $plan=$plans[$planKey]??null;
 if (!$plan || $modId<1) $error='Please select a valid game and duration.';
 elseif ($count<1 || $count>100) $error='Bulk quantity must be between 1 and 100.';
 elseif ($devices<1 || $devices>100) $error='Max devices must be between 1 and 100.';
 elseif (strlen($prefix)<2 || strlen($prefix)>12) $error='Prefix must contain 2 to 12 letters or numbers.';
 else {
  $unitPrice=(float)$plan['rate']; $total=round($unitPrice*$count*$devices,2);
  try {
   $pdo->beginTransaction();
   $lock=$pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE'); $lock->execute([$uid]);
   $balance=(float)$lock->fetchColumn();
   if ($balance<$total) throw new Exception('Insufficient balance. Required: ₹'.number_format($total,2));
   $m=$pdo->prepare("SELECT id,name FROM mods WHERE id=? AND status='active' LIMIT 1"); $m->execute([$modId]); $mod=$m->fetch(PDO::FETCH_ASSOC);
   if (!$mod) throw new Exception('Selected game is not active.');
   $ins=$pdo->prepare("INSERT INTO license_keys (mod_id,license_key,duration,duration_type,price,status,sold_to,sold_at,max_devices) VALUES (?,?,?,?,?,'sold',?,NOW(),?)");
   for ($i=0;$i<$count;$i++) {
    do {
     $key=$prefix.'-'.strtoupper(substr(preg_replace('/[^A-Z0-9]/','',$mod['name']),0,5)).'-'.strtoupper(bin2hex(random_bytes(5)));
     $q=$pdo->prepare('SELECT id FROM license_keys WHERE license_key=?'); $q->execute([$key]); $exists=$q->fetchColumn();
    } while ($exists);
    $ins->execute([$modId,$key,$plan['duration'],$plan['type'],$unitPrice*$devices,$uid,$devices]);
    $generated[]=$key;
   }
   $debit=$pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?'); $debit->execute([$total,$uid]);
   $tr=$pdo->prepare("INSERT INTO transactions (user_id,type,amount,reference,status,created_at) VALUES (?,'purchase',?,?, 'completed',NOW())");
   $tr->execute([$uid,-$total,'Bulk Key Generation: '.$count.' x '.$plan['label'].' / '.$mod['name']]);
   $pdo->commit();
   $success='Generated '.$count.' key(s) successfully. Total charged: ₹'.number_format($total,2);
   $stmt=$pdo->prepare('SELECT id,username,role,balance FROM users WHERE id=?'); $stmt->execute([$uid]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); $generated=[]; $error=$e->getMessage(); }
 }
}
$mods=$pdo->query("SELECT id,name FROM mods WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$page_title='Generate Keys - '.SITE_NAME;
require_once 'includes/header.php';
$generatedText=implode("\n",$generated);
?>
<style>
.aw-page-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin-bottom:26px;flex-wrap:wrap}.aw-eyebrow{font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:#9ca3af;font-weight:800}.aw-title{font-size:clamp(26px,4vw,36px);font-weight:900;letter-spacing:-.04em;margin-top:6px}.aw-subtitle{color:var(--brand-muted-rgb);font-size:13px;margin-top:6px}.aw-balance{padding:15px 20px;border-radius:18px;background:linear-gradient(135deg,rgba(99,102,241,.16),rgba(6,182,212,.10));border:1px solid rgba(129,140,248,.28);min-width:170px}.aw-layout{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(250px,.8fr);gap:20px}.aw-panel{border:1px solid rgba(148,163,184,.18);border-radius:22px;background:linear-gradient(145deg,rgba(30,41,59,.58),rgba(15,23,42,.78));box-shadow:0 18px 50px rgba(0,0,0,.16);padding:24px}.light .aw-panel{background:linear-gradient(145deg,#fff,#f5f7ff);box-shadow:0 18px 50px rgba(15,23,42,.06)}.aw-field label{display:block;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:8px}.aw-field input,.aw-field select{width:100%;background:rgba(2,6,23,.35);border:1px solid rgba(148,163,184,.24);border-radius:13px;padding:13px 14px;color:inherit;outline:none}.light .aw-field input,.light .aw-field select{background:#f8fafc}.aw-field input:focus,.aw-field select:focus{border-color:#818cf8;box-shadow:0 0 0 3px rgba(99,102,241,.14)}.aw-stat{padding:15px;border:1px solid rgba(148,163,184,.16);border-radius:16px;background:rgba(148,163,184,.05)}.aw-primary{background:linear-gradient(100deg,#6366f1,#06b6d4);color:#fff;border-radius:14px;font-weight:900;padding:15px 18px;box-shadow:0 10px 25px rgba(99,102,241,.2)}.aw-primary:hover{filter:brightness(1.08);transform:translateY(-1px)}.aw-keybox{background:rgba(2,6,23,.38);border:1px solid rgba(52,211,153,.28);border-radius:18px;padding:18px}.light .aw-keybox{background:#f0fdf4}.aw-keyline{font:600 12px ui-monospace,monospace;overflow-wrap:anywhere;padding:10px 12px;border-radius:10px;background:rgba(148,163,184,.09)}@media(max-width:850px){.aw-layout{grid-template-columns:1fr}.aw-panel{padding:18px}}
</style>
<div class="aw-page-head">
 <div><div class="aw-eyebrow">Abhinav Wire / Key Studio</div><h1 class="aw-title">Generate License Keys</h1><p class="aw-subtitle">Create one key or generate a complete batch in seconds.</p></div>
 <div class="aw-balance"><div class="text-[10px] uppercase tracking-widest font-extrabold text-brand-muted">Available balance</div><div class="text-2xl font-black mt-1">₹<?=number_format((float)$user['balance'],2)?></div></div>
</div>
<?php if($success): ?><div class="mb-5 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 text-emerald-300 p-4 text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="mb-5 rounded-2xl border border-red-400/30 bg-red-400/10 text-red-300 p-4 text-sm"><i class="fa-solid fa-circle-exclamation mr-2"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($generated): ?>
<div class="aw-keybox mb-6" id="generatedBatch">
 <div class="flex flex-wrap items-center justify-between gap-3 mb-4"><div><div class="text-xs uppercase tracking-widest font-extrabold text-emerald-400">Batch generated</div><h2 class="text-xl font-black mt-1"><?=count($generated)?> keys are ready</h2></div><button type="button" class="aw-primary text-sm" onclick="copyAllGenerated()"><i class="fa-regular fa-copy mr-2"></i>Copy All Keys</button></div>
 <div class="grid sm:grid-cols-2 gap-2 max-h-64 overflow-auto"><?php foreach($generated as $key): ?><div class="aw-keyline"><?=htmlspecialchars($key)?></div><?php endforeach; ?></div>
 <textarea id="generatedKeysText" class="sr-only" aria-hidden="true"><?=htmlspecialchars($generatedText)?></textarea>
 <p class="text-xs text-brand-muted mt-3">All keys are also saved in your separate My Keys section.</p>
</div>
<?php endif; ?>
<div class="aw-layout">
 <section class="aw-panel">
  <div class="flex items-center gap-3 mb-6"><div class="w-11 h-11 rounded-2xl bg-indigo-500/15 text-indigo-300 flex items-center justify-center text-xl"><i class="fa-solid fa-wand-magic-sparkles"></i></div><div><h2 class="font-black text-lg">Batch configuration</h2><p class="text-xs text-brand-muted">Choose game, duration and quantity</p></div></div>
  <form method="POST" class="space-y-5">
   <div class="aw-field"><label for="mod_id">Select game / mod</label><select id="mod_id" name="mod_id" required><option value="">Choose a game...</option><?php foreach($mods as $mod): ?><option value="<?=$mod['id']?>"><?=htmlspecialchars($mod['name'])?></option><?php endforeach; ?></select></div>
   <div class="aw-field"><label for="plan">License duration</label><select id="plan" name="plan" required onchange="updateCost()"><option value="">Choose duration</option><?php foreach($plans as $k=>$p): ?><option value="<?=htmlspecialchars($k)?>" data-rate="<?=$p['rate']?>"><?=htmlspecialchars($p['label'])?> — ₹<?=number_format($p['rate'],2)?> / device</option><?php endforeach; ?></select></div>
   <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="aw-field"><label for="prefix">Key prefix</label><input id="prefix" name="prefix" maxlength="12" value="ABHINAV" autocomplete="off"></div>
    <div class="aw-field"><label for="count">Bulk quantity</label><input id="count" name="count" type="number" min="1" max="100" value="1" oninput="updateCost()" required></div>
    <div class="aw-field"><label for="devices">Devices / key</label><input id="devices" name="max_devices" type="number" min="1" max="100" value="1" oninput="updateCost()" required></div>
   </div>
   <div class="grid grid-cols-2 gap-3"><div class="aw-stat"><div class="text-[10px] uppercase tracking-widest font-bold text-brand-muted">Batch size</div><div id="batchCount" class="text-2xl font-black mt-1">1 key</div></div><div class="aw-stat"><div class="text-[10px] uppercase tracking-widest font-bold text-brand-muted">Estimated total</div><div id="totalCost" class="text-2xl font-black mt-1">₹0.50</div></div></div>
   <button name="generate_keys" value="1" class="aw-primary w-full text-sm"><i class="fa-solid fa-bolt mr-2"></i>Generate Keys</button>
   <p class="text-[11px] text-brand-muted text-center">Wallet balance is deducted only after successful generation.</p>
  </form>
 </section>
 <aside class="aw-panel">
  <div class="flex items-center gap-3 mb-5"><div class="w-10 h-10 rounded-xl bg-cyan-400/10 text-cyan-300 flex items-center justify-center"><i class="fa-solid fa-tags"></i></div><div><h2 class="font-black">Pricing</h2><p class="text-xs text-brand-muted">Per key / device</p></div></div>
  <div class="space-y-3"><?php foreach($plans as $p): ?><div class="flex justify-between items-center border-b border-white/5 pb-3"><span class="text-sm text-brand-muted"><?=htmlspecialchars($p['label'])?></span><strong class="text-sm">₹<?=number_format($p['rate'],2)?></strong></div><?php endforeach; ?></div>
  <div class="mt-6 rounded-2xl p-4 bg-indigo-500/10 border border-indigo-400/20"><div class="font-bold text-sm"><i class="fa-solid fa-layer-group text-indigo-300 mr-2"></i>Bulk generation</div><p class="text-xs text-brand-muted mt-2 leading-5">Generate up to 100 keys at once. Use Copy All to copy the complete batch in one tap.</p></div>
  <a href="my-keys" class="mt-5 flex items-center justify-between rounded-xl border border-brand-border/50 px-4 py-3 text-sm font-bold hover:border-indigo-400/50"><span><i class="fa-solid fa-key text-indigo-300 mr-2"></i>Open My Keys</span><i class="fa-solid fa-arrow-right text-brand-muted"></i></a>
 </aside>
</div>
<script>
function updateCost(){const s=document.getElementById('plan'),o=s.options[s.selectedIndex],r=Number(o?.dataset?.rate||0),c=Math.max(1,Math.min(100,Number(document.getElementById('count').value||1))),d=Math.max(1,Math.min(100,Number(document.getElementById('devices').value||1)));document.getElementById('totalCost').textContent='₹'+(r*c*d).toFixed(2);document.getElementById('batchCount').textContent=c+' '+(c===1?'key':'keys')}
async function copyAllGenerated(){const text=document.getElementById('generatedKeysText').value;try{await navigator.clipboard.writeText(text);alert('All generated keys copied!')}catch(e){const t=document.getElementById('generatedKeysText');t.classList.remove('sr-only');t.select();document.execCommand('copy');t.classList.add('sr-only');alert('All generated keys copied!')}}
updateCost();
</script>
<?php require_once 'includes/footer.php'; ?>
