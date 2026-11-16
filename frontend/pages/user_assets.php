<?php
/*
 * ST Asset Management System
 * File: frontend/pages/user_assets.php
 * Purpose: Display all assets currently assigned to one employee/user.
 * Debugging: Check the user ID in the URL, then inspect
 *             backend/includes/user_asset_report.php for the database query.
 */

$page_title = "User Assets";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/user_asset_report.php";

/*
|--------------------------------------------------------------------------
| Administrator Only
|--------------------------------------------------------------------------
| User management is restricted to Administrators, so the related asset
| accountability view follows the same permission boundary.
|--------------------------------------------------------------------------
*/
if (!is_administrator_role(current_user_role())) {
    http_response_code(403);
    die("Access denied.");
}

/* Get the selected user ID from the URL. */
$userId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($userId <= 0) {
    http_response_code(400);
    die("Invalid user.");
}

/* Load employee information and the current accountability list. */
$user = st_get_user_for_asset_report($conn, $userId);

if (!$user) {
    http_response_code(404);
    die("User not found.");
}

$assets = st_get_user_assigned_assets($conn, $userId);

?>

<div class="page-header">
    <div>
        <p>View all assets currently assigned to this employee.</p>
    </div>

    <div class="page-actions">
        <a
            href="user_assets_export.php?id=<?= (int)$user["id"] ?>"
            class="btn btn-primary"
        >
            ↓ Download Excel
        </a>

        <a
            href="users.php"
            class="btn btn-secondary"
        >
            ← Back to Users
        </a>
    </div>
</div>

<!-- =========================================================
     EMPLOYEE SUMMARY
     ========================================================= -->
<div class="dashboard-section">
    <div class="section-header">
        <div>
            <h2><?= htmlspecialchars($user["full_name"]) ?></h2>
            <p>
                <?= htmlspecialchars($user["employee_id"] ?? "-") ?>
                ·
                <?= htmlspecialchars($user["department"] ?? "-") ?>
                ·
                <?= htmlspecialchars($user["position"] ?? "-") ?>
            </p>
        </div>

        <div class="user-assets-summary-count">
            <strong><?= count($assets) ?></strong>
            <span>Currently Assigned</span>
        </div>
    </div>
</div>

<!-- =========================================================
     CURRENTLY ASSIGNED ASSETS
     ========================================================= -->
<div class="dashboard-section">
    <div class="section-header">
        <div>
            <h2>Currently Assigned Assets</h2>
            <p>
                Only assets whose current employee is this user are shown.
                Previous transfers and turnovers remain available in History.
            </p>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Asset Code</th>
                    <th>Asset Name</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Serial / Plate</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Assigned / Transferred</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($assets): ?>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($asset["asset_code"]) ?></strong></td>
                            <td><?= htmlspecialchars($asset["asset_name"]) ?></td>
                            <td><?= htmlspecialchars($asset["category_name"]) ?></td>
                            <td><?= htmlspecialchars($asset["asset_type"]) ?></td>
                            <td><?= htmlspecialchars($asset["serial_number"] ?? "-") ?></td>
                            <td><?= htmlspecialchars($asset["condition_status"] ?? "-") ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower(htmlspecialchars($asset["status"])) ?>">
                                    <?= htmlspecialchars($asset["status"]) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($asset["assignment_date"])): ?>
                                    <?= htmlspecialchars(date("M d, Y", strtotime($asset["assignment_date"]))) ?>
                                    <br>
                                    <small>
                                        <?= htmlspecialchars($asset["assignment_action"] ?? "") ?>
                                    </small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a
                                    href="asset_view.php?id=<?= (int)$asset["id"] ?>"
                                    class="btn btn-small btn-primary"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            No assets are currently assigned to this user.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "../../backend/includes/footer.php"; ?>
