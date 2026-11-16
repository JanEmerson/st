<?php
$page_title = "Add User";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/upload_helpers.php";

require_admin_role();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    require_csrf();

    $employee_id = trim($_POST["employee_id"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $department = trim($_POST["department"] ?? "");
    $position = trim($_POST["position"] ?? "");
    $role = $_POST["role"] ?? "Viewer";
    $status = $_POST["status"] ?? "Active";

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

    if ($full_name === "") {
        $error = "Full name is required.";

    } elseif ($username === "") {
        $error = "Username is required.";

    } elseif ($password === "") {
        $error = "Password is required.";

    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";

    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = "Invalid user role.";

    } elseif (!in_array($status, $allowed_statuses, true)) {
        $error = "Invalid user status.";

    } else {

        // Check duplicate username
        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ":username" => $username
        ]);

        if ($stmt->fetch()) {

            $error = "Username already exists.";

        } else {

            // Check duplicate employee ID only when provided
            if ($employee_id !== "") {

                $stmt = $conn->prepare("
                    SELECT id
                    FROM users
                    WHERE employee_id = :employee_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ":employee_id" => $employee_id
                ]);

                if ($stmt->fetch()) {
                    $error = "Employee ID already exists.";
                }
            }

            if ($error === "") {

                $avatar_filename = null;

                if (isset($_FILES["avatar"]) && ($_FILES["avatar"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $upload = upload_image_file(
                        $_FILES["avatar"],
                        __DIR__ . "/../../uploads/avatars",
                        "avatar_new",
                        2097152
                    );

                    if (!$upload["success"]) {
                        $error = $upload["error"] ?? "Unable to upload the profile picture.";
                    } else {
                        $avatar_filename = $upload["filename"];
                    }
                }

                if ($error === "") {
                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare("
                    INSERT INTO users (
                        employee_id,
                        username,
                        password,
                        full_name,
                        department,
                        position,
                        role,
                        status,
                        avatar_filename
                    )
                    VALUES (
                        :employee_id,
                        :username,
                        :password,
                        :full_name,
                        :department,
                        :position,
                        :role,
                        :status,
                        :avatar_filename
                    )
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
                    ":avatar_filename" => $avatar_filename
                ]);

                }

                if ($error === "") {
                    header("Location: /st/frontend/pages/users.php?success=added");
                    exit;
                }
            }
        }
    }
}

?>




<div class="page-header">
    <div>
        <h2>Add User</h2>
        <p>Create a new system user.</p>
    </div>

<div>
    <a href="/st/frontend/pages/users.php" class="btn btn-secondary">
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
                value="<?= htmlspecialchars($_POST["employee_id"] ?? "") ?>"
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
                value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>"
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
                value="<?= htmlspecialchars($_POST["username"] ?? "") ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">
                Password <span class="required">*</span>
            </label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="6"
                required
            >

            <small>
                Minimum 6 characters.
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
                value="<?= htmlspecialchars($_POST["department"] ?? "") ?>"
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
                value="<?= htmlspecialchars($_POST["position"] ?? "") ?>"
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
        </div>

        <div class="form-group">
    <label for="role">
        Role <span class="required">*</span>
    </label>

<select id="role" name="role" required>
    <option value="">-- Select Role --</option>
    <option value="Administrator">Administrator</option>
    <option value="Asset Custodian">Asset Custodian</option>
    <option value="HR / Clearance Officer">HR / Clearance Officer</option>
    <option value="Viewer">Viewer</option>
</select>

    </div>
        <div class="form-group">
            <label for="status">
                Status <span class="required">*</span>
            </label>

            <select id="status" name="status" required>
                <option value="">-- Select Status --</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
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
            Save User
        </button>

    </div>

</form>

</div>

<?php require_once "../../backend/includes/footer.php"; ?>
