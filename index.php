<?php
$page_title = "Dashboard - Asset Overview";

require_once "backend/includes/header.php";
require_once "backend/includes/dashboard_data.php";

?>

<div class="dashboard-header">
    <div>
        <p>Monitor your organization's assets and accountability.</p>
    </div>
</div>


<!-- STATISTICS -->
<div class="stats-grid">
    <a class="stat-card stat-card-link" href="/st/frontend/pages/assets.php">
        <div class="stat-label">Total Assets</div>
        <div class="stat-number"><?= $total_assets ?></div>
    </a>


    <a class="stat-card stat-card-link" href="/st/frontend/pages/assets.php?status=Available">
        <div class="stat-label">Available</div>
        <div class="stat-number"><?= $available_assets ?></div>
    </a>


    <a class="stat-card stat-card-link" href="/st/frontend/pages/assets.php?status=Assigned">
        <div class="stat-label">Assigned</div>
        <div class="stat-number"><?= $assigned_assets ?></div>
    </a>


    <a class="stat-card stat-card-link" href="/st/frontend/pages/assets.php?status=Maintenance">
        <div class="stat-label">Maintenance</div>
        <div class="stat-number"><?= $maintenance_assets ?></div>
    </a>

    <a class="stat-card stat-card-link" href="/st/frontend/pages/maintenance.php?status=Active">
        <div class="stat-label">Active Work Orders</div>
        <div class="stat-number"><?= $open_maintenance_orders ?></div>
    </a>


    <a class="stat-card stat-card-link" href="/st/frontend/pages/assets.php?status=Retired">
        <div class="stat-label">Retired</div>
        <div class="stat-number"><?= $retired_assets ?></div>
    </a>


    <a class="stat-card stat-card-link" href="/st/frontend/pages/assets.php?asset_source=Legacy%20%2F%20Existing">
        <div class="stat-label">Legacy / Existing</div>
        <div class="stat-number"><?= $legacy_assets ?></div>
    </a>


    <?php if (is_administrator_role(current_user_role())): ?>
        <a class="stat-card stat-card-link" href="/st/frontend/pages/users.php?status=Active">
            <div class="stat-label">Active Users</div>
            <div class="stat-number"><?= $total_users ?></div>
        </a>
    <?php else: ?>
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-number"><?= $total_users ?></div>
        </div>
    <?php endif; ?>

</div>


<!-- QUICK ACTIONS -->
<section class="dashboard-section dashboard-quick-actions">
    <div class="section-header">
        <div>
            <h2>What do you need to do?</h2>
            <p>Use these shortcuts for the most common tasks.</p>
        </div>
        <a href="/st/frontend/pages/user_guide.php" class="btn btn-secondary btn-small">Need Help?</a>
    </div>
    <div class="quick-action-grid">
        <a class="quick-action-card" href="/st/frontend/pages/assets.php">
            <strong>Find an Asset</strong>
            <span>Search and view asset records.</span>
        </a>
        <?php if (user_has_role(current_user_role(), ["Administrator", "Asset Custodian"])): ?>
            <a class="quick-action-card" href="/st/frontend/pages/asset_add.php">
                <strong>Add an Asset</strong>
                <span>Register a new or existing asset.</span>
            </a>
            <a class="quick-action-card" href="/st/frontend/pages/assignment_add.php">
                <strong>Assign an Asset</strong>
                <span>Give an available asset to an employee.</span>
            </a>
            <a class="quick-action-card" href="/st/frontend/pages/transfers.php">
                <strong>Transfer an Asset</strong>
                <span>Move accountability to another employee.</span>
            </a>
            <a class="quick-action-card" href="/st/frontend/pages/turnovers.php">
                <strong>Record a Return</strong>
                <span>Receive an assigned asset back.</span>
            </a>
            <a class="quick-action-card" href="/st/frontend/pages/maintenance.php">
                <strong>Manage Maintenance</strong>
                <span>Track repair and service work.</span>
            </a>
        <?php endif; ?>
        <a class="quick-action-card" href="/st/frontend/pages/history.php">
            <strong>View Asset History</strong>
            <span>See previous assignments, transfers, and returns.</span>
        </a>
        <?php if (in_array(current_user_role(), ["Administrator", "HR / Clearance Officer"], true)): ?>
            <a class="quick-action-card" href="/st/frontend/pages/clearance.php">
                <strong>Check Clearance</strong>
                <span>Review an employee's current asset accountability.</span>
            </a>
        <?php endif; ?>
    </div>
</section>


<!-- ATTENTION CENTER -->
<?php if (is_administrator_role(current_user_role()) || in_array(current_user_role(), ["Asset Custodian"], true)): ?>
<div class="dashboard-section dashboard-attention-center">
    <div class="section-header">
        <div>
            <h2>Attention Needed</h2>
            <p>High-priority, urgent, due-today, or overdue maintenance work orders.</p>
        </div>
        <a href="/st/frontend/pages/maintenance.php?status=Active" class="btn btn-secondary btn-small">View Active Work Orders</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Work Order</th><th>Asset</th><th>Priority</th><th>Status</th><th>Due</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if ($attention_count > 0): ?>
                <?php foreach ($attention_orders as $order): ?>
                    <?php
                        $dueDate = $order["due_date"] ?? null;
                        $dueClass = $dueDate && $dueDate < date("Y-m-d") ? "dashboard-overdue" : ($dueDate === date("Y-m-d") ? "dashboard-due-today" : "");
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($order["work_order_no"] ?? "Pending") ?></strong><br><span class="secondary-text"><?= htmlspecialchars($order["title"]) ?></span></td>
                        <td><a href="/st/frontend/pages/asset_view.php?id=<?= (int)$order["asset_id"] ?>"><?= htmlspecialchars($order["asset_code"]) ?></a><br><span class="secondary-text"><?= htmlspecialchars($order["asset_name"]) ?></span></td>
                        <td><span class="status-badge"><?= htmlspecialchars($order["priority"]) ?></span></td>
                        <td><span class="status-badge"><?= htmlspecialchars($order["status"]) ?></span></td>
                        <td><span class="<?= $dueClass ?>"><?= $dueDate ? htmlspecialchars(date("M d, Y", strtotime($dueDate))) : "-" ?></span></td>
                        <td><a href="/st/frontend/pages/maintenance_edit.php?id=<?= (int)$order["id"] ?>" class="btn btn-secondary btn-small">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No urgent or overdue maintenance items. The system is up to date.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>


<!-- CATEGORY SUMMARY -->
<div class="dashboard-section">

    <div class="section-header">

        <div>

            <h2>Asset Categories</h2>

            <p>
                Asset distribution by category.
            </p>

        </div>

    </div>


    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>Category</th>
                    <th>Total</th>
                    <th>Assigned</th>
                    <th>Available</th>
                    <!-- Retired assets are shown separately for management visibility. -->
                    <th>Retired</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($category_summary) > 0): ?>

                    <?php foreach ($category_summary as $category): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($category["name"]) ?>
                            </td>

                            <td>
                                <?= (int) $category["total"] ?>
                            </td>

                            <td>
                                <?= (int) $category["assigned"] ?>
                            </td>

                            <td>
                                <?= (int) $category["available"] ?>
                            </td>

                            <!-- Current retired count for this category. -->
                            <td>
                                <?= (int) $category["retired"] ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="5"
                            class="empty-state"
                        >
                            No categories found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- RECENT HISTORY -->
<div class="dashboard-section">

    <div class="section-header">

        <div>

            <h2>Recent Activity</h2>

            <p>
                Latest asset transactions.
            </p>

        </div>

    </div>


    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Date</th>
                    <th>Asset</th>
                    <th>Action</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Performed By</th>
                </tr>

            </thead>

            <tbody>

                <?php if (count($recent_history) > 0): ?>

                    <?php foreach ($recent_history as $history): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $history["transaction_date"]
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $history["asset_code"]
                                    ) ?>
                                </strong>

                                <br>

                                <span class="secondary-text">
                                    <?= htmlspecialchars(
                                        $history["asset_name"]
                                    ) ?>
                                </span>

                            </td>

                            <td>

                                <span class="action-badge">
                                    <?= htmlspecialchars(
                                        $history["action"]
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $history["from_user_name"] ?? "-"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $history["to_user_name"] ?? "-"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $history["performed_by_name"]
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            class="empty-state"
                        >
                            No asset transactions yet.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php

require_once "backend/includes/footer.php";

?>
