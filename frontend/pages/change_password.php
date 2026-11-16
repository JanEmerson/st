<?php
$page_title = "Change Password";

require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";


/*
|--------------------------------------------------------------------------
| Administrator Only
|--------------------------------------------------------------------------
*/

$role = $_SESSION["role"] ?? "";

if ($role !== "Administrator") {
    die("Access denied.");
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| Process Password Change
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    require_csrf();

    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {

        $error = "All password fields are required.";

    } elseif (strlen($new_password) < 8) {

        $error = "New password must be at least 8 characters.";

    } elseif ($new_password !== $confirm_password) {

        $error = "New password and confirmation password do not match.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Current User
        |--------------------------------------------------------------------------
        */

        $user_id = $_SESSION["user_id"] ?? 0;

        $stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $user_id
        ]);

        $user = $stmt->fetch();


        if (!$user) {

            $error = "User account not found.";

        } elseif (!password_verify($current_password, $user["password"])) {

            $error = "Current password is incorrect.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Prevent Same Password
            |--------------------------------------------------------------------------
            */

            if (password_verify($new_password, $user["password"])) {

                $error = "New password must be different from your current password.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Hash New Password
                |--------------------------------------------------------------------------
                */

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );


                /*
                |--------------------------------------------------------------------------
                | Update Password
                |--------------------------------------------------------------------------
                */

                $update = $conn->prepare("
                    UPDATE users
                    SET password = :password
                    WHERE id = :id
                ");

                $update->execute([
                    ":password" => $hashed_password,
                    ":id" => $user_id
                ]);


                $success = "Password changed successfully.";
            }
        }
    }
}


require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

?>

<div class="page-header">

    <div>
        <h2>Change Password</h2>
        <p>Update your account password.</p>
    </div>

</div>


<?php if ($error !== ""): ?>

    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<?php if ($success !== ""): ?>

    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
    </div>

<?php endif; ?>


<div class="detail-card">

    <div class="detail-card-header">
        <h3>Change Password</h3>
    </div>


    <form method="POST">
            <?= csrf_field() ?>

        <div class="form-group">

            <label for="current_password">
                Current Password
            </label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                required
            >

        </div>


        <div class="form-group">

            <label for="new_password">
                New Password
            </label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="8"
                required
            >

        </div>


        <div class="form-group">

            <label for="confirm_password">
                Confirm New Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                required
            >

        </div>


        <div class="form-actions">

            <a
                href="settings.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Change Password
            </button>

        </div>

    </form>

</div>


<?php require_once "../../backend/includes/footer.php"; ?>
