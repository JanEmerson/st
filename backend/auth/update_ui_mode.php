<?php
require_once __DIR__ . "/../includes/security.php";
st_start_session();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";
require_csrf();
header("Content-Type: application/json; charset=utf-8");
$mode = trim($_POST["mode"] ?? "");
if (!in_array($mode, ["light","dark"], true)) { http_response_code(422); echo json_encode(["success"=>false]); exit; }
$stmt=$conn->prepare("UPDATE users SET ui_theme_mode=:mode WHERE id=:id");
$stmt->execute([":mode"=>$mode,":id"=>(int)($_SESSION["user_id"]??0)]);
echo json_encode(["success"=>true]);
