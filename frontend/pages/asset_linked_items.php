<?php
$page_title = "Linked Items / Loose Tools";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

$allowed_roles = ["Administrator", "Asset Custodian"];
$role = $_SESSION["role"] ?? "";

if (!in_array($role, $allowed_roles, true)) {
    http_response_code(403);
    die("Access denied.");
}

$asset_id = filter_input(INPUT_GET, "asset_id", FILTER_VALIDATE_INT);
if (!$asset_id) {
    header("Location: assets.php?error=notfound");
    exit;
}

$stmt = $conn->prepare("SELECT a.id, a.asset_code, a.asset_name, c.name AS category_name FROM assets a INNER JOIN categories c ON a.category_id=c.id WHERE a.id=:id LIMIT 1");
$stmt->execute([":id" => $asset_id]);
$asset = $stmt->fetch();
if (!$asset) {
    header("Location: assets.php?error=notfound");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $item_code = trim($_POST["item_code"] ?? "");
        $item_name = trim($_POST["item_name"] ?? "");
        $item_type = trim($_POST["item_type"] ?? "Loose Tool");
        $serial_number = trim($_POST["serial_number"] ?? "");
        $quantity = max(1, (int)($_POST["quantity"] ?? 1));
        $condition_status = trim($_POST["condition_status"] ?? "Good");
        $remarks = trim($_POST["remarks"] ?? "");

        $valid_types = ["Loose Tool","Accessory","Component","Safety Equipment","Included Item","Other"];
        $valid_conditions = ["New","Good","Fair","Poor","Damaged"];

        if ($item_name === "") $error = "Item name is required.";
        elseif (!in_array($item_type, $valid_types, true)) $error = "Invalid linked item type.";
        elseif (!in_array($condition_status, $valid_conditions, true)) $error = "Invalid condition.";
        elseif ($quantity < 1) $error = "Quantity must be at least 1.";

        if ($error === "" && $item_code !== "") {
            $stmt = $conn->prepare("SELECT id FROM asset_linked_items WHERE item_code=:item_code AND status='With Asset' LIMIT 1");
            $stmt->execute([":item_code" => $item_code]);
            if ($stmt->fetch()) $error = "That item code is already linked to an active item.";
        }

        if ($error === "") {
            $stmt = $conn->prepare("INSERT INTO asset_linked_items (asset_id,item_code,item_name,item_type,serial_number,quantity,condition_status,status,remarks) VALUES (:asset_id,:item_code,:item_name,:item_type,:serial_number,:quantity,:condition_status,'With Asset',:remarks)");
            $stmt->execute([
                ":asset_id"=>$asset_id,
                ":item_code"=>$item_code !== "" ? $item_code : null,
                ":item_name"=>$item_name,
                ":item_type"=>$item_type,
                ":serial_number"=>$serial_number !== "" ? $serial_number : null,
                ":quantity"=>$quantity,
                ":condition_status"=>$condition_status,
                ":remarks"=>$remarks !== "" ? $remarks : null
            ]);
            $success = "Linked item added successfully.";
        }
    }

    if ($action === "remove") {
        $item_id = filter_input(INPUT_POST, "item_id", FILTER_VALIDATE_INT);
        if ($item_id) {
            $stmt = $conn->prepare("UPDATE asset_linked_items SET status='Removed' WHERE id=:id AND asset_id=:asset_id AND status='With Asset'");
            $stmt->execute([":id"=>$item_id,":asset_id"=>$asset_id]);
            $success = $stmt->rowCount() ? "Linked item marked as removed." : "Linked item was not changed.";
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM asset_linked_items WHERE asset_id=:asset_id ORDER BY status='With Asset' DESC, id ASC");
$stmt->execute([":asset_id"=>$asset_id]);
$items = $stmt->fetchAll();

?>

<div class="page-header">
    <div>
        <p>
            Manage loose tools, accessories, components and other items that belong with this asset.
        </p>
        <p class="secondary-text">
            <?= htmlspecialchars($asset["asset_code"]) ?> — <?= htmlspecialchars($asset["asset_name"]) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="asset_view.php?id=<?= (int)$asset_id ?>" class="btn btn-secondary">Back to Asset</a>
    </div>
</div>

<?php if ($error !== ""): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success !== ""): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="detail-card linked-items-card">
    <div class="detail-card-header">
        <div>
            <h3>Linked Items / Loose Tools</h3>
            <p class="secondary-text">These items remain attached to the main asset and can be included on accountability printouts.</p>
        </div>
    </div>

    <form method="POST" class="linked-item-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="form-group">
                <label for="item_code">Item Code</label>
                <input id="item_code" name="item_code" maxlength="50" placeholder="Optional, e.g. LT-001">
            </div>
            <div class="form-group">
                <label for="item_name">Item Name *</label>
                <input id="item_name" name="item_name" maxlength="150" required placeholder="e.g. Hydraulic Jack">
            </div>
            <div class="form-group">
                <label for="item_type">Item Type *</label>
                <select id="item_type" name="item_type" required>
                    <?php foreach (["Loose Tool","Accessory","Component","Safety Equipment","Included Item","Other"] as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="serial_number">Serial Number</label>
                <input id="serial_number" name="serial_number" maxlength="150" placeholder="Optional">
            </div>
            <div class="form-group">
                <label for="quantity">Quantity *</label>
                <input id="quantity" name="quantity" type="number" min="1" value="1" required>
            </div>
            <div class="form-group">
                <label for="condition_status">Condition *</label>
                <select id="condition_status" name="condition_status" required>
                    <?php foreach (["New","Good","Fair","Poor","Damaged"] as $condition): ?>
                        <option value="<?= htmlspecialchars($condition) ?>" <?= $condition === "Good" ? "selected" : "" ?>><?= htmlspecialchars($condition) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-group-full">
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" maxlength="1000" placeholder="Optional remarks or accountability notes"></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">+ Add Linked Item</button>
        </div>
    </form>
</div>

<div class="detail-card">
    <div class="detail-card-header"><h3>Items Attached to This Asset</h3></div>
    <?php $active_count = count(array_filter($items, fn($item) => $item["status"] === "With Asset")); ?>
    <p class="secondary-text"><?= $active_count ?> active linked item(s).</p>
    <?php if (!$items): ?>
        <div class="empty-state">No linked items have been recorded.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr>
                    <th>Item Code</th><th>Item Name</th><th>Type</th><th>Serial</th><th>Qty</th><th>Condition</th><th>Status</th><th>Remarks</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item["item_code"] ?: "-") ?></td>
                        <td><strong><?= htmlspecialchars($item["item_name"]) ?></strong></td>
                        <td><?= htmlspecialchars($item["item_type"]) ?></td>
                        <td><?= htmlspecialchars($item["serial_number"] ?: "-") ?></td>
                        <td><?= (int)$item["quantity"] ?></td>
                        <td><?= htmlspecialchars($item["condition_status"]) ?></td>
                        <td><?= htmlspecialchars($item["status"]) ?></td>
                        <td><?= htmlspecialchars($item["remarks"] ?: "-") ?></td>
                        <td>
                            <?php if ($item["status"] === "With Asset"): ?>
                                <form method="POST" onsubmit="return confirm('Mark this linked item as removed from the asset?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="item_id" value="<?= (int)$item["id"] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            <?php else: ?>
                                <span class="secondary-text">Historical</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once "../../backend/includes/footer.php"; ?>
