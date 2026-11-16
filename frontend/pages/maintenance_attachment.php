<?php
require_once "../../backend/includes/security.php";
st_start_session();
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/includes/app_config.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit("Attachment not found.");
}

$stmt = $conn->prepare("SELECT attachment_filename, attachment_original_name FROM maintenance_records WHERE id = :id");
$stmt->execute([":id" => $id]);
$attachment = $stmt->fetch();

if (!$attachment) {
    http_response_code(404);
    exit("Attachment not found.");
}

$filename = basename($attachment["attachment_filename"] ?? "");
$path = dirname(__DIR__, 2) . "/uploads/app/maintenance/" . $filename;

if ($filename === "" || !is_file($path)) {
    http_response_code(404);
    exit("Attachment not found.");
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);
$allowedMimes = ["application/pdf", "image/jpeg", "image/png", "image/webp"];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(415);
    exit("Unsupported attachment.");
}

$displayName = basename(str_replace("\\", "/", $attachment["attachment_original_name"] ?? $filename));
header("Content-Type: " . $mime);
header("Content-Length: " . filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($displayName));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");
readfile($path);
exit;