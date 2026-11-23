<?php
$page_title="Print Center";
require_once "../../backend/includes/header.php";
?>
<h1>Print Center</h1><p>Use the existing approved printing pages. No document formats or QR identifiers have been changed.</p>
<div class="table-card" style="padding:24px;display:grid;gap:14px;max-width:800px">
<a class="btn btn-primary" href="asset_qr_print.php">QR labels — A4 / 4R / 2 × 2-inch sticker</a>
<a class="btn btn-secondary" href="assignments.php">Asset accountability and assignment</a>
<a class="btn btn-secondary" href="transfers.php">Asset transfers</a>
<a class="btn btn-secondary" href="turnovers.php">Asset turnover</a>
<a class="btn btn-secondary" href="clearance.php">Clearance documents</a>
<a class="btn btn-secondary" href="history.php">Asset history and reports</a>
</div>
<?php require_once "../../backend/includes/footer.php"; ?>
