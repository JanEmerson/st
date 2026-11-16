<?php
$page_title = "Maintenance Work Orders";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);

$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");
$allowedStatuses = ["Open", "In Progress", "Waiting for Parts", "Completed", "Cancelled"];

$sql = "
    SELECT
        m.id,
        m.work_order_no,
        m.title,
        m.maintenance_type,
        m.priority,
        m.status,
        m.due_date,
        m.opened_at,
        a.id AS asset_id,
        a.asset_code,
        a.asset_name,
        u.full_name AS created_by_name
    FROM maintenance_records m
    INNER JOIN assets a ON a.id = m.asset_id
    INNER JOIN users u ON u.id = m.created_by
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (m.work_order_no LIKE :search OR m.title LIKE :search OR a.asset_code LIKE :search OR a.asset_name LIKE :search OR m.vendor_name LIKE :search)";
    $params[":search"] = "%" . $search . "%";
}

if ($status === "Active") {
    $sql .= " AND m.status IN ('Open','In Progress','Waiting for Parts')";
} elseif (in_array($status, $allowedStatuses, true)) {
    $sql .= " AND m.status = :status";
    $params[":status"] = $status;
}

$sql .= " ORDER BY CASE WHEN m.status IN ('Open','In Progress','Waiting for Parts') THEN 0 ELSE 1 END, m.due_date IS NULL, m.due_date ASC, m.opened_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$workOrders = $stmt->fetchAll();

$summary = $conn->query("
    SELECT
        SUM(status IN ('Open','In Progress','Waiting for Parts')) AS active_count,
        SUM(status = 'Waiting for Parts') AS waiting_count,
        SUM(status = 'Completed') AS completed_count
    FROM maintenance_records
")->fetch();

$success = $_GET["success"] ?? "";
$error = $_GET["error"] ?? "";
?>

<div class="page-header">
    <div>
        <p>Track repairs, inspections, preventive service, and maintenance costs.</p>
    </div>
    <div class="page-actions">
        <a href="maintenance_add.php" class="btn btn-primary">New Work Order</a>
    </div>
</div>

<?php if ($success === "created" || $success === "updated"): ?>
    <div class="alert alert-success" role="status">Work order <?= $success === "created" ? "created" : "updated" ?>.</div>
<?php elseif ($error === "invalid"): ?>
    <div class="alert alert-error" role="alert">Please review the work order details and try again.</div>
<?php elseif ($error === "assetbusy"): ?>
    <div class="alert alert-error" role="alert">That asset is assigned, retired, or already has an active work order.</div>
<?php elseif ($error === "notfound"): ?>
    <div class="alert alert-error" role="alert">The requested work order or asset could not be found.</div>
<?php elseif ($error === "failed"): ?>
    <div class="alert alert-error" role="alert">The work order could not be saved.</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Active Work Orders</div><div class="stat-number"><?= (int)($summary["active_count"] ?? 0) ?></div></div>
    <div class="stat-card"><div class="stat-label">Waiting for Parts</div><div class="stat-number"><?= (int)($summary["waiting_count"] ?? 0) ?></div></div>
    <div class="stat-card"><div class="stat-label">Completed</div><div class="stat-number"><?= (int)($summary["completed_count"] ?? 0) ?></div></div>
</div>

<div class="filter-container">
    <form method="GET" class="filter-form">
        <div class="form-group">
            <label for="search">Search</label>
            <input type="search" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Work order, asset, vendor">
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <option value="Active" <?= $status === "Active" ? "selected" : "" ?>>Active work orders</option>
                <?php foreach ($allowedStatuses as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>" <?= $status === $option ? "selected" : "" ?>><?= htmlspecialchars($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="maintenance.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="primary-info-table">
            <thead><tr><th>Work Order</th><th>Asset</th><th>Issue</th><th>Type</th><th>Priority</th><th>Status</th><th>Due</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($workOrders): ?>
                    <?php foreach ($workOrders as $order): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($order["work_order_no"] ?? "Pending") ?></strong><br><small><?= htmlspecialchars(date("M d, Y", strtotime($order["opened_at"]))) ?></small></td>
                            <td><a href="asset_view.php?id=<?= (int)$order["asset_id"] ?>"><?= htmlspecialchars($order["asset_code"]) ?></a><br><?= htmlspecialchars($order["asset_name"]) ?></td>
                            <td><?= htmlspecialchars($order["title"]) ?></td>
                            <td><?= htmlspecialchars($order["maintenance_type"]) ?></td>
                            <td><?= htmlspecialchars($order["priority"]) ?></td>
                            <td><span class="status-badge"><?= htmlspecialchars($order["status"]) ?></span></td>
                            <td><?= $order["due_date"] ? htmlspecialchars(date("M d, Y", strtotime($order["due_date"]))) : "-" ?></td>
                            <td><a href="maintenance_edit.php?id=<?= (int)$order["id"] ?>" class="btn btn-secondary btn-small">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">No work orders match these filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "../../backend/includes/footer.php"; ?>