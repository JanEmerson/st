<?php
$page_title = "Asset Turnovers";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

require_role(["Administrator", "Asset Custodian", "HR / Clearance Officer"]);

$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| Get Currently Assigned Assets
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,

        u.employee_id,
        u.full_name,
        u.department,
        u.position

    FROM assets a

    INNER JOIN users u
        ON a.current_user_id = u.id

    WHERE a.status = 'Assigned'
      AND a.current_user_id IS NOT NULL
";

$params = [];

if ($search !== "") {

    $sql .= "
        AND (
            a.asset_code LIKE :search
            OR a.asset_name LIKE :search
            OR a.asset_type LIKE :search
            OR a.serial_number LIKE :search
            OR u.employee_id LIKE :search
            OR u.full_name LIKE :search
            OR u.department LIKE :search
        )
    ";

    $params[":search"] = "%" . $search . "%";
}

$sql .= "
    ORDER BY a.asset_code ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$assets = $stmt->fetchAll();

?>

<div class="page-header">

<div>
    <p>Process the return of assets from employees.</p>
</div>

</div>

<div class="filter-container">

<form
    method="GET"
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
            placeholder="Asset, employee, department..."
        >

    </div>


    <div class="filter-actions">

        <button
            type="submit"
            class="btn btn-primary"
        >
            Search
        </button>

        <a
            href="/st/frontend/pages/turnovers.php"
            class="btn btn-secondary"
        >
            Clear
        </a>

    </div>

</form>

</div>

<div class="table-card">

<div class="table-responsive">

    <table class="primary-info-table turnovers-table">

        <thead>

            <tr>

                <th>Asset Code</th>
                <th>Asset Name</th>
                <th>Asset Type</th>
                <th>Serial Number</th>
                <th>Current Employee</th>
                <th>Department</th>
                <th>Action</th>

            </tr>

        </thead>


        <tbody>

            <?php if (count($assets) > 0): ?>

                <?php foreach ($assets as $asset): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $asset["asset_code"]
                                ) ?>
                            </strong>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $asset["asset_name"]
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $asset["asset_type"]
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $asset["serial_number"] ?? "-"
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $asset["full_name"]
                            ) ?>

                            <?php if (!empty($asset["employee_id"])): ?>

                                <br>

                                <small>
                                    <?= htmlspecialchars(
                                        $asset["employee_id"]
                                    ) ?>
                                </small>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $asset["department"] ?? "-"
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                in_array(
                                    $role,
                                    ["Administrator", "Asset Custodian"],
                                    true
                                )
                            ): ?>

                                <a
                                    href="/st/frontend/pages/turnover_add.php?id=<?= (int)$asset["id"] ?>"
                                    class="btn btn-primary btn-small"
                                >
                                    Turn Over
                                </a>

                            <?php endif; ?>


                            <a
                                href="/st/frontend/pages/asset_view.php?id=<?= (int)$asset["id"] ?>"
                                class="btn btn-secondary btn-small"
                            >
                                View
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="7"
                        style="text-align:center;"
                    >
                        No currently assigned assets found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>

</div>

<?php require_once "../../backend/includes/footer.php"; ?>
