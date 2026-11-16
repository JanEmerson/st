<?php
require_once "../../backend/includes/security.php";
st_start_session();

require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";

$role = $_SESSION["role"] ?? "";

if (!in_array(
    $role,
    ["Administrator", "Asset Custodian"],
    true
)) {
    header("Location: /st/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /st/frontend/pages/turnovers.php");
    exit;
}

require_csrf();

$asset_id = (int)($_POST["asset_id"] ?? 0);
$condition_status = trim($_POST["condition_status"] ?? "");
$remarks = trim($_POST["remarks"] ?? "");

$allowed_conditions = [
    "Good",
    "Fair",
    "Needs Inspection"
];

if (
    $asset_id <= 0 ||
    !in_array($condition_status, $allowed_conditions, true)
) {
    header(
        "Location: /st/frontend/pages/turnovers.php?error=invalid"
    );
    exit;
}

$performed_by = (int)($_SESSION["user_id"] ?? 0);

if ($performed_by <= 0) {
    header("Location: /st/login.php");
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock Asset
    |--------------------------------------------------------------------------
    */

    $sql_asset = "
        SELECT
            id,
            asset_code,
            asset_name,
            status,
            current_user_id

        FROM assets

        WHERE id = :asset_id

        LIMIT 1

        FOR UPDATE
    ";

    $stmt_asset = $conn->prepare($sql_asset);

    $stmt_asset->execute([
        ":asset_id" => $asset_id
    ]);

    $asset = $stmt_asset->fetch();


    if (!$asset) {

        $conn->rollBack();

        header(
            "Location: /st/frontend/pages/turnovers.php?error=notfound"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Asset Is Currently Assigned
    |--------------------------------------------------------------------------
    */

    if (
        $asset["status"] !== "Assigned" ||
        empty($asset["current_user_id"])
    ) {

        $conn->rollBack();

        header(
            "Location: /st/frontend/pages/turnovers.php?error=notassigned"
        );

        exit;
    }


    $from_user_id = (int)$asset["current_user_id"];


    /*
    |--------------------------------------------------------------------------
    | Return Asset
    |--------------------------------------------------------------------------
    */

    $sql_update = "
        UPDATE assets

        SET
            status = 'Available',
            current_user_id = NULL

        WHERE id = :asset_id
    ";

    $stmt_update = $conn->prepare($sql_update);

    $stmt_update->execute([
        ":asset_id" => $asset_id
    ]);


    /*
    |--------------------------------------------------------------------------
    | Record Return In Asset History
    |--------------------------------------------------------------------------
    */

    $sql_history = "
        INSERT INTO asset_history (
            asset_id,
            action,
            from_user_id,
            to_user_id,
            performed_by,
            condition_status,
            remarks
        )

        VALUES (
            :asset_id,
            'RETURNED',
            :from_user_id,
            NULL,
            :performed_by,
            :condition_status,
            :remarks
        )
    ";

    $stmt_history = $conn->prepare($sql_history);

    $stmt_history->execute([
        ":asset_id" => $asset_id,
        ":from_user_id" => $from_user_id,
        ":performed_by" => $performed_by,
        ":condition_status" => $condition_status,
        ":remarks" => $remarks
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Redirect To Asset View
    |--------------------------------------------------------------------------
    */

    header(
        "Location: /st/frontend/pages/asset_view.php?id=" .
        $asset_id .
        "&success=returned"
    );

    exit;


} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback On Error
    |--------------------------------------------------------------------------
    */

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    header(
        "Location: /st/frontend/pages/turnovers.php?error=failed"
    );

    exit;
}

?>
