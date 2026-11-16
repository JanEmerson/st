<?php
$page_title = "Assign Asset";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian"]);

/*
|--------------------------------------------------------------------------
| Get Available Assets
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number
    FROM assets a
    WHERE a.status = 'Available'
      AND a.current_user_id IS NULL
            AND NOT EXISTS (
                    SELECT 1
                    FROM maintenance_records m
                    WHERE m.asset_id = a.id
                        AND m.status IN ('Open', 'In Progress', 'Waiting for Parts')
            )
    ORDER BY a.asset_code ASC
");

$stmt->execute();

$assets = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Get Active Users
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        employee_id,
        full_name,
        department,
        position
    FROM users
    WHERE status = 'Active'
    ORDER BY full_name ASC
");

$stmt->execute();

$users = $stmt->fetchAll();

?>
<div class="page-header">


<div>
    <p>Assign an available asset to an active employee.</p>
</div>

<div>
    <a
        href="/st/frontend/pages/assignments.php"
        class="btn btn-secondary"
    >
        Back to Assignments
    </a>
</div>


</div>

<div class="transaction-guide">
    <div class="transaction-guide-title">Assigning an asset</div>
    <div class="transaction-steps">
        <span class="is-current">1. Select asset</span><span>→</span><span>2. Select employee</span><span>→</span><span>3. Check condition</span><span>→</span><span>4. Confirm assignment</span>
    </div>
    <p>After you confirm, the asset becomes <strong>Assigned</strong>, the employee becomes accountable for it, and the transaction is recorded in Asset History.</p>
</div>

<div class="form-card">
<?php if (!empty($_GET["error"])): ?>
<div class="alert alert-error">
    <?= htmlspecialchars($_GET["error"]) ?>
</div>

<?php endif; ?>


<?php if (count($assets) === 0): ?>
    <div class="alert alert-error">
        There are currently no available assets to assign.
    </div>
<?php elseif (count($users) === 0): ?>

    <div class="alert alert-error">
        There are currently no active users available for assignment.
    </div>

<?php else: ?>

    <form method="POST" action="assignment_save.php">
            <?= csrf_field() ?>

        <div class="form-grid">

            <!-- Asset -->

            <div class="form-group">

                <label for="asset_id">
                    Asset <span class="required">*</span>
                </label>
                <small class="secondary-text">Select the asset being issued. Only Available, unassigned assets are shown.</small>

                <select
                    id="asset_id"
                    name="asset_id"
                    data-searchable-select
                    data-search-placeholder="Search by asset code, name, type or serial..."
                    required
                >

                    <option value="">
                        -- Select Asset --
                    </option>

                    <?php foreach ($assets as $asset): ?>

                        <option
                            value="<?= (int)$asset["id"] ?>"
                        >
                            <?= htmlspecialchars($asset["asset_code"]) ?>
                            -
                            <?= htmlspecialchars($asset["asset_name"]) ?>
                            -
                            <?= htmlspecialchars($asset["asset_type"]) ?>
                            <?php if (!empty($asset["serial_number"])): ?>
                                (SN:
                                <?= htmlspecialchars($asset["serial_number"]) ?>)
                            <?php endif; ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Employee -->

            <div class="form-group">

                <label for="user_id">
                    Assign To <span class="required">*</span>
                </label>
                <small class="secondary-text">Select the active employee who will become accountable for the asset.</small>

                <select
                    id="user_id"
                    name="user_id"
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
                            <?= htmlspecialchars($user["employee_id"] ?? "No ID") ?>
                            -
                            <?= htmlspecialchars($user["department"] ?? "No Department") ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Condition -->

            <div class="form-group">

                <label for="condition_status">
                    Condition <span class="required">*</span>
                </label>
                <small class="secondary-text">Record the asset condition at the time it is issued.</small>

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


            <!-- Remarks -->

            <div class="form-group">

                <label for="remarks">
                    Remarks
                </label>
                <small class="secondary-text">Optional: record accessories, special instructions, or other facts relevant to this transaction.</small>

                <textarea
                    id="remarks"
                    name="remarks"
                    rows="4"
                    placeholder="Enter assignment notes or remarks..."
                ></textarea>

            </div>

        </div>


        <div class="form-actions">

            <a
                href="/st/frontend/pages/assignments.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Continue Assignment
            </button>

        </div>

    </form>

<?php endif; ?>


</div>

<?php require_once "../../backend/includes/footer.php"; ?>
