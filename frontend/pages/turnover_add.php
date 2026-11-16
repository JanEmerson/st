<?php
$page_title = "Asset Turnover";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);

$asset_id = (int)($_GET["id"] ?? 0);

if ($asset_id <= 0) {
    header("Location: /st/frontend/pages/turnovers.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Assigned Asset
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
    header(
        "Location: /st/frontend/pages/turnovers.php?error=notfound"
    );
    exit;
}

?>

<div class="page-header">

<div>

    <h2>Asset Turnover / Return</h2>

    <p>
        Record the return of this asset from the current employee.
    </p>

</div>

<div>

    <a
        href="/st/frontend/pages/turnovers.php"
        class="btn btn-secondary"
    >
        Back to Turnovers
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
    <div class="transaction-guide-title">Returning an asset</div>
    <div class="transaction-steps">
        <span>1. Review employee</span><span>→</span><span class="is-current">2. Check returned condition</span><span>→</span><span>3. Add notes if needed</span><span>→</span><span>4. Confirm return</span>
    </div>
    <p>After confirmation, the asset normally becomes <strong>Available</strong> and the employee is no longer accountable for it. The return is recorded in Asset History.</p>
</div>

<div class="form-card">

<div class="form-card-header">

    <h3>Return Details</h3>

</div>


<form
    method="POST"
    action="/st/frontend/pages/turnover_save.php"
>
            <?= csrf_field() ?>

    <input
        type="hidden"
        name="asset_id"
        value="<?= (int)$asset["id"] ?>"
    >


    <div class="form-group">

        <label for="condition_status">

            Returned Asset Condition
            <span class="required">*</span>

        </label>
        <small class="secondary-text">Record the asset condition after inspection. Use Needs Inspection if further checking is required.</small>


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
        <small class="secondary-text">Optional: record missing accessories, defects, return circumstances, or other observations.</small>


        <textarea
            id="remarks"
            name="remarks"
            rows="5"
            placeholder="Enter return remarks or notes..."
        ></textarea>

    </div>


    <div class="form-actions">

        <a
            href="/st/frontend/pages/turnovers.php"
            class="btn btn-secondary"
        >
            Cancel
        </a>


        <button
            type="submit"
            class="btn btn-primary"
        >
            Confirm Turnover
        </button>

    </div>

</form>

</div>

<?php require_once "../../backend/includes/footer.php"; ?>
