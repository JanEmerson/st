<?php
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";

/*
|--------------------------------------------------------------------------
| Access Control
|--------------------------------------------------------------------------
*/

$allowed_roles = [
    "Administrator",
    "HR / Clearance Officer"
];

$current_role = $_SESSION["role"] ?? "";

if (!in_array($current_role, $allowed_roles, true)) {
    die("Access denied.");
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| Get Employees
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id,
        u.employee_id,
        u.full_name,
        u.department,
        u.position,
        u.status,
        COUNT(a.id) AS asset_count
    FROM users u
    LEFT JOIN assets a
        ON a.current_user_id = u.id
    WHERE u.role != 'Administrator'
";

$params = [];

if ($search !== "") {
    $sql .= "
        AND (
            u.employee_id LIKE :search
            OR u.full_name LIKE :search
            OR u.department LIKE :search
        )
    ";

    $params[":search"] = "%{$search}%";
}

$sql .= "
    GROUP BY
        u.id,
        u.employee_id,
        u.full_name,
        u.department,
        u.position,
        u.status
    ORDER BY u.full_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

/* Purpose: Handle the file-specific operation represented by this function. */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? "-",
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<?php require_once "../../backend/includes/header.php"; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <p>
                View employee asset accountability and generate
                clearance forms.
            </p>
        </div>
    </div>

    <!-- Search -->
    <div class="card filter-container">
        <form method="GET" class="filter-form">
            <div class="search-form">

                <div class="search-field">
                    <label for="search">
                        Search Employee
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Employee ID, name or department..."
                    >
                </div>

                <div class="search-actions">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Search
                    </button>

                    <a
                        href="clearance.php"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>
                </div>

            </div>
        </form>
    </div>

    <!-- Employee List -->
    <div class="card">

        <div class="card-header">
            <h2>Employees</h2>
        </div>

        <div class="table-responsive">
            <table class="primary-info-table clearance-table">

                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Accountable Assets</th>
                        <th>Clearance</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($employees)): ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                No employees found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($employees as $employee): ?>

                            <?php
                            $asset_count = (int) $employee["asset_count"];
                            $is_cleared = $asset_count === 0;
                            ?>

                            <tr>

                                <td>
                                    <?= e($employee["employee_id"]) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($employee["full_name"]) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($employee["department"]) ?>
                                </td>

                                <td>
                                    <?= e($employee["position"]) ?>
                                </td>

                                <td>
                                    <?= e($employee["status"]) ?>
                                </td>

                                <td>
                                    <?= $asset_count ?>
                                </td>

                                <td>
                                    <?php if ($is_cleared): ?>

                                        <span class="badge badge-success">
                                            CLEARED
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-danger">
                                            NOT CLEARED
                                        </span>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a
                                        href="clearance_print.php?employee_id=<?= (int) $employee["id"] ?>"
                                        class="btn btn-primary"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Print Clearance
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>
        </div>

    </div>

</div>

<?php require_once "../../backend/includes/footer.php"; ?>