<?php
require_once __DIR__ . '/../../backend/includes/security.php'; st_start_session();
require_once __DIR__ . '/../../backend/includes/auth_check.php';
require_once __DIR__ . '/../../backend/config/database.php';
if (!in_array($_SESSION['role'] ?? '', ['Administrator','Asset Custodian'], true)) { http_response_code(403); exit('Access denied'); }
$po=trim((string)($_GET['po'] ?? ''));
if (strlen($po)>100) { http_response_code(400); exit('Invalid PO'); }
$rows=[]; $previous=[];
if ($po!=='') {
 $stmt=$conn->prepare("SELECT a.id,a.asset_code,a.asset_type,a.asset_name,a.serial_number,u.full_name FROM assets a JOIN users u ON u.id=a.current_user_id WHERE a.purchase_number=? AND a.asset_source='New Purchase' AND a.status='Assigned' AND NOT EXISTS (SELECT 1 FROM accountability_batch_items bi WHERE bi.asset_id=a.id) AND (SELECT COUNT(*) FROM asset_history ah WHERE ah.asset_id=a.id AND ah.action='ASSIGNED')=1 AND EXISTS (SELECT 1 FROM asset_history ah WHERE ah.asset_id=a.id AND ah.action='ASSIGNED' AND ah.to_user_id=a.current_user_id) AND NOT EXISTS (SELECT 1 FROM asset_history ah WHERE ah.asset_id=a.id AND ah.action IN ('TRANSFERRED','RETURNED')) ORDER BY a.asset_code");
 $stmt->execute([$po]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $stmt=$conn->prepare('SELECT id,created_at FROM accountability_batches WHERE po_number=? ORDER BY id DESC'); $stmt->execute([$po]); $previous=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
function bh($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$page_title='Optional Initial Batch Accountability'; require_once __DIR__.'/../../backend/includes/header.php';
?>
<div class="page-header">
    <div><h2>Initial Batch Accountability</h2><p>Optional for newly purchased assets at their first assignment. Other assets follow the normal lifecycle.</p></div>
    <a class="btn btn-secondary" href="/st/frontend/pages/assignments.php">Back to Assignments</a>
</div>
<div class="table-card" style="padding:20px;margin-bottom:16px">
    <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:220px;margin:0">
            <label for="po">Purchase Order Number</label>
            <input id="po" name="po" maxlength="100" required value="<?= bh($po) ?>" placeholder="e.g. 530000427">
        </div>
        <button class="btn btn-primary" type="submit">Find Assets &amp; Documents</button>
    </form>
</div>
<?php if ($po !== ''): ?>
<div class="table-card" style="padding:20px;margin-bottom:16px">
    <h3>Eligible new assets (<?= count($rows) ?>)</h3>
    <p>Select at least two unbatched assets from this PO for a photo-free A4 landscape form. Corrected purchase dates do not disqualify an otherwise eligible first assignment. Existing individual printing is unchanged.</p>
    <?php if (count($rows) >= 2): ?>
    <form method="post" action="/st/frontend/pages/assignment_batch_create.php" id="batch-create-form">
        <?= csrf_field() ?>
        <input type="hidden" name="po" value="<?= bh($po) ?>">
        <div class="table-responsive"><table class="data-table">
            <thead><tr><th><input type="checkbox" id="batch-select-all" checked aria-label="Select all eligible assets"></th><th>Asset Code</th><th>Part / Model</th><th>Serial No.</th><th>Assigned Employee</th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?><tr>
                <td><input type="checkbox" class="batch-asset-check" name="asset_ids[]" value="<?= (int)$r['id'] ?>" checked aria-label="Select <?= bh($r['asset_code']) ?>"></td>
                <td><?= bh($r['asset_code']) ?></td><td><?= bh($r['asset_type'] ?: $r['asset_name']) ?></td>
                <td><?= bh($r['serial_number']) ?></td><td><?= bh($r['full_name']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:14px">
            <span id="batch-selected-count" role="status" aria-live="polite"></span>
            <button type="submit" id="batch-create-button" class="btn btn-primary">Generate Batch Form</button>
        </div>
    </form>
    <script>
    (function () {
        const form = document.getElementById('batch-create-form');
        const boxes = Array.from(form.querySelectorAll('.batch-asset-check'));
        const all = document.getElementById('batch-select-all');
        const count = document.getElementById('batch-selected-count');
        const button = document.getElementById('batch-create-button');
        function update() {
            const selected = boxes.filter(box => box.checked).length;
            count.textContent = selected + ' of ' + boxes.length + ' selected (minimum 2)';
            button.disabled = selected < 2;
            all.checked = selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
        }
        all.addEventListener('change', () => { boxes.forEach(box => { box.checked = all.checked; }); update(); });
        boxes.forEach(box => box.addEventListener('change', update));
        update();
    })();
    </script>
    <?php else: ?>
    <p>No batch can be created for this PO right now. Use individual accountability for a single asset, or check the PO and first-assignment status.</p>
    <?php endif; ?>
</div>
<?php if ($previous): ?>
<div class="table-card" style="padding:20px">
    <h3>Previous Batch Documents</h3>
    <p>Reprints show the original issued information, even if an asset is later transferred.</p>
    <div class="table-responsive"><table class="data-table"><thead><tr><th>Document</th><th>Created</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($previous as $b): ?><tr>
        <td>BAA-<?= str_pad((string)$b['id'], 6, '0', STR_PAD_LEFT) ?></td>
        <td><?= bh($b['created_at']) ?></td>
        <td><a class="btn btn-secondary" target="_blank" rel="noopener" href="/st/frontend/pages/assignment_batch_print.php?id=<?= (int)$b['id'] ?>">View / Print</a></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php require_once __DIR__.'/../../backend/includes/footer.php'; ?>
