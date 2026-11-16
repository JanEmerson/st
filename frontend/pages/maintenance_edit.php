<?php
$page_title = "Maintenance Work Order";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: maintenance.php?error=notfound");
    exit;
}

$stmt = $conn->prepare("
    SELECT m.*, a.asset_code, a.asset_name, a.status AS asset_status
    FROM maintenance_records m
    INNER JOIN assets a ON a.id = m.asset_id
    WHERE m.id = :id
    LIMIT 1
");
$stmt->execute([":id" => $id]);
$order = $stmt->fetch();
if (!$order) {
    header("Location: maintenance.php?error=notfound");
    exit;
}

$stmt = $conn->prepare("
    SELECT h.*, u.full_name AS performed_by_name
    FROM maintenance_history h
    INNER JOIN users u ON u.id = h.performed_by
    WHERE h.maintenance_id = :id
    ORDER BY h.event_at DESC, h.id DESC
");
$stmt->execute([":id" => $id]);
$events = $stmt->fetchAll();
$activeStatuses = ["Open", "In Progress", "Waiting for Parts"];
$terminal = !in_array($order["status"], $activeStatuses, true);
$success = ($_GET["success"] ?? "") === "updated";
?>

<div class="page-header">
    <div>
        <p><?= htmlspecialchars($order["work_order_no"] ?? "Work Order") ?> · <?= htmlspecialchars($order["asset_code"] . " - " . $order["asset_name"]) ?></p>
    </div>
    <div class="page-actions"><a href="maintenance.php" class="btn btn-secondary">Back to Work Orders</a></div>
</div>

<?php if ($success): ?><div class="alert alert-success" role="status">Work order updated.</div><?php endif; ?>
<?php if (($_GET["error"] ?? "") === "invalid"): ?><div class="alert alert-error" role="alert">Please review the work order details.</div><?php endif; ?>

<div class="form-container">
    <form method="POST" action="maintenance_save.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="maintenance_id" value="<?= (int)$order["id"] ?>">
        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="title">Issue or service title *</label>
                <input id="title" name="title" type="text" maxlength="150" required value="<?= htmlspecialchars($order["title"]) ?>">
            </div>
            <div class="form-group">
                <label for="maintenance_type">Type</label>
                <select id="maintenance_type" name="maintenance_type">
                    <?php foreach (["Repair", "Preventive", "Inspection", "Other"] as $type): ?>
                        <option value="<?= $type ?>" <?= $order["maintenance_type"] === $type ? "selected" : "" ?>><?= $type ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <?php foreach (["Low", "Normal", "High", "Urgent"] as $priority): ?>
                        <option value="<?= $priority ?>" <?= $order["priority"] === $priority ? "selected" : "" ?>><?= $priority ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <?php if ($terminal): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($order["status"]) ?>">
                    <input id="status" type="text" value="<?= htmlspecialchars($order["status"]) ?>" disabled>
                    <small class="secondary-text">Completed and cancelled work orders are closed.</small>
                <?php else: ?>
                    <select id="status" name="status">
                        <?php foreach ($activeStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= $order["status"] === $status ? "selected" : "" ?>><?= htmlspecialchars($status) ?></option>
                        <?php endforeach; ?>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="due_date">Target date</label>
                <input id="due_date" name="due_date" type="date" value="<?= htmlspecialchars($order["due_date"] ?? "") ?>">
            </div>
            <div class="form-group">
                <label for="vendor_name">Vendor / technician</label>
                <input id="vendor_name" name="vendor_name" type="text" maxlength="150" value="<?= htmlspecialchars($order["vendor_name"] ?? "") ?>">
            </div>
            <div class="form-group form-group-full">
                <label for="description">Problem or requested service</label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($order["description"] ?? "") ?></textarea>
            </div>
            <div class="form-group form-group-full">
                <label for="diagnosis">Diagnosis / findings</label>
                <textarea id="diagnosis" name="diagnosis" rows="3"><?= htmlspecialchars($order["diagnosis"] ?? "") ?></textarea>
            </div>
            <div class="form-group form-group-full">
                <label for="work_performed">Work performed</label>
                <textarea id="work_performed" name="work_performed" rows="3"><?= htmlspecialchars($order["work_performed"] ?? "") ?></textarea>
            </div>
            <div class="form-group">
                <label for="labor_cost">Labor cost</label>
                <input id="labor_cost" name="labor_cost" type="number" min="0" step="0.01" value="<?= htmlspecialchars($order["labor_cost"] ?? "") ?>">
            </div>
            <div class="form-group">
                <label for="parts_cost">Parts cost</label>
                <input id="parts_cost" name="parts_cost" type="number" min="0" step="0.01" value="<?= htmlspecialchars($order["parts_cost"] ?? "") ?>">
            </div>
            <div class="form-group form-group-full">
                <label for="attachment">Add attachment</label>
                <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <small class="secondary-text">PDF or image, maximum 5 MB. A new upload replaces the existing file.</small>
                <?php if (!empty($order["attachment_original_name"])): ?>
                    <p><a href="maintenance_attachment.php?id=<?= (int)$order["id"] ?>"><?= htmlspecialchars($order["attachment_original_name"]) ?></a></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Work Order</button>
            <a href="maintenance.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<div class="dashboard-section">
    <div class="section-header"><div><h2>Work Order History</h2><p>Creation and changes recorded for this service record.</p></div></div>
    <div class="table-container">
        <table>
            <thead><tr><th>Date</th><th>Event</th><th>Status</th><th>Performed By</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= htmlspecialchars(date("M d, Y h:i A", strtotime($event["event_at"]))) ?></td>
                        <td><?= htmlspecialchars($event["event_type"]) ?></td>
                        <td><?= htmlspecialchars(($event["old_status"] ? $event["old_status"] . " -> " : "") . ($event["new_status"] ?? "-")) ?></td>
                        <td><?= htmlspecialchars($event["performed_by_name"]) ?></td>
                        <td><?= nl2br(htmlspecialchars($event["remarks"] ?? "-")) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "../../backend/includes/footer.php"; ?>