<?php
require_once __DIR__ . '/../../backend/includes/security.php';
st_start_session();
require_once __DIR__ . '/../../backend/includes/auth_check.php';
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/app_config.php';
header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
if (!in_array($_SESSION['role'] ?? '', ['Administrator','Asset Custodian'], true)) { http_response_code(403); exit('Access denied'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid batch document'); }
$stmt=$conn->prepare('SELECT id, po_number, created_at FROM accountability_batches WHERE id = ?'); $stmt->execute([$id]); $batch=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) { http_response_code(404); exit('Batch not found'); }
$stmt=$conn->prepare('SELECT asset_code, part_number, serial_number, employee_name FROM accountability_batch_items WHERE batch_id = ? ORDER BY id'); $stmt->execute([$id]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
function batch_h($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Batch Accountability BAA-<?= str_pad((string)$id,6,'0',STR_PAD_LEFT) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#e5e7eb;color:#111827;font:12px Arial,Helvetica,sans-serif}.toolbar{display:flex;justify-content:center;gap:12px;padding:16px}.toolbar button,.toolbar a{background:#1f2937;color:white;padding:9px 16px;border:0;border-radius:5px;text-decoration:none;cursor:pointer;font:inherit}.document{background:white;max-width:1120px;margin:0 auto 24px;padding:32px 36px;min-height:700px}.header{position:relative;min-height:85px;padding:6px 105px 0;text-align:center;border-bottom:2px solid #111827;margin-bottom:22px}.header-logo{position:absolute;left:0;top:0;width:92px;height:75px;display:flex;align-items:center}.header-logo img{max-width:90px;max-height:70px;object-fit:contain}.header h1{font-size:21px;text-transform:uppercase;margin:0 0 7px}.header h2{font-size:15px;margin:0 0 12px;font-weight:600}.meta{display:grid;grid-template-columns:1fr 1fr;gap:10px 24px;margin-bottom:22px}.meta strong{display:inline-block;min-width:120px}.section-title{padding:9px 11px;border:1px solid #111827;border-bottom:0;background:#f3f4f6;font-weight:bold;text-transform:uppercase}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #111827;text-align:left;vertical-align:middle;padding:9px 8px}th{background:#f9fafb;font-size:11px}td{height:54px;overflow-wrap:break-word}thead{display:table-header-group}tr{break-inside:avoid;page-break-inside:avoid}.num{width:5%;text-align:center}.model{width:21%}.serial{width:21%}.employee{width:25%}.signature{width:20%}.date{width:8%}.ack{line-height:1.55;margin:18px 0 0}.signoff{display:flex;gap:60px;margin-top:62px}.signoff>div{flex:1;border-top:1px solid #111827;text-align:center;padding-top:7px}.footer{margin-top:24px;font-size:10px;color:#4b5563}.reference{margin-top:22px;font-size:10px}.reference summary{cursor:pointer;font-weight:bold}.reference table{margin-top:8px}.reference td{height:auto;padding:5px 7px}.reference th{padding:6px 7px}@page{size:A4 landscape;margin:12mm}@media print{body{background:#fff}.toolbar{display:none}.document{margin:0;padding:0;max-width:none;min-height:0}.header{margin-bottom:16px}.reference{display:none}th,.section-title{-webkit-print-color-adjust:exact;print-color-adjust:exact}.signoff{break-inside:avoid}}
</style>
<style id="st-a4-print-layout">
/* A4 print density control: standard / compact / extra compact. */
@page { size: A4 landscape; margin: 9mm; }
.st-print-density-control { display:inline-flex; align-items:center; gap:7px; margin:0 8px; font:12px Arial,sans-serif; }
.st-print-density-control select { padding:6px; border:1px solid #999; border-radius:4px; background:white; color:#111; }
@media print {
 html, body { width:auto !important; margin:0 !important; }
 body { padding:0 !important; background:white !important; }
 .document { width:100% !important; max-width:none !important; min-height:0 !important; margin:0 !important; padding:0 !important; }
 .st-print-density-control, .toolbar, .print-buttons { display:none !important; }
 table { width:100%; border-collapse:collapse; }
 thead { display:table-header-group; }
 tfoot { display:table-footer-group; }
 tr { break-inside:avoid; page-break-inside:avoid; }
 img { max-width:100%; }
 body[data-print-density="compact"] { font-size:11px !important; }
 body[data-print-density="compact"] .document { font-size:11px !important; }
 body[data-print-density="compact"] .header { margin-bottom:12px !important; min-height:65px !important; }
 body[data-print-density="compact"] .header-logo { transform:scale(.8); transform-origin:top left; }
 body[data-print-density="compact"] h1 { font-size:17px !important; margin:3px 0 !important; }
 body[data-print-density="compact"] h2 { font-size:12px !important; margin:3px 0 !important; }
 body[data-print-density="compact"] th,body[data-print-density="compact"] td { padding:5px 6px !important; font-size:10px !important; }
 body[data-print-density="compact"] .section-title { margin:9px 0 5px !important; padding:5px !important; }
 body[data-print-density="extra"] { font-size:10px !important; }
 body[data-print-density="extra"] .document { font-size:10px !important; }
 body[data-print-density="extra"] .header { margin-bottom:8px !important; min-height:55px !important; }
 body[data-print-density="extra"] .header-logo { transform:scale(.7); transform-origin:top left; }
 body[data-print-density="extra"] h1 { font-size:15px !important; margin:2px 0 !important; }
 body[data-print-density="extra"] h2 { font-size:11px !important; margin:2px 0 !important; }
 body[data-print-density="extra"] th,body[data-print-density="extra"] td { padding:3px 5px !important; font-size:9px !important; }
 body[data-print-density="extra"] .section-title { margin:6px 0 4px !important; padding:4px !important; }
 /* Keep signature blocks intact when possible; do not shrink QR images. */
 .signoff, .signature-section, .signature-block { break-inside:avoid; }
}
</style>
</head><body>
<div class="toolbar"><label class="st-print-density-control" for="st-density">A4 layout <select id="st-density" aria-label="Print layout density"><option value="standard">Standard</option><option value="compact" selected>Compact</option><option value="extra">Extra compact</option></select></label><button type="button" onclick="window.print()">Print / Save PDF</button><a href="/st/frontend/pages/assignments.php">Back to Assignments</a></div>
<main class="document">
<header class="header">
<?php $logo = app_logo_url($conn); if ($logo !== ''): ?><div class="header-logo"><img src="<?= batch_h($logo) ?>" alt="Company logo"></div><?php endif; ?>
<h1><?= batch_h(app_name($conn)) ?></h1><h2>INITIAL BATCH ASSET ACCOUNTABILITY &amp; ACKNOWLEDGMENT</h2>
</header>
<div class="meta"><div><strong>Purchase Order No.:</strong> <?= batch_h($batch['po_number']) ?></div><div><strong>Document No.:</strong> BAA-<?= str_pad((string)$id,6,'0',STR_PAD_LEFT) ?></div><div><strong>Total Assets:</strong> <?= count($rows) ?></div><div><strong>Date Issued:</strong> <?= batch_h(date('F j, Y', strtotime($batch['created_at']))) ?></div></div>
<section><div class="section-title">Asset Assignment Details</div><table><thead><tr><th class="num">#</th><th class="model">Asset / Model</th><th class="serial">Serial Number</th><th class="employee">Assigned Employee</th><th class="signature">Signature</th><th class="date">Date</th></tr></thead><tbody>
<?php foreach ($rows as $index => $r): ?><tr><td class="num"><?= $index + 1 ?></td><td><?= batch_h($r['part_number']) ?></td><td><?= batch_h($r['serial_number']) ?></td><td><?= batch_h($r['employee_name']) ?></td><td></td><td></td></tr><?php endforeach; ?>
</tbody></table></section>
<p class="ack"><strong>Acknowledgment:</strong> By signing above, each employee acknowledges receipt of the asset listed in their corresponding row and accepts responsibility for its proper care and use in accordance with company policies.</p>
<div class="signoff"><div>Prepared / Issued By</div><div>Verified By</div></div>
<p class="footer">This document records the initial issuance. Later transfers and returns follow the normal asset lifecycle.</p>
<details class="reference"><summary>Asset code reference (on-screen only; not printed)</summary><table><thead><tr><th>#</th><th>Asset Code</th><th>Serial Number</th></tr></thead><tbody><?php foreach ($rows as $index => $r): ?><tr><td><?= $index + 1 ?></td><td><?= batch_h($r['asset_code']) ?></td><td><?= batch_h($r['serial_number']) ?></td></tr><?php endforeach; ?></tbody></table></details>
</main><script>
(function(){var control=document.getElementById('st-density');if(!control)return;document.body.dataset.printDensity=control.value;control.addEventListener('change',function(){document.body.dataset.printDensity=control.value;});})();
</script>
</body></html>
