<?php
$page_title="Asset Alerts";
require_once "../../backend/includes/header.php";
$overdue=$conn->query("SELECT m.id,m.work_order_no,m.title,m.due_date,m.priority,a.asset_code,a.asset_name FROM maintenance_records m JOIN assets a ON a.id=m.asset_id WHERE m.status IN ('Open','In Progress','Waiting for Parts') AND m.due_date < CURDATE() ORDER BY m.due_date LIMIT 200")->fetchAll();
$urgent=$conn->query("SELECT m.id,m.work_order_no,m.title,m.due_date,m.priority,a.asset_code,a.asset_name FROM maintenance_records m JOIN assets a ON a.id=m.asset_id WHERE m.status IN ('Open','In Progress','Waiting for Parts') AND m.priority IN ('High','Urgent') ORDER BY m.opened_at DESC LIMIT 200")->fetchAll();
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,"UTF-8");
?>
<h1>Asset Alerts</h1><p>Live alerts from existing maintenance work orders. No duplicate work orders are created.</p>
<?php foreach(["Overdue maintenance"=>$overdue,"High / urgent active work orders"=>$urgent] as $heading=>$rows):?>
<div class="table-card" style="padding:18px;margin:18px 0"><h2><?= $e($heading) ?> (<?= count($rows) ?><?= count($rows)===200?"+":"" ?>)</h2>
<div class="table-responsive"><table class="primary-info-table"><thead><tr><th>Asset</th><th>Work order</th><th>Priority</th><th>Due</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?= $e($r["asset_code"]) ?> — <?= $e($r["asset_name"]) ?></td><td><?= $e($r["work_order_no"]??$r["title"]) ?></td><td><?= $e($r["priority"]) ?></td><td><?= $e($r["due_date"]??"-") ?></td><td><a href="maintenance_edit.php?id=<?= (int)$r["id"] ?>">View</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5">No alerts.</td></tr><?php endif;?></tbody></table></div></div>
<?php endforeach;?>
<?php require_once "../../backend/includes/footer.php"; ?>
