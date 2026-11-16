<?php
$page_title = "Edit User";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/upload_helpers.php";

require_admin_role();

$user_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($user_id <= 0) {
    header("Location: /st/frontend/pages/users.php?error=notfound");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get User
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        employee_id,
        username,
        full_name,
        department,
        position,
        role,
        status,
        avatar_filename
    FROM users
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $user_id
]);

$user = $stmt->fetch();

if (!$user) {
    header("Location: /st/frontend/pages/users.php?error=notfound");
    exit;
}

$error = "";

$allowed_roles = [
    "Administrator",
    "Asset Custodian",
    "HR / Clearance Officer",
    "Viewer"
];

$allowed_statuses = [
    "Active",
    "Inactive"
];

$current_avatar = trim($user["avatar_filename"] ?? "");
$new_avatar = $current_avatar;
$avatar_uploaded = false;

/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    require_csrf();

    $employee_id = trim($_POST["employee_id"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $department = trim($_POST["department"] ?? "");
    $position = trim($_POST["position"] ?? "");
    $role = $_POST["role"] ?? "";
    $status = $_POST["status"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($full_name === "") {

        $error = "Full name is required.";

    } elseif ($username === "") {

        $error = "Username is required.";

    } elseif (!in_array($role, $allowed_roles, true)) {

        $error = "Invalid user role.";

    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error = "Invalid user status.";

    } elseif ($password !== "" && strlen($password) < 6) {

        $error = "New password must be at least 6 characters.";

    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Current Administrator From Being Deactivated
    |--------------------------------------------------------------------------
    */

    if (
        $error === "" &&
        $user_id === (int)$_SESSION["user_id"] &&
        $status === "Inactive"
    ) {
        $error = "You cannot deactivate your own Administrator account.";
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Username
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = :username
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            ":username" => $username,
            ":id" => $user_id
        ]);

        if ($stmt->fetch()) {
            $error = "Username already exists.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Employee ID
    |--------------------------------------------------------------------------
    */

    if ($error === "" && $employee_id !== "") {

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE employee_id = :employee_id
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            ":employee_id" => $employee_id,
            ":id" => $user_id
        ]);

        if ($stmt->fetch()) {
            $error = "Employee ID already exists.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update User
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $current_avatar = trim($user["avatar_filename"] ?? "");
        $new_avatar = $current_avatar;
        $avatar_uploaded = false;

        if (isset($_FILES["avatar"]) && ($_FILES["avatar"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = upload_image_file(
                $_FILES["avatar"],
                __DIR__ . "/../../uploads/avatars",
                "avatar_" . $user_id,
                2097152
            );

            if (!$upload["success"]) {
                $error = $upload["error"] ?? "Unable to upload the profile picture.";
            } else {
                $new_avatar = $upload["filename"];
                $avatar_uploaded = true;
            }
        }

        if ($error === "" && $password !== "") {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare("
                UPDATE users
                SET
                    employee_id = :employee_id,
                    username = :username,
                    password = :password,
                    full_name = :full_name,
                    department = :department,
                    position = :position,
                    role = :role,
                    status = :status,
                    avatar_filename = :avatar_filename
                WHERE id = :id
            ");

            $stmt->execute([
                ":employee_id" => $employee_id !== "" ? $employee_id : null,
                ":username" => $username,
                ":password" => $hashed_password,
                ":full_name" => $full_name,
                ":department" => $department,
                ":position" => $position,
                ":role" => $role,
                ":status" => $status,
                ":avatar_filename" => $new_avatar,
                ":id" => $user_id
            ]);

        } else {

            $stmt = $conn->prepare("
                UPDATE users
                SET
                    employee_id = :employee_id,
                    username = :username,
                    full_name = :full_name,
                    department = :department,
                    position = :position,
                    role = :role,
                    status = :status,
                    avatar_filename = :avatar_filename
                WHERE id = :id
            ");

            $stmt->execute([
                ":employee_id" => $employee_id !== "" ? $employee_id : null,
                ":username" => $username,
                ":full_name" => $full_name,
                ":department" => $department,
                ":position" => $position,
                ":role" => $role,
                ":status" => $status,
                ":avatar_filename" => $new_avatar,
                ":id" => $user_id
            ]);
        }

        if ($avatar_uploaded && $current_avatar !== $new_avatar) {
            delete_uploaded_file(__DIR__ . "/../../uploads/avatars", $current_avatar);
        }

        /*
        |--------------------------------------------------------------------------
        | If Editing Own Account
        |--------------------------------------------------------------------------
        | Update the current session so changes take effect immediately.
        */

        if ($user_id === (int)$_SESSION["user_id"]) {

            $_SESSION["employee_id"] = $employee_id;
            $_SESSION["username"] = $username;
            $_SESSION["full_name"] = $full_name;
            $_SESSION["department"] = $department;
            $_SESSION["position"] = $position;
            $_SESSION["role"] = $role;
            $_SESSION["avatar_filename"] = $new_avatar;
        }

        header("Location: /st/frontend/pages/users.php?success=updated");
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Preserve Submitted Values After Error
    |--------------------------------------------------------------------------
    */

    $user["employee_id"] = $employee_id;
    $user["username"] = $username;
    $user["full_name"] = $full_name;
    $user["department"] = $department;
    $user["position"] = $position;
    $user["role"] = $role;
    $user["status"] = $status;
    $user["avatar_filename"] = $new_avatar ?? ($user["avatar_filename"] ?? "");
}

?>

<div class="page-header">

<div>
    <p>Update system user information.</p>
</div>

<div>
    <a
        href="/st/frontend/pages/users.php"
        class="btn btn-secondary"
    >
        Back to Users
    </a>
</div>

</div>

<?php if ($error !== ""): ?>

<div class="alert alert-error">
    <?= htmlspecialchars($error) ?>
</div>


<?php endif; ?>

<div class="form-card">

<form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

    <div class="form-grid">

        <div class="form-group">

            <label for="employee_id">
                Employee ID
            </label>

            <input
                type="text"
                id="employee_id"
                name="employee_id"
                value="<?= htmlspecialchars($user["employee_id"] ?? "") ?>"
                placeholder="Example: EMP-001"
            >

        </div>


        <div class="form-group">

            <label for="full_name">
                Full Name <span class="required">*</span>
            </label>

            <input
                type="text"
                id="full_name"
                name="full_name"
                value="<?= htmlspecialchars($user["full_name"] ?? "") ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="username">
                Username <span class="required">*</span>
            </label>

            <input
                type="text"
                id="username"
                name="username"
                value="<?= htmlspecialchars($user["username"] ?? "") ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="password">
                New Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="6"
                placeholder="Leave blank to keep current password"
            >

            <small>
                Leave blank if you do not want to change the password.
            </small>

        </div>


        <div class="form-group">

            <label for="department">
                Department
            </label>

            <input
                type="text"
                id="department"
                name="department"
                value="<?= htmlspecialchars($user["department"] ?? "") ?>"
                placeholder="Example: IT Department"
            >

        </div>


        <div class="form-group">

            <label for="position">
                Position
            </label>

            <input
                type="text"
                id="position"
                name="position"
                value="<?= htmlspecialchars($user["position"] ?? "") ?>"
                placeholder="Example: IT Staff"
            >

        </div>


        <div class="form-group">

            <label for="avatar">Profile Picture</label>

            <input
                type="file"
                id="avatar"
                name="avatar"
                accept="image/jpeg,image/png,image/webp"
            >

            <small>Optional. JPG, PNG, or WEBP, maximum 2 MB.</small>

            <?php if (!empty($user["avatar_filename"])): ?>
                <div class="admin-user-avatar-preview">
                    <img src="/st/uploads/avatars/<?= htmlspecialchars(rawurlencode($user["avatar_filename"])) ?>" alt="Current profile picture">
                </div>
            <?php endif; ?>

        </div>


        <div class="form-group">

            <label for="role">
                Role <span class="required">*</span>
            </label>

            <select
                id="role"
                name="role"
                required
            >

                <option
                    value="Administrator"
                    <?= $user["role"] === "Administrator" ? "selected" : "" ?>
                >
                    Administrator
                </option>

                <option
                    value="Asset Custodian"
                    <?= $user["role"] === "Asset Custodian" ? "selected" : "" ?>
                >
                    Asset Custodian
                </option>

                <option
                    value="HR / Clearance Officer"
                    <?= $user["role"] === "HR / Clearance Officer" ? "selected" : "" ?>
                >
                    HR / Clearance Officer
                </option>

                <option
                    value="Viewer"
                    <?= $user["role"] === "Viewer" ? "selected" : "" ?>
                >
                    Viewer
                </option>

            </select>

        </div>


        <div class="form-group">

            <label for="status">
                Status <span class="required">*</span>
            </label>

            <select
                id="status"
                name="status"
                required
            >

                <option
                    value="Active"
                    <?= $user["status"] === "Active" ? "selected" : "" ?>
                >
                    Active
                </option>

                <option
                    value="Inactive"
                    <?= $user["status"] === "Inactive" ? "selected" : "" ?>
                >
                    Inactive
                </option>

            </select>

        </div>

    </div>


    <div class="form-actions">

        <a
            href="/st/frontend/pages/users.php"
            class="btn btn-secondary"
        >
            Cancel
        </a>

        <button
            type="submit"
            class="btn btn-primary"
        >
            Update User
        </button>

    </div>

</form>

</div>

<?php require_once "../../backend/includes/footer.php"; ?>