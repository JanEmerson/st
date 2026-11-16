<?php
require_once "../../backend/includes/security.php";
st_start_session();
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/includes/app_config.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /st/frontend/pages/maintenance.php");
    exit;
}
require_csrf();

$action = $_POST["action"] ?? "";
$userId = (int)($_SESSION["user_id"] ?? 0);
$title = trim($_POST["title"] ?? "");
$type = trim($_POST["maintenance_type"] ?? "");
$priority = trim($_POST["priority"] ?? "");
$description = trim($_POST["description"] ?? "");
$vendor = trim($_POST["vendor_name"] ?? "");
$dueDate = trim($_POST["due_date"] ?? "");
$diagnosis = trim($_POST["diagnosis"] ?? "");
$workPerformed = trim($_POST["work_performed"] ?? "");
$status = trim($_POST["status"] ?? "Open");
$laborCost = trim($_POST["labor_cost"] ?? "");
$partsCost = trim($_POST["parts_cost"] ?? "");
$validTypes = ["Repair", "Preventive", "Inspection", "Other"];
$validPriorities = ["Low", "Normal", "High", "Urgent"];
$activeStatuses = ["Open", "In Progress", "Waiting for Parts"];
$allStatuses = array_merge($activeStatuses, ["Completed", "Cancelled"]);
$id = (int)($_POST["maintenance_id"] ?? 0);
$assetId = (int)($_POST["asset_id"] ?? 0);
$newFile = "";
$oldAttachment = "";
$uploadDirectory = dirname(__DIR__, 2) . "/uploads/app/maintenance";

$dateValid = $dueDate === "" || (($date = DateTime::createFromFormat("!Y-m-d", $dueDate)) && $date->format("Y-m-d") === $dueDate);
$costValid = static function (string $value): bool {
    return $value === "" || (is_numeric($value) && (float)$value >= 0 && (float)$value <= 9999999999.99);
};

if ($userId <= 0 || !in_array($action, ["create", "update"], true) || $title === "" || mb_strlen($title) > 150 || !in_array($type, $validTypes, true) || !in_array($priority, $validPriorities, true) || !$dateValid || !$costValid($laborCost) || !$costValid($partsCost)) {
    header("Location: /st/frontend/pages/maintenance.php?error=invalid");
    exit;
}

$file = $_FILES["attachment"] ?? null;
if ($file && (int)($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $mimeExtensions = ["application/pdf" => "pdf", "image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$file["size"] <= 0 || (int)$file["size"] > 5242880 || !is_uploaded_file($file["tmp_name"])) {
        header("Location: /st/frontend/pages/maintenance.php?error=invalid");
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);
    if (!isset($mimeExtensions[$mime])) {
        header("Location: /st/frontend/pages/maintenance.php?error=invalid");
        exit;
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        header("Location: /st/frontend/pages/maintenance.php?error=failed");
        exit;
    }
    $newFile = bin2hex(random_bytes(16)) . "." . $mimeExtensions[$mime];
    if (!move_uploaded_file($file["tmp_name"], $uploadDirectory . "/" . $newFile)) {
        header("Location: /st/frontend/pages/maintenance.php?error=failed");
        exit;
    }
}

$originalName = null;
if ($file && $newFile !== "") {
    $safeOriginalName = preg_replace('/[\x00-\x1F\x7F]/u', "", basename(str_replace("\\", "/", $file["name"])));
    $originalName = mb_substr($safeOriginalName ?: "attachment", 0, 255);
}
$redirect = "/st/frontend/pages/maintenance.php";

try {
    $conn->beginTransaction();

    if ($action === "create") {
        $stmt = $conn->prepare("SELECT id, status, current_user_id FROM assets WHERE id = :id FOR UPDATE");
        $stmt->execute([":id" => $assetId]);
        $asset = $stmt->fetch();
        if (!$asset || $asset["current_user_id"] !== null || !in_array($asset["status"], ["Available", "Maintenance"], true)) {
            throw new DomainException("assetbusy");
        }
        $stmt = $conn->prepare("SELECT id FROM maintenance_records WHERE asset_id = :asset_id AND status IN ('Open','In Progress','Waiting for Parts') LIMIT 1");
        $stmt->execute([":asset_id" => $assetId]);
        if ($stmt->fetch()) {
            throw new DomainException("assetbusy");
        }

        $stmt = $conn->prepare("
            INSERT INTO maintenance_records (
                asset_id, title, maintenance_type, priority, status, description,
                vendor_name, due_date, asset_status_before, attachment_filename,
                attachment_original_name, created_by, updated_by
            ) VALUES (
                :asset_id, :title, :type, :priority, 'Open', :description,
                :vendor, :due_date, :asset_status_before, :attachment, :original_name, :created_by, :updated_by
            )
        ");
        $stmt->execute([
            ":asset_id" => $assetId,
            ":title" => $title,
            ":type" => $type,
            ":priority" => $priority,
            ":description" => $description !== "" ? $description : null,
            ":vendor" => $vendor !== "" ? $vendor : null,
            ":due_date" => $dueDate !== "" ? $dueDate : null,
            ":asset_status_before" => $asset["status"],
            ":attachment" => $newFile !== "" ? $newFile : null,
            ":original_name" => $originalName,
            ":created_by" => $userId,
            ":updated_by" => $userId
        ]);
        $id = (int)$conn->lastInsertId();
        $workOrderNo = "MWO-" . str_pad((string)$id, 7, "0", STR_PAD_LEFT);
        $stmt = $conn->prepare("UPDATE maintenance_records SET work_order_no = :number WHERE id = :id");
        $stmt->execute([":number" => $workOrderNo, ":id" => $id]);
        $stmt = $conn->prepare("UPDATE assets SET status = 'Maintenance' WHERE id = :id AND current_user_id IS NULL");
        $stmt->execute([":id" => $assetId]);
        $stmt = $conn->prepare("INSERT INTO maintenance_history (maintenance_id, event_type, new_status, remarks, performed_by) VALUES (:id, 'Created', 'Open', :remarks, :user_id)");
        $stmt->execute([":id" => $id, ":remarks" => $title, ":user_id" => $userId]);
        $redirect = "/st/frontend/pages/maintenance_edit.php?id=" . $id . "&success=updated";
    } else {
        $stmt = $conn->prepare("SELECT asset_id FROM maintenance_records WHERE id = :id");
        $stmt->execute([":id" => $id]);
        $existingRef = $stmt->fetch();
        if (!$existingRef) {
            throw new DomainException("notfound");
        }
        $stmt = $conn->prepare("SELECT id, status, current_user_id FROM assets WHERE id = :id FOR UPDATE");
        $stmt->execute([":id" => (int)$existingRef["asset_id"]]);
        $asset = $stmt->fetch();
        $stmt = $conn->prepare("SELECT * FROM maintenance_records WHERE id = :id FOR UPDATE");
        $stmt->execute([":id" => $id]);
        $existing = $stmt->fetch();
        if (!$asset || !$existing || (int)$existing["asset_id"] !== (int)$asset["id"]) {
            throw new DomainException("notfound");
        }
        if ($newFile !== "") {
            $oldAttachment = $existing["attachment_filename"] ?? "";
        }
        $oldStatus = $existing["status"];
        $isTerminal = !in_array($oldStatus, $activeStatuses, true);
        if (!in_array($status, $allStatuses, true) || ($isTerminal && $status !== $oldStatus)) {
            throw new DomainException("invalid");
        }
        if (in_array($status, $activeStatuses, true) && ($asset["current_user_id"] !== null || !in_array($asset["status"], ["Maintenance", "Available"], true))) {
            throw new DomainException("assetbusy");
        }
        $stmt = $conn->prepare("SELECT id FROM maintenance_records WHERE asset_id = :asset_id AND id <> :id AND status IN ('Open','In Progress','Waiting for Parts') LIMIT 1");
        $stmt->execute([":asset_id" => (int)$asset["id"], ":id" => $id]);
        if ($stmt->fetch()) {
            throw new DomainException("assetbusy");
        }

        $attachmentSql = "";
        $params = [
            ":id" => $id,
            ":title" => $title,
            ":type" => $type,
            ":priority" => $priority,
            ":status" => $status,
            ":description" => $description !== "" ? $description : null,
            ":diagnosis" => $diagnosis !== "" ? $diagnosis : null,
            ":work_performed" => $workPerformed !== "" ? $workPerformed : null,
            ":vendor" => $vendor !== "" ? $vendor : null,
            ":due_date" => $dueDate !== "" ? $dueDate : null,
            ":labor_cost" => $laborCost !== "" ? $laborCost : null,
            ":parts_cost" => $partsCost !== "" ? $partsCost : null,
            ":completed_at" => $status === "Completed" ? ($existing["completed_at"] ?? date("Y-m-d H:i:s")) : null,
            ":user_id" => $userId
        ];
        if ($newFile !== "") {
            $attachmentSql = ", attachment_filename = :attachment, attachment_original_name = :original_name";
            $params[":attachment"] = $newFile;
            $params[":original_name"] = $originalName;
        }
        $stmt = $conn->prepare("
            UPDATE maintenance_records SET
                title = :title, maintenance_type = :type, priority = :priority,
                status = :status, description = :description, diagnosis = :diagnosis,
                work_performed = :work_performed, vendor_name = :vendor, due_date = :due_date,
                labor_cost = :labor_cost, parts_cost = :parts_cost, completed_at = :completed_at,
                updated_by = :user_id $attachmentSql
            WHERE id = :id
        ");
        $stmt->execute($params);

        if (in_array($status, $activeStatuses, true)) {
            $stmt = $conn->prepare("UPDATE assets SET status = 'Maintenance' WHERE id = :id AND current_user_id IS NULL");
            $stmt->execute([":id" => (int)$asset["id"]]);
        } elseif (!$isTerminal) {
            if ($asset["current_user_id"] !== null || !in_array($asset["status"], ["Maintenance", "Available", "Retired"], true)) {
                throw new DomainException("assetbusy");
            }
            $restoreStatus = $asset["status"] === "Retired"
                ? "Retired"
                : ($status === "Cancelled" ? $existing["asset_status_before"] : "Available");
            $stmt = $conn->prepare("UPDATE assets SET status = :status WHERE id = :id AND current_user_id IS NULL");
            $stmt->execute([":status" => $restoreStatus, ":id" => (int)$asset["id"]]);
        }

        $eventType = $oldStatus === $status ? "Updated" : "Status Changed";
        $remarks = $oldStatus === $status ? "Work order details updated." : "Status changed from " . $oldStatus . " to " . $status . ".";
        $stmt = $conn->prepare("INSERT INTO maintenance_history (maintenance_id, event_type, old_status, new_status, remarks, performed_by) VALUES (:id, :event, :old_status, :new_status, :remarks, :user_id)");
        $stmt->execute([
            ":id" => $id,
            ":event" => $eventType,
            ":old_status" => $oldStatus,
            ":new_status" => $status,
            ":remarks" => $remarks,
            ":user_id" => $userId
        ]);
        $redirect = "/st/frontend/pages/maintenance_edit.php?id=" . $id . "&success=updated";
    }

    $conn->commit();
    if ($oldAttachment !== "" && $oldAttachment !== $newFile) {
        $oldPath = $uploadDirectory . "/" . basename($oldAttachment);
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }
    header("Location: " . $redirect);
    exit;
} catch (DomainException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($newFile !== "" && is_file($uploadDirectory . "/" . $newFile)) {
        unlink($uploadDirectory . "/" . $newFile);
    }
    $error = $e->getMessage();
    $target = $action === "create" ? "maintenance_add.php" : "maintenance.php";
    header("Location: /st/frontend/pages/" . $target . "?error=" . rawurlencode($error));
    exit;
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($newFile !== "" && is_file($uploadDirectory . "/" . $newFile)) {
        unlink($uploadDirectory . "/" . $newFile);
    }
    header("Location: /st/frontend/pages/maintenance.php?error=failed");
    exit;
}