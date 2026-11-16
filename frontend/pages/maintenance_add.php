<?php
$page_title = "New Maintenance Work Order";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);

$stmt = $conn->query("
    SELECT a.id, a.asset_code, a.asset_name, a.status
    FROM assets a
    WHERE a.status IN ('Available','Maintenance')
      AND a.current_user_id IS NULL
      AND NOT EXISTS (
          SELECT 1 FROM maintenance_records m
          WHERE m.asset_id = a.id
            AND m.status IN ('Open','In Progress','Waiting for Parts')
      )
    ORDER BY a.asset_code ASC
");
$assets = $stmt->fetchAll();
$selectedAssetId = filter_input(INPUT_GET, "asset_id", FILTER_VALIDATE_INT) ?: 0;
$error = $_GET["error"] ?? "";
?>

<div class="page-header">
    <div><p>Open a service record for an unassigned asset.</p></div>
    <div class="page-actions"><a href="maintenance.php" class="btn btn-secondary">Back to Work Orders</a></div>
</div>

<?php if ($error === "assetbusy"): ?>
    <div class="alert alert-error" role="alert">This asset is no longer eligible for a new work order.</div>
<?php elseif ($error === "invalid"): ?>
    <div class="alert alert-error" role="alert">Please check the required fields and attachment, then try again.</div>
<?php endif; ?>

<div class="transaction-guide">
    <div class="transaction-guide-title">Opening a maintenance work order</div>
    <div class="transaction-steps">
        <span>1. Select asset</span><span>→</span><span class="is-current">2. Describe the problem</span><span>→</span><span>3. Set priority</span><span>→</span><span>4. Create work order</span>
    </div>
    <p>Creating the work order places the asset under <strong>Maintenance</strong>, so it cannot be assigned while the active work order is open.</p>
</div>

<div class="form-container">
    <form method="POST" action="maintenance_save.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="asset_id">Asset *</label>
                <small class="secondary-text">Choose an unassigned Available or already-Maintenance asset. Assigned and Retired assets cannot start a work order.</small>
                <select id="asset_id" name="asset_id" data-searchable-select data-search-placeholder="Search by asset code or name..." required>
                    <option value="">Select an asset</option>
                    <?php foreach ($assets as $asset): ?>
                        <option value="<?= (int)$asset["id"] ?>" <?= $selectedAssetId === (int)$asset["id"] ? "selected" : "" ?>><?= htmlspecialchars($asset["asset_code"] . " - " . $asset["asset_name"]) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$assets): ?><small class="secondary-text">No eligible assets are available. Assigned and retired assets cannot be opened for maintenance.</small><?php endif; ?>
            </div>
            <div class="form-group form-group-full">
                <label for="title">Issue or service title *</label>
                <small class="secondary-text">Use a short, specific title such as “Laptop will not power on” or “Preventive service”.</small>
                <input id="title" name="title" type="text" maxlength="150" required value="<?= htmlspecialchars($_POST["title"] ?? "") ?>">
            </div>
            <div class="form-group">
                <label for="maintenance_type">Type</label>
                <small class="secondary-text">Repair = fix a fault; Preventive = scheduled service; Inspection = checking condition; Other = other service activity.</small>
                <select id="maintenance_type" name="maintenance_type">
                    <?php foreach (["Repair", "Preventive", "Inspection", "Other"] as $type): ?>
                        <option value="<?= $type ?>" <?= ($_POST["maintenance_type"] ?? "Repair") === $type ? "selected" : "" ?>><?= $type ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <small class="secondary-text">Use Urgent only when immediate attention is required. High, Normal, and Low help the team prioritize work.</small>
                <select id="priority" name="priority">
                    <?php foreach (["Low", "Normal", "High", "Urgent"] as $priority): ?>
                        <option value="<?= $priority ?>" <?= ($_POST["priority"] ?? "Normal") === $priority ? "selected" : "" ?>><?= $priority ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="due_date">Target date</label>
                <small class="secondary-text">Expected completion date. The dashboard can use this to identify due or overdue work.</small>
                <input id="due_date" name="due_date" type="date" value="<?= htmlspecialchars($_POST["due_date"] ?? "") ?>">
            </div>
            <div class="form-group">
                <label for="vendor_name">Vendor / technician</label>
                <small class="secondary-text">Enter the person or company performing or responsible for the service.</small>
                <input id="vendor_name" name="vendor_name" type="text" maxlength="150" value="<?= htmlspecialchars($_POST["vendor_name"] ?? "") ?>">
            </div>
            <div class="form-group form-group-full">
                <label for="description">Problem or requested service</label>
                <small class="secondary-text">Describe the reported problem, requested service, symptoms, or initial findings.</small>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST["description"] ?? "") ?></textarea>
            </div>
            <div class="form-group form-group-full">
                <label for="attachment">Attachment</label>
                <small class="secondary-text">Attach a service report, quotation, photo, receipt, or other supporting document.</small>
                <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <small class="secondary-text">PDF or image, maximum 5 MB.</small>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" <?= !$assets ? "disabled" : "" ?>>Create Work Order</button>
            <a href="maintenance.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once "../../backend/includes/footer.php"; ?>