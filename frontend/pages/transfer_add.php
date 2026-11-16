<?php
$page_title = "Transfer Asset";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);

$asset_id = (int)($_GET["id"] ?? 0);

if ($asset_id <= 0) {
    header("Location: /st/frontend/pages/transfers.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Asset and Current Employee
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,
        a.status,
        a.current_user_id,

        u.employee_id,
        u.full_name,
        u.department,
        u.position

    FROM assets a

    INNER JOIN users u
        ON a.current_user_id = u.id

    WHERE a.id = :asset_id
      AND a.status = 'Assigned'
      AND a.current_user_id IS NOT NULL

    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ":asset_id" => $asset_id
]);

$asset = $stmt->fetch();

if (!$asset) {
    header("Location: /st/frontend/pages/transfers.php?error=notfound");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Active Employees Except Current Employee
|--------------------------------------------------------------------------
*/

$sql_users = "
    SELECT
        id,
        employee_id,
        full_name,
        department,
        position

    FROM users

    WHERE status = 'Active'
      AND id != :current_user_id

    ORDER BY full_name ASC
";

$stmt_users = $conn->prepare($sql_users);

$stmt_users->execute([
    ":current_user_id" => $asset["current_user_id"]
]);

$users = $stmt_users->fetchAll();

?>

<div class="page-header">

<div>
    <p>Transfer this asset from the current employee to another employee.</p>
</div>

<div>

    <a
        href="/st/frontend/pages/transfers.php"
        class="btn btn-secondary"
    >
        Back to Transfers
    </a>

</div>

</div>

    <div class="detail-card">

    <div class="detail-card-header">

        <h3>Asset Information</h3>

    </div>


<div class="detail-grid">

    <div class="detail-item">

        <span class="detail-label">
            Asset Code
        </span>

        <strong>
            <?= htmlspecialchars($asset["asset_code"]) ?>
        </strong>

    </div>


    <div class="detail-item">

        <span class="detail-label">
            Asset Name
        </span>

        <?= htmlspecialchars($asset["asset_name"]) ?>

    </div>


    <div class="detail-item">

        <span class="detail-label">
            Asset Type
        </span>

        <?= htmlspecialchars($asset["asset_type"]) ?>

    </div>


    <div class="detail-item">

        <span class="detail-label">
            Serial Number
        </span>

        <?= htmlspecialchars($asset["serial_number"] ?? "-") ?>

    </div>

</div>

</div>

<div class="detail-card">

<div class="detail-card-header">

    <h3>Current Accountability</h3>

</div>


<div class="detail-grid">

    <div class="detail-item">

        <span class="detail-label">
            Employee ID
        </span>

        <?= htmlspecialchars($asset["employee_id"]) ?>

    </div>


    <div class="detail-item">

        <span class="detail-label">
            Employee Name
        </span>

        <?= htmlspecialchars($asset["full_name"]) ?>

    </div>


    <div class="detail-item">

        <span class="detail-label">
            Department
        </span>

        <?= htmlspecialchars($asset["department"] ?? "-") ?>

    </div>


    <div class="detail-item">

        <span class="detail-label">
            Position
        </span>

        <?= htmlspecialchars($asset["position"] ?? "-") ?>

    </div>

</div>

</div>

<div class="transaction-guide">
    <div class="transaction-guide-title">Transferring an asset</div>
    <div class="transaction-steps">
        <span>1. Review current employee</span><span>→</span><span class="is-current">2. Select new employee</span><span>→</span><span>3. Check condition</span><span>→</span><span>4. Confirm transfer</span>
    </div>
    <p>The asset remains <strong>Assigned</strong>, but accountability moves to the selected employee. The previous employee remains visible in the transaction history.</p>
</div>

<div class="form-card">

<div class="form-card-header">

    <h3>Transfer Details</h3>

</div>


<form
    method="POST"
    action="/st/frontend/pages/transfer_save.php"
>
            <?= csrf_field() ?>

    <input
        type="hidden"
        name="asset_id"
        value="<?= (int)$asset["id"] ?>"
    >


    <div class="form-group">

        <label for="to_user_id">

            Transfer To
            <span class="required">*</span>

        </label>
        <small class="secondary-text">Choose the new accountable employee. The current employee is shown above for comparison.</small>


        <select
            id="to_user_id"
            name="to_user_id"
            data-searchable-select
            data-search-placeholder="Search by employee, ID or department..."
            required
        >

            <option value="">
                -- Select Employee --
            </option>


            <?php foreach ($users as $user): ?>

                <option
                    value="<?= (int)$user["id"] ?>"
                >

                    <?= htmlspecialchars($user["full_name"]) ?>

                    -
                    <?= htmlspecialchars($user["employee_id"]) ?>

                    <?php if (!empty($user["department"])): ?>

                        -
                        <?= htmlspecialchars($user["department"]) ?>

                    <?php endif; ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="form-group">

        <label for="condition_status">

            Asset Condition
            <span class="required">*</span>

        </label>
        <small class="secondary-text">Record the condition at the moment of transfer.</small>


        <select
            id="condition_status"
            name="condition_status"
            required
        >

            <option value="">
                -- Select Condition --
            </option>

            <option value="Good">
                Good
            </option>

            <option value="Fair">
                Fair
            </option>

            <option value="Needs Inspection">
                Needs Inspection
            </option>

        </select>

    </div>


    <div class="form-group">

        <label for="remarks">
            Remarks
        </label>
        <small class="secondary-text">Optional: document the reason for transfer, accessories, or other accountability notes.</small>


        <textarea
            id="remarks"
            name="remarks"
            rows="5"
            placeholder="Enter transfer remarks or notes..."
        ></textarea>

    </div>


    <div class="form-actions">

        <a
            href="/st/frontend/pages/transfers.php"
            class="btn btn-secondary"
        >
            Cancel
        </a>


        <button
            type="submit"
            class="btn btn-primary"
        >
            Transfer Asset
        </button>

    </div>

</form>

</div>

<?php require_once "../../backend/includes/footer.php"; ?>
