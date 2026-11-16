<?php
require_once "../../backend/includes/security.php";
st_start_session();

require_once "../../backend/config/database.php";

$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["Administrator", "Asset Custodian"], true)) {
    header("Location: /st/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /st/frontend/pages/assignments.php");
    exit;
}

require_csrf();

$asset_id = (int)($_POST["asset_id"] ?? 0);
$user_id = (int)($_POST["user_id"] ?? 0);
$condition_status = trim($_POST["condition_status"] ?? "");
$remarks = trim($_POST["remarks"] ?? "");

$error = "";

if ($asset_id <= 0) {
    $error = "Please select an asset.";
}

if ($error === "" && $user_id <= 0) {
    $error = "Please select an employee.";
}

$allowed_conditions = [
    "Good",
    "Fair",
    "Needs Inspection"
];

if (
    $error === "" &&
    !in_array($condition_status, $allowed_conditions, true)
) {
    $error = "Please select a valid asset condition.";
}

if ($error !== "") {
    header(
        "Location: /st/frontend/pages/assignment_add.php?error=" .
        urlencode($error)
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Start Transaction
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Get Asset
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            asset_code,
            asset_name,
            status,
            current_user_id
        FROM assets
        WHERE id = :id
        FOR UPDATE
    ");

    $stmt->execute([
        ":id" => $asset_id
    ]);

    $asset = $stmt->fetch();

    if (!$asset) {
        throw new Exception("Asset not found.");
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Asset Is Available
    |--------------------------------------------------------------------------
    */

    if (
        $asset["status"] !== "Available" ||
        $asset["current_user_id"] !== null
    ) {
        throw new Exception(
            "This asset is no longer available for assignment."
        );
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM maintenance_records
        WHERE asset_id = :asset_id
          AND status IN ('Open', 'In Progress', 'Waiting for Parts')
        LIMIT 1
    ");

    $stmt->execute([
        ":asset_id" => $asset_id
    ]);

    if ($stmt->fetch()) {
        throw new Exception(
            "This asset has an active maintenance work order."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Employee
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            employee_id,
            full_name,
            status
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $user_id
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Selected employee was not found.");
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Employee Is Active
    |--------------------------------------------------------------------------
    */

    if ($user["status"] !== "Active") {
        throw new Exception(
            "The selected employee is inactive."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Asset
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE assets
        SET
            status = 'Assigned',
            current_user_id = :user_id
        WHERE id = :asset_id
    ");

    $stmt->execute([
        ":user_id" => $user_id,
        ":asset_id" => $asset_id
    ]);


    /*
    |--------------------------------------------------------------------------
    | Create Asset History
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
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
            'ASSIGNED',
            NULL,
            :to_user_id,
            :performed_by,
            :condition_status,
            :remarks
        )
    ");

    $stmt->execute([
        ":asset_id" => $asset_id,
        ":to_user_id" => $user_id,
        ":performed_by" => $_SESSION["user_id"],
        ":condition_status" => $condition_status,
        ":remarks" => $remarks !== "" ? $remarks : null
    ]);


    /*
    |--------------------------------------------------------------------------
    | Complete Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        "Location: /st/frontend/pages/asset_view.php?id=" .
        $asset_id .
        "&success=assigned"
    );

    exit;


} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    header(
        "Location: /st/frontend/pages/assignment_add.php?error=" .
        urlencode($e->getMessage())
    );

    exit;
}

?>
