<?php
$page_title = "Print Asset QR Labels";
require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/qr_helper.php";

function qr_label_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$requested = [];
if (isset($_GET["id"]) && ctype_digit((string)$_GET["id"])) {
    $requested[] = (int)$_GET["id"];
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ids"]) && is_array($_POST["ids"])) {
    foreach ($_POST["ids"] as $value) {
        if (ctype_digit((string)$value) && (int)$value > 0) $requested[] = (int)$value;
    }
}
$requested = array_slice(array_values(array_unique($requested)), 0, 200);
$paper = (string)($_POST["paper"] ?? $_GET["paper"] ?? "a4");
if (!in_array($paper, ["a4", "a4_4", "4r", "sticker"], true)) $paper = "a4";
$perPage = ["a4" => 20, "a4_4" => 4, "4r" => 4, "sticker" => 1][$paper];
$paperName = ["a4" => "A4 portrait (20 labels)", "a4_4" => "A4 portrait (4 labels)", "4r" => "4R (4 × 6 inches)", "sticker" => "2 × 2-inch sticker"][$paper];
$paperClass = "qrl-paper-" . $paper;
$selected = [];
if ($requested) {
    $placeholders = implode(",", array_fill(0, count($requested), "?"));
    $stmt = $conn->prepare("SELECT id, asset_code, asset_name, qr_token FROM assets WHERE id IN ($placeholders) AND qr_token IS NOT NULL AND qr_token <> ''");
    $stmt->execute($requested);
    $found = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $found[(int)$row["id"]] = $row;
    foreach ($requested as $id) if (isset($found[$id])) $selected[] = $found[$id];
}
$search = trim((string)($_GET["search"] ?? ""));
$available = [];
if (!$requested) {
    $query = "SELECT id, asset_code, asset_name FROM assets WHERE qr_token IS NOT NULL AND qr_token <> ''";
    $params = [];
    if ($search !== "") {
        $query .= " AND (asset_code LIKE ? OR asset_name LIKE ? OR serial_number LIKE ?)";
        $params = array_fill(0, 3, "%" . $search . "%");
    }
    $query .= " ORDER BY asset_code ASC LIMIT 500";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $available = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<style>
.qrl-wrap{max-width:1100px;margin:18px auto 32px;padding:0 12px}
.qrl-toolbar{padding:16px;border:1px solid #d3dbe1;border-radius:10px;margin-bottom:16px}
.qrl-toolbar form{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.qrl-toolbar input[type=search]{padding:9px;min-width:220px;max-width:100%;flex:1}
.qrl-list{max-height:360px;overflow:auto;border:1px solid #d3dbe1;border-radius:8px;margin:12px 0}
.qrl-list label{display:flex;gap:12px;align-items:center;padding:9px 12px;border-bottom:1px solid #e5e7eb}
.qrl-list label:last-child{border:0}
.qrl-list input{width:17px;height:17px}
.qrl-note{font-size:13px;color:var(--color-text-muted,#64748b)}
.qrl-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.qrl-sheet{width:210mm;min-height:297mm;box-sizing:border-box;background:#fff;color:#111;margin:12px auto;padding:5mm 3.4mm;display:grid;grid-template-columns:repeat(4,50.8mm);grid-template-rows:repeat(5,50.8mm);column-gap:0;row-gap:3mm;align-content:start;box-shadow:0 1px 8px #0002}
/* Four large labels use most of the A4 sheet; other modes retain fixed 2-inch labels. */
.qrl-paper-a4_4{grid-template-columns:repeat(2,minmax(0,1fr));grid-template-rows:repeat(2,minmax(0,1fr));justify-content:stretch;align-content:stretch;column-gap:4mm;row-gap:4mm;padding:7mm}
.qrl-paper-a4_4 .qrl-label{width:100%;height:100%;padding:3mm;border:0.2mm dashed #cbd5e1}
.qrl-paper-a4_4 .qrl-label img{width:77mm;height:77mm;max-width:100%;max-height:calc(100% - 15mm)}
.qrl-paper-a4_4 .qrl-code{font-size:14px;max-width:100%;margin-top:2mm}
.qrl-paper-a4_4 .qrl-name{font-size:11px;max-width:100%;max-height:12mm;margin-top:1mm}
.qrl-paper-4r{width:101.6mm;min-height:152.4mm;grid-template-columns:repeat(2,50.8mm);grid-template-rows:repeat(2,50.8mm);padding:25.4mm 0;justify-content:center;align-content:center;column-gap:0;row-gap:0}
.qrl-paper-sticker{width:50.8mm;min-height:50.8mm;grid-template-columns:50.8mm;grid-template-rows:50.8mm;padding:0;place-content:center;place-items:center;box-shadow:0 1px 8px #0002}
.qrl-label{width:50.8mm;height:50.8mm;box-sizing:border-box;border:0.2mm dashed #cbd5e1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:1mm;overflow:hidden;font-family:Arial,sans-serif}
.qrl-label img{width:35mm;height:35mm;object-fit:contain;display:block;margin-left:auto;margin-right:auto;flex-shrink:0}
.qrl-code{font-size:9px;font-weight:700;line-height:1.1;max-width:47mm;overflow-wrap:anywhere;margin-top:.5mm}
.qrl-name{font-size:7px;line-height:1.1;max-width:47mm;overflow-wrap:anywhere;max-height:5mm;overflow:hidden;margin-top:.4mm}
.qrl-print-disabled{opacity:.6}
@page{size:<?= $paper === "4r" ? "4in 6in" : ($paper === "sticker" ? "2in 2in" : "A4 portrait") ?>;margin:0}
.qrl-preview-area{overflow-x:auto;padding:8px 0 20px}
.qrl-paper-sticker.qrl-sheet{margin:35px auto 75px;outline:1px solid #d1d5db;transform:scale(1.8);transform-origin:center center}
/* Keep the QR image and its caption centered as one unit within the sticker. */
.qrl-paper-sticker .qrl-label{border-color:#d1d5db;justify-self:center;align-self:center;justify-content:center;align-items:center;padding:1mm}
.qrl-paper-sticker .qrl-code,.qrl-paper-sticker .qrl-name{text-align:center;margin-left:auto;margin-right:auto}
.qrl-preview-hint{font-size:13px;color:var(--color-text-muted,#64748b);margin:10px 0}

@media print{
 html,body{margin:0!important;padding:0!important;background:#fff!important}
 .sidebar,.topbar,.qrl-toolbar,.qrl-actions,.qrl-note,.qrl-heading{display:none!important}
 .app,.main-area,.content,.qrl-wrap{margin:0!important;padding:0!important;width:auto!important;max-width:none!important;min-height:0!important;height:auto!important;overflow:visible!important}
 .qrl-preview-area{overflow:visible!important;padding:0!important}
 .qrl-paper-sticker.qrl-sheet{transform:none!important;outline:none!important}
 .qrl-sheet{width:210mm!important;height:297mm!important;min-height:297mm!important;margin:0!important;padding:5mm 3.4mm!important;box-shadow:none!important;break-after:page;page-break-after:always}
 .qrl-paper-a4_4{grid-template-columns:repeat(2,minmax(0,1fr))!important;grid-template-rows:repeat(2,minmax(0,1fr))!important;justify-content:stretch!important;align-content:stretch!important;column-gap:4mm!important;row-gap:4mm!important;padding:7mm!important}
 .qrl-paper-4r{width:101.6mm!important;height:152.4mm!important;min-height:152.4mm!important;padding:25.4mm 0!important;grid-template-columns:repeat(2,50.8mm)!important;grid-template-rows:repeat(2,50.8mm)!important;justify-content:center!important;align-content:center!important;column-gap:0!important;row-gap:0!important}
 .qrl-paper-sticker{width:50.8mm!important;height:50.8mm!important;min-height:50.8mm!important;padding:0!important;grid-template-columns:50.8mm!important;grid-template-rows:50.8mm!important;place-content:center!important;place-items:center!important}
 .qrl-sheet:last-child{break-after:auto;page-break-after:auto}
 .qrl-label{break-inside:avoid;page-break-inside:avoid;border-color:transparent!important;justify-content:center!important;align-items:center!important}
 .qrl-wrap,.qrl-preview-area,.qrl-sheet{position:static!important}
 .qrl-sheet{overflow:hidden!important}

}
@media screen and (max-width:900px){
 .qrl-sheet:not(.qrl-paper-sticker){transform-origin:top left;transform:scale(.65);margin-bottom:-95mm}
 .qrl-paper-sticker.qrl-sheet{transform:scale(1.35);margin:20px auto 55px}
}

</style>
<div class="qrl-wrap">
<?php if (!$requested): ?>
 <div class="qrl-toolbar">
  <h2>Print Asset QR Labels</h2>
  <p class="qrl-note">Choose assets and preview labels. The A4 four-label option uses enlarged labels; other modes use fixed 2 × 2-inch labels. A4 holds either 20 or 4 labels; 4R holds 4; sticker paper holds 1.</p>
  <form method="get"><input type="search" name="search" value="<?= qr_label_escape($search) ?>" placeholder="Search asset code, name, or serial"><button class="btn btn-secondary" type="submit">Search</button></form>
  <form method="post" id="qrlSelectForm" style="display:block">
   <div class="qrl-actions"><label><input type="checkbox" id="qrlAll"> Select all shown</label><span id="qrlCount">0 selected</span></div>
   <div class="qrl-list">
    <?php foreach ($available as $item): ?>
     <label><input type="checkbox" name="ids[]" value="<?= (int)$item["id"] ?>" class="qrl-check"><span><strong><?= qr_label_escape($item["asset_code"]) ?></strong> — <?= qr_label_escape($item["asset_name"]) ?></span></label>
    <?php endforeach; ?>
    <?php if (!$available): ?><p style="padding:12px">No assets with QR codes found.</p><?php endif; ?>
   </div>
   <p class="qrl-note">Showing up to 500 matching assets. You can print up to 200 labels at once.</p>
   <label for="qrlPaper" style="display:inline-flex;align-items:center;gap:8px;margin:8px 12px 8px 0">Paper size
     <select name="paper" id="qrlPaper" style="padding:9px"><option value="a4">A4 — 20 labels</option><option value="a4_4">A4 — 4 large labels</option><option value="4r">4R (4 × 6 in) — 4 labels</option><option value="sticker">2 × 2 in sticker — 1 label</option></select>
   </label>
   <button type="submit" class="btn btn-primary">Preview Selected Labels</button>
   <a href="assets.php" class="btn btn-secondary">Back to Assets</a>
  </form>
 </div>
<?php else: ?>
 <div class="qrl-toolbar">
  <h2><?= count($selected) ?> QR label(s) ready — <?= qr_label_escape($paperName) ?></h2>
  <p class="qrl-note"><?= $paper === "a4_4" ? "Four enlarged labels using most of the A4 sheet" : "Exactly 2 × 2 inches per label" ?> · <?= (int)$perPage ?> per page. In the browser print dialog select <?= qr_label_escape($paperName) ?>, 100% scale, and disable headers/footers. Print a test sheet first. For 4R with four full-size labels, borderless printing is required to avoid clipping at the left and right edges.</p>
  <form method="post" id="qrlPaperChange" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:12px 0">
   <?php foreach ($requested as $assetId): ?><input type="hidden" name="ids[]" value="<?= (int)$assetId ?>"><?php endforeach; ?>
   <label for="qrlPreviewPaper">Paper size</label>
   <select id="qrlPreviewPaper" name="paper" onchange="this.form.submit()" style="padding:9px">
    <option value="a4" <?= $paper === "a4" ? "selected" : "" ?>>A4 — 20 labels per sheet</option><option value="a4_4" <?= $paper === "a4_4" ? "selected" : "" ?>>A4 — 4 large labels per sheet</option>
    <option value="4r" <?= $paper === "4r" ? "selected" : "" ?>>4R — 4 labels per sheet</option>
    <option value="sticker" <?= $paper === "sticker" ? "selected" : "" ?>>2 × 2 in sticker — 1 per sheet</option>
   </select>
  </form>
  <div class="qrl-actions"><button type="button" id="qrlPrint" class="btn btn-primary" disabled>Loading QR images...</button><a href="asset_qr_print.php" class="btn btn-secondary">Choose Different Assets</a></div>
 </div>
 <p class="qrl-preview-hint"><?= $paper === "sticker" ? "Sticker preview enlarged on screen for readability. The printed label remains exactly 2 × 2 inches." : "Preview shows actual paper proportions. The printer must support the chosen paper size." ?></p>
 <div class="qrl-preview-area">
 <?php foreach (array_chunk($selected, $perPage) as $sheet): ?>
 <div class="qrl-sheet <?= qr_label_escape($paperClass) ?>">
  <?php foreach ($sheet as $item):
   $qrLink = asset_qr_url($conn, $item["qr_token"]);
   $qrImage = asset_qr_image_url($qrLink, 500);
  ?>
   <div class="qrl-label">
    <img class="qrl-img" src="<?= qr_label_escape($qrImage) ?>" alt="QR code for <?= qr_label_escape($item["asset_code"]) ?>">
    <div class="qrl-code"><?= qr_label_escape($item["asset_code"]) ?></div>
    <div class="qrl-name"><?= qr_label_escape($item["asset_name"]) ?></div>
   </div>
  <?php endforeach; ?>
 </div>
 <?php endforeach; ?>
 </div>
 <?php if (!$selected): ?><p>No valid QR assets were selected.</p><?php endif; ?>
<?php endif; ?>
</div>
<script>
(function(){
 const all=document.getElementById('qrlAll'), checks=[...document.querySelectorAll('.qrl-check')], count=document.getElementById('qrlCount');
 function update(){if(count)count.textContent=checks.filter(x=>x.checked).length+' selected';if(all)all.checked=checks.length>0&&checks.every(x=>x.checked)}
 if(all)all.addEventListener('change',()=>{checks.forEach(x=>x.checked=all.checked);update()});
 checks.forEach(x=>x.addEventListener('change',update));update();

 const btn=document.getElementById('qrlPrint'), imgs=[...document.querySelectorAll('.qrl-img')];
 if(!btn)return;
 Promise.all(imgs.map(img=>new Promise(resolve=>{if(img.complete)return resolve(img.naturalWidth>0);img.addEventListener('load',()=>resolve(true),{once:true});img.addEventListener('error',()=>resolve(false),{once:true})}))).then(results=>{
  if(results.every(Boolean)&&results.length){btn.disabled=false;btn.textContent='Print Labels';btn.addEventListener('click',()=>window.print())}
  else{btn.textContent='Some QR images failed to load — refresh before printing'}
 });
})();
</script>
<?php require_once "../../backend/includes/footer.php"; ?>
