<?php
$page_title = "Users";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";


/*
|--------------------------------------------------------------------------
| Administrator Only
|--------------------------------------------------------------------------
*/

if (!is_administrator_role(current_user_role())) {
    http_response_code(403);
    die("Access denied.");
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

$status = trim($_GET["status"] ?? "");


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        employee_id,
        username,
        full_name,
        department,
        position,
        role,
        status,
        avatar_filename,
        created_at,
        (
            SELECT COUNT(*)
            FROM assets assigned_assets
            WHERE assigned_assets.current_user_id = users.id
        ) AS assigned_asset_count
    FROM users
    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            employee_id LIKE :search
            OR username LIKE :search
            OR full_name LIKE :search
            OR department LIKE :search
            OR position LIKE :search
        )
    ";

    $params[":search"] = "%" . $search . "%";
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
            "Active",
            "Inactive"
        ],
        true
    )
) {

    $sql .= "
        AND status = :status
    ";

    $params[":status"] = $status;
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY id DESC
";


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll();


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
            Manage system users and employee accounts.
        </p>
    </div>


    <div class="page-actions">
        <a
            href="user_add.php"
            class="btn btn-primary"
        >+ Add User</a>
    </div>
</div>


<?php if ($success === "added"): ?>

    <div class="alert alert-success">
        User added successfully.
    </div>

<?php endif; ?>


<?php if ($success === "updated"): ?>

    <div class="alert alert-success">
        User updated successfully.
    </div>

<?php endif; ?>


<?php if ($error === "duplicate"): ?>

    <div class="alert alert-error">
        Employee ID or username already exists.
    </div>

<?php endif; ?>


<?php if ($error === "notfound"): ?>

    <div class="alert alert-error">
        User not found.
    </div>

<?php endif; ?>


<!-- =========================================================
     FILTERS
     ========================================================= -->

<div class="filter-container">

    <form
        method="GET"
        action="users.php"
        class="filter-form"
    >

        <div class="form-group">

            <label for="search">
                Search
            </label>

            <input
                type="text"
                id="search"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Employee ID, name, username..."
            >

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
                    value="Active"
                    <?= $status === "Active"
                        ? "selected"
                        : ""
                    ?>
                >
                    Active
                </option>

                <option
                    value="Inactive"
                    <?= $status === "Inactive"
                        ? "selected"
                        : ""
                    ?>
                >
                    Inactive
                </option>

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
                href="users.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        </div>

    </form>

</div>


<!-- =========================================================
     USER TABLE
     ========================================================= -->

<div class="dashboard-section">

    <div class="section-header">

        <div>

            <h2>
                User Accounts
            </h2>

            <p>
                <?= count($users) ?> user(s) found.
            </p>

        </div>

    </div>


    <div class="table-container">

        <table class="primary-info-table users-table">

            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>


            <tbody>

                <?php if (count($users) > 0): ?>

                    <?php foreach ($users as $user): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $user["employee_id"] ?? "-"
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <div class="user-table-identity">
                                    <?php if (!empty($user["avatar_filename"])): ?>
                                        <img
                                            class="table-avatar"
                                            src="/st/uploads/avatars/<?= htmlspecialchars(rawurlencode($user["avatar_filename"])) ?>"
                                            alt="Profile picture"
                                        >
                                    <?php else: ?>
                                        <span class="table-avatar table-avatar-fallback">
                                            <?= htmlspecialchars(strtoupper(substr(trim($user["full_name"] ?? "U"), 0, 1))) ?>
                                        </span>
                                    <?php endif; ?>

                                    <strong>
                                        <?= htmlspecialchars($user["full_name"]) ?>
                                    </strong>
                                </div>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $user["username"]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $user["department"] ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $user["position"] ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $user["role"]
                                ) ?>

                            </td>


                            <td>

                                <span class="status-badge status-<?= strtolower(
                                    $user["status"]
                                ) ?>">

                                    <?= htmlspecialchars(
                                        $user["status"]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        "M d, Y",
                                        strtotime(
                                            $user["created_at"]
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <div class="user-action-buttons">

                                    <a
                                        href="user_assets.php?id=<?= (int) $user["id"] ?>"
                                        class="btn btn-small btn-secondary"
                                        title="View all assets currently assigned to this user"
                                    >
                                        Assets
                                        <span class="action-count">
                                            <?= (int) ($user["assigned_asset_count"] ?? 0) ?>
                                        </span>
                                    </a>

                                    <a
                                        href="user_assets_export.php?id=<?= (int) $user["id"] ?>"
                                        class="btn btn-small btn-primary"
                                        title="Download the current assigned asset list as Excel"
                                    >
                                        Excel
                                    </a>

                                    <a
                                        href="user_edit.php?id=<?= (int) $user["id"] ?>"
                                        class="btn btn-small btn-primary"
                                    >
                                        Edit
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="9"
                            class="empty-state"
                        >
                            No users found.
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

