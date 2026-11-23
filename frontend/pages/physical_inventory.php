<?php
$page_title='Physical Inventory & QR Verification';
require_once '../../backend/includes/header.php';
require_role(['Administrator','Asset Custodian']);
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['inventory_csrf'])) $_SESSION['inventory_csrf']=bin2hex(random_bytes(24));
$csrf=$_SESSION['inventory_csrf'];
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$msg='';$err='';
function inventory_token(string $raw):string {
 $raw=trim($raw);
 if (preg_match('~^https?://~i',$raw)) {
  $parts=parse_url($raw);if (!$parts) return '';
  parse_str($parts['query']??'',$q);
  if (isset($q['token']) && is_string($q['token'])) return trim($q['token']);
  if (preg_match('~/asset_scan\.php/([^/?]+)~',$parts['path']??'',$m)) return rawurldecode($m[1]);
  return '';
 }
 return $raw;
}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!hash_equals($csrf,(string)($_POST['csrf']??''))) {http_response_code(403);exit('Invalid form token');}
 $action=(string)($_POST['action']??'');
 if ($action==='create') {
  $title=mb_substr(trim((string)($_POST['title']??'')),0,150);
  if ($title==='') $err='Enter a session name.';
  else {
   try {
    $conn->beginTransaction();
    $st=$conn->prepare('INSERT INTO inventory_sessions(title,created_by) VALUES (?,?)');$st->execute([$title,(int)$_SESSION['user_id']]);
    $sid=(int)$conn->lastInsertId();
    $st=$conn->prepare("INSERT INTO inventory_session_items(session_id,asset_id,expected_status,expected_user_id) SELECT ?,id,status,current_user_id FROM assets WHERE status <> 'Retired'");$st->execute([$sid]);
    $conn->commit();header('Location: physical_inventory.php?id='.$sid);exit;
   }catch(Throwable $ex){if($conn->inTransaction())$conn->rollBack();$err='Unable to create session. Check the inventory database migration.';}
  }
 } elseif (in_array($action,['verify','resolve','reopen','close'],true)) {
  $sid=filter_var($_POST['session_id']??null,FILTER_VALIDATE_INT);
  $st=$conn->prepare('SELECT id,status FROM inventory_sessions WHERE id=?');$st->execute([$sid]);$session=$st->fetch(PDO::FETCH_ASSOC);
  if (!$session) $err='Session not found.';
  elseif ($session['status']!=='Open') $err='Session is closed; records cannot be changed.';
  elseif ($action==='close') {
   $st=$conn->prepare("SELECT COUNT(*) FROM inventory_session_items WHERE session_id=? AND verification_status='Discrepancy' AND reconciliation_status='Open'");$st->execute([$sid]);
   if ((int)$st->fetchColumn()>0) $err='Resolve or document all open discrepancies before closing the session.';
   else {$conn->prepare("UPDATE inventory_sessions SET status='Closed',closed_at=NOW() WHERE id=? AND status='Open'")->execute([$sid]);$msg='Session closed.';}
  } elseif ($action==='verify') {
   $code=inventory_token((string)($_POST['code']??''));
   $state=($_POST['state']??'')==='Discrepancy'?'Discrepancy':'Verified';
   $notes=mb_substr(trim((string)($_POST['notes']??'')),0,500);
   if ($code==='') $err='Scan a valid ST asset QR or enter an asset code.';
   else {
    $st=$conn->prepare('SELECT i.id FROM inventory_session_items i JOIN assets a ON a.id=i.asset_id WHERE i.session_id=? AND (a.asset_code=? OR a.qr_token=?) LIMIT 1');$st->execute([$sid,$code,$code]);$item=$st->fetch(PDO::FETCH_ASSOC);
    if (!$item) $err='This asset is not part of the selected session.';
    else {
     $st=$conn->prepare("UPDATE inventory_session_items SET verification_status=?,notes=?,verified_by=?,verified_at=NOW(),reconciliation_status=?,reconciliation_notes=NULL,reconciled_by=NULL,reconciled_at=NULL WHERE id=? AND session_id=?");
     $st->execute([$state,$notes,(int)$_SESSION['user_id'],$state==='Discrepancy'?'Open':'Not Required',$item['id'],$sid]);$msg='Verification recorded.';
    }
   }
  } elseif ($action==='resolve' || $action==='reopen') {
   $itemId=filter_var($_POST['item_id']??null,FILTER_VALIDATE_INT);
   $notes=mb_substr(trim((string)($_POST['reconciliation_notes']??'')),0,1000);
   $st=$conn->prepare("UPDATE inventory_session_items SET reconciliation_status=?,reconciliation_notes=?,reconciled_by=?,reconciled_at=NOW() WHERE id=? AND session_id=? AND verification_status='Discrepancy'");
   $st->execute([$action==='resolve'?'Resolved':'Open',$notes,(int)$_SESSION['user_id'],$itemId,$sid]);
   $msg=$st->rowCount()?'Reconciliation updated.':'No matching discrepancy.';
  }
  $id=$sid;
 }
}
$session=null;$items=[];
if($id){$st=$conn->prepare('SELECT * FROM inventory_sessions WHERE id=?');$st->execute([$id]);$session=$st->fetch(PDO::FETCH_ASSOC);
 if($session){$st=$conn->prepare('SELECT i.*,a.asset_code,a.asset_name,a.serial_number,a.condition_status,u.full_name AS custodian FROM inventory_session_items i JOIN assets a ON a.id=i.asset_id LEFT JOIN users u ON u.id=i.expected_user_id WHERE i.session_id=? ORDER BY a.asset_code');$st->execute([$id]);$items=$st->fetchAll(PDO::FETCH_ASSOC);}}
if(isset($_GET['export']) && $_GET['export']==='csv' && $session){
 while(ob_get_level())ob_end_clean();
 header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="ST-Inventory-'.(int)$id.'.csv"');
 $f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,['Asset Code','Asset Name','Serial Number','Expected Status','Expected Custodian','Verification','Reconciliation','Verification Notes','Reconciliation Notes','Verified At']);
 foreach($items as $i){$fields=[$i['asset_code'],$i['asset_name'],$i['serial_number'],$i['expected_status'],$i['custodian'],$i['verification_status'],$i['reconciliation_status'],$i['notes'],$i['reconciliation_notes'],$i['verified_at']];foreach($fields as &$v){$v=(string)($v??'');if(preg_match('/^[=+@\-\t\r]/',$v))$v="'".$v;}unset($v);fputcsv($f,$fields);}fclose($f);exit;
}
$sessions=$conn->query('SELECT s.*,u.full_name AS creator FROM inventory_sessions s JOIN users u ON u.id=s.created_by ORDER BY s.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.inv-box{border:1px solid #8885;border-radius:14px;padding:18px;margin:16px 0}.inv-box input,.inv-box select,.inv-box textarea{padding:10px;max-width:100%;margin:4px 0}.inv-flex{display:flex;flex-wrap:wrap;gap:12px;align-items:center}.inv-table{width:100%;border-collapse:collapse;min-width:730px}.inv-table th,.inv-table td{padding:10px;text-align:left;border-bottom:1px solid #8884}.inv-scroll{overflow-x:auto}.inv-stats{display:flex;flex-wrap:wrap;gap:18px;font-weight:bold}.inv-help{opacity:.8}.inv-camera{width:100%;max-width:340px}.inv-box button{cursor:pointer}@media print{.inv-noprint,.sidebar,.topbar{display:none!important}.inv-box{break-inside:avoid}}
</style>
<h1>Physical Inventory &amp; QR Verification</h1><p class="inv-help">Works with keyboard-style USB scanners and mobile QR photos. Inventory does not change asset assignments or maintenance records.</p>
<?php if($msg):?><div class="inv-box" role="status"><?= $e($msg) ?></div><?php endif;?>
<?php if($err):?><div class="inv-box" role="alert"><?= $e($err) ?></div><?php endif;?>
<?php if(!$session):?>
<div class="inv-box inv-noprint"><h2>Create inventory session</h2><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="create"><input name="title" required maxlength="150" placeholder="Inventory session name"><button class="btn btn-primary">Create Session</button></form><p class="inv-help">Includes all currently non-retired assets at session creation.</p></div>
<div class="inv-box"><h2>Sessions</h2><div class="inv-scroll"><table class="inv-table"><thead><tr><th>Name</th><th>Status</th><th>Created</th><th>Action</th></tr></thead><tbody><?php foreach($sessions as $s):?><tr><td><?= $e($s['title']) ?></td><td><?= $e($s['status']) ?></td><td><?= $e($s['created_at']) ?></td><td><a href="?id=<?= (int)$s['id'] ?>">Open</a></td></tr><?php endforeach;?></tbody></table></div></div>
<?php else:$counts=array_count_values(array_column($items,'verification_status'));$open=0;foreach($items as $i)if($i['verification_status']==='Discrepancy' && $i['reconciliation_status']==='Open')$open++;?>
<div class="inv-box"><h2><?= $e($session['title']) ?> — <?= $e($session['status']) ?></h2><div class="inv-stats"><span>Total <?= count($items) ?></span><span>Verified <?= (int)($counts['Verified']??0) ?></span><span>Discrepancies <?= (int)($counts['Discrepancy']??0) ?></span><span>Pending <?= (int)($counts['Pending']??0) ?></span><span>Open discrepancies <?= $open ?></span></div><div class="inv-flex inv-noprint"><a class="btn btn-secondary" href="physical_inventory.php">All sessions</a><a class="btn btn-secondary" href="?id=<?= (int)$id ?>&amp;export=csv">Export CSV (Excel)</a><button type="button" class="btn btn-secondary" onclick="window.print()">Print Report</button></div></div>
<?php if($session['status']==='Open'):?>
<div class="inv-box inv-noprint"><h2>Scan and verify</h2><form method="post" id="verifyForm"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="session_id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="verify"><div class="inv-flex"><input id="invCode" name="code" required autofocus placeholder="Asset code or ST QR token" style="min-width:260px;flex:1"><select name="state"><option value="Verified">Verified</option><option value="Discrepancy">Discrepancy</option></select></div><textarea name="notes" maxlength="500" rows="2" style="width:100%" placeholder="Inspection notes / discrepancy details"></textarea><div class="inv-flex"><button class="btn btn-primary">Save Verification</button><label class="btn btn-secondary" for="invPhoto">Scan QR with phone camera / choose image</label><input id="invPhoto" type="file" accept="image/*" capture="environment" style="max-width:230px"></div><p id="invScanStatus" class="inv-help" role="status">On a phone, choose camera/photo. On Windows, scan into the asset code field using a USB QR scanner.</p></form></div>
<script src="/st/frontend/js/jsqr.min.js"></script>
<script>
(function(){const photo=document.getElementById('invPhoto'),field=document.getElementById('invCode'),status=document.getElementById('invScanStatus');
function parseCode(raw){raw=(raw||'').trim();try{const u=new URL(raw);const token=u.searchParams.get('token');if(token)return token.trim();const match=u.pathname.match(/\/asset_scan\.php\/([^/]+)/);return match?decodeURIComponent(match[1]):'';}catch(e){return raw;}}
photo.addEventListener('change',async function(){const file=this.files&&this.files[0];if(!file)return;status.textContent='Reading QR image...';try{const img=await createImageBitmap(file);const canvas=document.createElement('canvas');const scale=Math.min(1,1400/Math.max(img.width,img.height));canvas.width=Math.round(img.width*scale);canvas.height=Math.round(img.height*scale);const ctx=canvas.getContext('2d',{willReadFrequently:true});ctx.drawImage(img,0,0,canvas.width,canvas.height);let raw='';if('BarcodeDetector' in window){try{const d=new BarcodeDetector({formats:['qr_code']});const codes=await d.detect(canvas);if(codes.length)raw=codes[0].rawValue;}catch(e){}}
if(!raw&&window.jsQR){const result=window.jsQR(ctx.getImageData(0,0,canvas.width,canvas.height).data,canvas.width,canvas.height,{inversionAttempts:'attemptBoth'});if(result)raw=result.data;}
const code=parseCode(raw);if(!code){status.textContent='No valid ST QR code found. Try a clearer photo or type the asset code.';return;}field.value=code;status.textContent='QR read successfully. Review condition and tap Save Verification.';field.focus();}catch(e){status.textContent='Unable to read this photo. Try another image or manual entry.';}finally{this.value='';}});
})();
</script>
<?php endif;?>
<div class="inv-box"><h2>Inventory checklist and reconciliation</h2><p class="inv-help">Pending means not yet inspected, not automatically missing. Resolved discrepancies remain visible in the audit report.</p><div class="inv-scroll"><table class="inv-table"><thead><tr><th>Asset</th><th>Expected custodian</th><th>Verification</th><th>Notes</th><th>Reconciliation</th></tr></thead><tbody>
<?php foreach($items as $i):?><tr><td><strong><?= $e($i['asset_code']) ?></strong><div><?= $e($i['asset_name']) ?></div><small><?= $e($i['serial_number']??'') ?></small></td><td><?= $e($i['custodian']??'Unassigned') ?></td><td><?= $e($i['verification_status']) ?></td><td><?= $e($i['notes']??'') ?></td><td><?php if($i['verification_status']==='Discrepancy'):?><strong><?= $e($i['reconciliation_status']) ?></strong><p><?= $e($i['reconciliation_notes']??'') ?></p><?php if($session['status']==='Open'):?><form method="post" class="inv-noprint"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="session_id" value="<?= (int)$id ?>"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><textarea name="reconciliation_notes" maxlength="1000" rows="2" placeholder="Resolution evidence / follow-up" required><?= $e($i['reconciliation_notes']??'') ?></textarea><div class="inv-flex"><button name="action" value="resolve" class="btn btn-secondary">Resolve</button><button name="action" value="reopen" class="btn btn-secondary">Reopen</button></div></form><?php endif;?><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
<?php if($session['status']==='Open'):?><form method="post" class="inv-box inv-noprint" onsubmit="return confirm('Close session? Pending assets will remain Pending.')"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="session_id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="close"><button class="btn btn-secondary">Close Session</button><p class="inv-help">Open discrepancies must be reconciled first. Pending items are retained for the final report.</p></form><?php endif;?>
<?php endif;?>
<?php require_once '../../backend/includes/footer.php'; ?>
