<?php
$page_title = "Assets";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";


/*
|--------------------------------------------------------------------------
| Current User Role
|--------------------------------------------------------------------------
*/

$role = current_user_role();


/*
|--------------------------------------------------------------------------
| Search and Filter
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");
$category_id = filter_input(
    INPUT_GET,
    "category_id",
    FILTER_VALIDATE_INT
);

$status = trim($_GET["status"] ?? "");
$asset_source = trim($_GET["asset_source"] ?? "");


/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        id,
        name
    FROM categories
    ORDER BY name ASC
");

$categories = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Build Asset Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,
        a.asset_source,
        a.condition_status,
        a.legacy_reference,
        a.date_added,
        a.supplier_name,
        a.purchase_number,
        a.purchase_date,
        a.purchase_cost,
        a.status,
        a.current_user_id,
        a.description,
        a.created_at,

        c.name AS category_name,

        u.full_name AS current_user_name

    FROM assets a

    INNER JOIN categories c
        ON a.category_id = c.id

    LEFT JOIN users u
        ON a.current_user_id = u.id

    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            a.asset_code LIKE :search
            OR a.asset_name LIKE :search
            OR a.asset_type LIKE :search
            OR a.serial_number LIKE :search
            OR a.supplier_name LIKE :search
            OR a.legacy_reference LIKE :search
        )
    ";

    $params[":search"] = "%" . $search . "%";
}


/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if ($category_id) {

    $sql .= "
        AND a.category_id = :category_id
    ";

    $params[":category_id"] = $category_id;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $status,
        [
            "Available",
            "Assigned",
            "Maintenance",
            "Retired"
        ],
        true
    )
) {

    $sql .= "
        AND a.status = :status
    ";

    $params[":status"] = $status;
}


/*
|--------------------------------------------------------------------------
| Asset Source Filter
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $asset_source,
        [
            "New Purchase",
            "Legacy / Existing",
            "Donated",
            "Transferred",
            "Other"
        ],
        true
    )
) {
    $sql .= "
        AND a.asset_source = :asset_source
    ";

    $params[":asset_source"] = $asset_source;
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY a.id DESC
";


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

$stmt->execute($params);

$assets = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$success = $_GET["success"] ?? "";
$error = $_GET["error"] ?? "";

?>

<div class="page-header">
    <div>
        <p>
            Manage and monitor all registered assets.
        </p>
    </div>


    <?php if (user_has_role($role, ["Administrator", "Asset Custodian"])): ?>

        <div class="page-actions">

            <a
                href="asset_add.php"
                class="btn btn-primary"
            >
                + Add Asset
            </a>

            <a href="asset_qr_print.php" class="btn btn-secondary">Print QR Labels</a>

            <a
                href="asset_import_export.php"
                class="btn btn-secondary"
            >
                Import / Export
            </a>

        </div>

    <?php endif; ?>

</div>


<?php if ($success === "added"): ?>

    <div class="alert alert-success">
        Asset added successfully.
    </div>

<?php endif; ?>


<?php if ($error === "notfound"): ?>

    <div class="alert alert-error">
        Asset not found.
    </div>

<?php endif; ?>


<!-- Search and Filters -->
<div class="filter-container">

    <form
        method="GET"
        action="assets.php"
        class="filter-form"
    >

        <div class="form-group">
            <label for="search">Search</label>

            <input
                type="text"
                id="search"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Asset code, name, serial/plate, legacy reference..."
            >
        </div>

        <div class="form-group">
            <label for="category_id">
                Category
            </label>

            <select
                id="category_id"
                name="category_id"
                data-searchable-select
                data-search-placeholder="Search categories..."
            >

                <option value="">
                    All Categories
                </option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category["id"] ?>"
                        <?= (
                            $category_id &&
                            (int) $category_id === (int) $category["id"]
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        <?= htmlspecialchars($category["name"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>


        <div class="form-group">
            <label for="status">
                Status
            </label>

            <select
                id="status"
                name="status"
            >

                <option value="">
                    All Statuses                   
                </option>

                <option
                    value="Available"
                    <?= $status === "Available" ? "selected" : "" ?>
                >
                    Available
                </option>

                <option
                    value="Assigned"
                    <?= $status === "Assigned" ? "selected" : "" ?>
                >
                    Assigned
                </option>

                <option
                    value="Maintenance"
                    <?= $status === "Maintenance" ? "selected" : "" ?>
                >
                    Maintenance
                </option>

                <option
                    value="Retired"
                    <?= $status === "Retired" ? "selected" : "" ?>
                >
                    Retired
                </option>
            </select>
        </div>


        <div class="form-group">
            <label for="asset_source">Asset Source</label>
            <select id="asset_source" name="asset_source">
                <option value="">All Sources</option>
                <?php foreach ([
                    "New Purchase",
                    "Legacy / Existing",
                    "Donated",
                    "Transferred",
                    "Other"
                ] as $source): ?>
                    <option
                        value="<?= htmlspecialchars($source) ?>"
                        <?= $asset_source === $source ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars($source) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>


        <div class="filter-actions">
            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>

            <a
                href="assets.php"
                class="btn btn-secondary"
            >
                Reset
            </a>
        </div>
    </form>
</div>


<!-- Asset Table -->
<div class="dashboard-section">
    <div class="section-header">
        <div>
            <h2>Asset Records</h2>

            <p><?= count($assets) ?> asset(s) found.</p>
        </div>
    </div>


    <div class="table-container">
        <table class="primary-info-table assets-table">
            <thead>
                <tr>
                    <th>Asset Code</th>
                    <th>Asset</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Serial / Plate</th>
                    <th>Source</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Current User</th>
                    <th>Actions</th>
                </tr>
            </thead>


            <tbody>
                <?php if (count($assets) > 0): ?>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($asset["asset_code"]) ?>
                                </strong>
                            </td>


                            <td>
                                <?= htmlspecialchars($asset["asset_name"]) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($asset["category_name"]) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($asset["asset_type"]) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $asset["serial_number"] ?? "-"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($asset["asset_source"] ?? "New Purchase") ?>
                                <?php if (($asset["asset_source"] ?? "") === "Legacy / Existing" && !empty($asset["legacy_reference"])): ?>
                                    <small class="secondary-text" style="display:block;">
                                        Ref: <?= htmlspecialchars($asset["legacy_reference"]) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($asset["condition_status"] ?? "-") ?>
                            </td>

                            <td>
                                <span class="status-badge status-<?= strtolower(
                                    $asset["status"]
                                ) ?>">

                                    <?= htmlspecialchars($asset["status"]) ?>
                                </span>
                            </td>


                            <td>
                                <?php if ($asset["current_user_name"]): ?>
                                    <?= htmlspecialchars(
                                        $asset["current_user_name"]
                                    ) ?>
                                <?php else: ?>

                                    <span class="secondary-text">
                                        Unassigned
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a
                                    href="asset_view.php?id=<?= (int) $asset["id"] ?>"
                                    class="btn btn-small btn-secondary"
                                >
                                    View
                                </a>

                                <?php if (
                                    in_array(
                                        $role,
                                        [
                                            "Administrator",
                                            "Asset Custodian"
                                        ],
                                        true
                                    )
                                ): ?>

                                    <a
                                        href="asset_edit.php?id=<?= (int) $asset["id"] ?>"
                                        class="btn-small btn-edit"
                                    >
                                        Edit
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td
                            colspan="8"
                            class="empty-state"
                        >
                            No assets found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php
require_once "../../backend/includes/footer.php";
?>
