<?php
require_once __DIR__ . "/backend/includes/security.php";
st_start_session();

require_once __DIR__ . "/backend/config/database.php";
require_once __DIR__ . "/backend/includes/app_config.php";

$token = trim($_GET["token"] ?? "");

if ($token === "") {
    http_response_code(400);
    die("Invalid asset QR code.");
}

$stmt = $conn->prepare("
    SELECT
        a.id, a.asset_code, a.asset_name, a.asset_type,
        a.status, a.condition_status, a.description,
        a.qr_enabled, c.name AS category_name,
        u.id AS current_user_id
    FROM assets a
    INNER JOIN categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.current_user_id = u.id
    WHERE a.qr_token = :token
    LIMIT 1
");
$stmt->execute([":token" => $token]);
$asset = $stmt->fetch();

if (!$asset || (int)$asset["qr_enabled"] !== 1) {
    http_response_code(404);
    die("This asset QR code is invalid or disabled.");
}

if (!empty($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    header("Location: /st/frontend/pages/asset_view.php?id=" . (int)$asset["id"]);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$app_name = app_name($conn);
$status_class = strtolower(str_replace(" ", "-", $asset["status"]));

?>
<!doctype html>
<html lang="en">
<head>

    <link rel="icon" type="image/svg+xml" href="/st/frontend/assets/icons/favicon-industrial.svg?v=20261002">
    <link rel="apple-touch-icon" sizes="180x180" href="/st/frontend/assets/icons/apple-touch-industrial.png?v=20261002">


<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Asset Verification - <?= htmlspecialchars($app_name) ?></title>
<style>
:root{font-family:Arial,sans-serif;color:#20272d;background:#eef0f2}
body{margin:0;padding:24px}.card{max-width:620px;margin:30px auto;background:#fff;border-radius:10px;padding:28px;box-shadow:0 12px 35px rgba(39,45,51,.12)}
h1{margin:0 0 6px;font-size:26px}.brand{font-weight:700;color:#b78100;margin-bottom:24px}.grid{display:grid;grid-template-columns:150px 1fr;gap:12px;border-top:1px solid #d0d5d9;padding-top:18px}.label{font-weight:700;color:#66717a}.value{color:#272d33}.status{display:inline-block;padding:5px 10px;border-radius:4px;background:#fff4ce;color:#644900}.note{margin-top:24px;padding:14px;border-radius:6px;background:#f3f4f5;color:#59636c;font-size:14px}.btn{display:inline-block;margin-top:20px;padding:11px 16px;border-radius:4px;background:#e0a800;color:#272d33;text-decoration:none;font-weight:700}@media(max-width:560px){body{padding:12px}.card{padding:20px;margin:10px auto}.grid{grid-template-columns:1fr;gap:4px}.label{margin-top:10px}}
</style>
</head>
<body>
<div class="card">
<div class="brand"><?= htmlspecialchars($app_name) ?></div>
<h1>Asset Verification</h1>
<p>This is a public, view-only asset verification page.</p>
<div class="grid">
<div class="label">Asset Code</div><div class="value"><strong><?= htmlspecialchars($asset["asset_code"]) ?></strong></div>
<div class="label">Asset Name</div><div class="value"><?= htmlspecialchars($asset["asset_name"]) ?></div>
<div class="label">Category</div><div class="value"><?= htmlspecialchars($asset["category_name"]) ?></div>
<div class="label">Asset Type</div><div class="value"><?= htmlspecialchars($asset["asset_type"]) ?></div>
<div class="label">Status</div><div class="value"><span class="status"><?= htmlspecialchars($asset["status"]) ?></span></div>
<div class="label">Condition</div><div class="value"><?= htmlspecialchars($asset["condition_status"] ?? "-") ?></div>
<div class="label">Accountability</div><div class="value"><?= (int)($asset["current_user_id"] ?? 0) > 0 ? "Assigned" : "Not assigned" ?></div>
</div>
<div class="note">Login to ST Asset Management to access the normal asset details page according to your assigned role.</div>
<a class="btn" href="<?= htmlspecialchars(app_base_url($conn)) ?>/login.php">Login to ST</a>
</div>
</body>
</html>
