<?php
$page_title = "My Profile";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/upload_helpers.php";

$user_id = (int)($_SESSION["user_id"] ?? 0);
$error = "";
$success = "";

$stmt = $conn->prepare("SELECT id, employee_id, username, full_name, department, position, role, status, avatar_filename, ui_theme_preset, ui_theme_primary, ui_theme_primary_hover, ui_theme_sidebar, ui_theme_sidebar_hover, ui_theme_accent, ui_theme_mode FROM users WHERE id = :id LIMIT 1");
$stmt->execute([":id" => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User account not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    require_csrf();

    $action = $_POST["action"] ?? "";
    $currentAvatar = trim($user["avatar_filename"] ?? "");

    if ($action === "upload_avatar") {

        if (!isset($_FILES["avatar"]) || ($_FILES["avatar"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = "Please choose an image to upload.";
        } else {
            $upload = upload_image_file(
                $_FILES["avatar"],
                __DIR__ . "/../../uploads/avatars",
                "avatar_" . $user_id,
                2097152
            );

            if (!$upload["success"]) {
                $error = $upload["error"] ?? "Unable to upload the profile picture.";
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE users SET avatar_filename = :avatar WHERE id = :id");
                    $stmt->execute([
                        ":avatar" => $upload["filename"],
                        ":id" => $user_id
                    ]);

                    delete_uploaded_file(__DIR__ . "/../../uploads/avatars", $currentAvatar);

                    $_SESSION["avatar_filename"] = $upload["filename"];
                    $user["avatar_filename"] = $upload["filename"];
                    $success = "Profile picture updated successfully.";
                } catch (Throwable $e) {
                    delete_uploaded_file(__DIR__ . "/../../uploads/avatars", $upload["filename"]);
                    $error = "Unable to save the profile picture.";
                }
            }
        }

    } elseif ($action === "save_personal_theme") {
        $presets = app_theme_presets();
        $preset = trim($_POST["ui_theme_preset"] ?? "__default__");
        if ($preset === "__default__") {
            $preset = "";
        }
        $mode = trim($_POST["ui_theme_mode"] ?? "system");
        if (($preset !== "" && !array_key_exists($preset, $presets)) || !in_array($mode, ["light","dark","system"], true)) {
            $error = "Please select a valid appearance setting.";
        }
        $colors = [
            "ui_theme_primary" => trim($_POST["ui_theme_primary"] ?? ""),
            "ui_theme_primary_hover" => trim($_POST["ui_theme_primary_hover"] ?? ""),
            "ui_theme_sidebar" => trim($_POST["ui_theme_sidebar"] ?? ""),
            "ui_theme_sidebar_hover" => trim($_POST["ui_theme_sidebar_hover"] ?? ""),
            "ui_theme_accent" => trim($_POST["ui_theme_accent"] ?? "")
        ];
        if ($error === "" && $preset !== "custom" && $preset !== "") {
            foreach ($colors as $key => $_) {
                $colors[$key] = $presets[$preset][str_replace("ui_theme_", "", $key)];
            }
        }
        if ($error === "" && $preset === "") {
            $colors = array_fill_keys(array_keys($colors), null);
        } elseif ($error === "") {
            foreach ($colors as $value) {
                if (!is_valid_hex_color($value)) {
                    $error = "Please use valid 6-digit HEX colors.";
                    break;
                }
            }
        }
        if ($error === "") {
            try {
                $stmt = $conn->prepare("UPDATE users SET ui_theme_preset=:preset, ui_theme_primary=:primary, ui_theme_primary_hover=:primary_hover, ui_theme_sidebar=:sidebar, ui_theme_sidebar_hover=:sidebar_hover, ui_theme_accent=:accent, ui_theme_mode=:mode WHERE id=:id");
                $stmt->execute([
                    ":preset"=>$preset, ":primary"=>$colors["ui_theme_primary"], ":primary_hover"=>$colors["ui_theme_primary_hover"],
                    ":sidebar"=>$colors["ui_theme_sidebar"], ":sidebar_hover"=>$colors["ui_theme_sidebar_hover"], ":accent"=>$colors["ui_theme_accent"],
                    ":mode"=>$mode, ":id"=>$user_id
                ]);
                $user = array_merge($user, $colors, ["ui_theme_preset"=>($preset === "" ? null : $preset), "ui_theme_mode"=>$mode]);
                $success = "Your personal appearance settings were saved. They apply only to your account.";
            } catch (Throwable $e) {
                $error = "Unable to save your personal appearance. Please make sure the user appearance migration is installed.";
            }
        }

    } elseif ($action === "remove_avatar") {

        if ($currentAvatar !== "") {
            $stmt = $conn->prepare("UPDATE users SET avatar_filename = NULL WHERE id = :id");
            $stmt->execute([":id" => $user_id]);
            delete_uploaded_file(__DIR__ . "/../../uploads/avatars", $currentAvatar);
        }

        $_SESSION["avatar_filename"] = "";
        $user["avatar_filename"] = "";
        $success = "Profile picture removed.";
    }
}

$avatar = trim($user["avatar_filename"] ?? "");
$avatarUrl = $avatar !== ""
    ? "/st/uploads/avatars/" . rawurlencode($avatar)
    : "";
$initial = strtoupper(substr(trim($user["full_name"] ?? "U"), 0, 1));

?>

<div class="page-header">
    <div>
        <h2>My Profile</h2>
        <p>Manage your profile picture and review your account information.</p>
    </div>
</div>

<?php if ($error !== ""): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success !== ""): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="detail-card profile-card">
    <div class="detail-card-header"><h3>Profile Picture</h3></div>
    <div class="detail-card-body">
        <div class="profile-picture-layout">
            <div class="profile-picture-preview">
                <?php if ($avatarUrl !== ""): ?>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Profile picture">
                <?php else: ?>
                    <span><?= htmlspecialchars($initial ?: "U") ?></span>
                <?php endif; ?>
            </div>

            <div class="profile-picture-actions">
                <p>Upload a JPG, JPEG, PNG, or WEBP image. Maximum size: 2 MB.</p>

                <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="form-group">
                        <label for="avatar">Choose Profile Picture</label>
                        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Picture</button>
                </form>

                <?php if ($avatarUrl !== ""): ?>
                    <form method="POST" class="profile-remove-form">
            <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_avatar">
                        <button type="submit" class="btn btn-danger">Remove Picture</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="detail-card personal-appearance-card">
    <div class="detail-card-header">
        <div><h3>My Appearance</h3><p class="detail-card-subtitle">These settings belong only to your account and follow you after login.</p></div>
    </div>
    <div class="detail-card-body">
        <form method="POST" id="personal-appearance-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_personal_theme">
            <div class="form-grid">
                <div class="form-group">
                    <label for="ui_theme_preset">Theme</label>
                    <select id="ui_theme_preset" name="ui_theme_preset">
                        <option value="__default__" <?= empty($user["ui_theme_preset"]) ? "selected" : "" ?>>Use Company Default</option>
                        <?php foreach (app_theme_presets() as $key => $preset): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= ($user["ui_theme_preset"] ?? "") === $key ? "selected" : "" ?>><?= htmlspecialchars($preset["label"]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ui_theme_mode">Display Mode</label>
                    <select id="ui_theme_mode" name="ui_theme_mode">
                        <?php foreach (["system"=>"System Default","light"=>"Light","dark"=>"Dark"] as $key=>$label): ?>
                            <option value="<?= $key ?>" <?= ($user["ui_theme_mode"] ?? "system") === $key ? "selected" : "" ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="theme-color-grid personal-theme-colors">
                <?php foreach (["ui_theme_primary"=>"Primary","ui_theme_primary_hover"=>"Primary Hover","ui_theme_sidebar"=>"Sidebar","ui_theme_sidebar_hover"=>"Sidebar Hover","ui_theme_accent"=>"Accent"] as $name=>$label): $value=$user[$name] ?? app_theme($conn)[str_replace("ui_theme_","",$name)]; ?>
                    <div class="theme-color-field">
                        <label for="<?= $name ?>"><?= $label ?></label>
                        <div class="theme-color-input"><input type="color" id="<?= $name ?>_picker" value="<?= htmlspecialchars($value) ?>"><input type="text" id="<?= $name ?>" name="<?= $name ?>" value="<?= htmlspecialchars($value) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="secondary-text">Choose a preset or use Custom-style colors. Your choices affect only your logged-in account; other users keep their own settings.</p>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Save My Appearance</button></div>
        </form>
    </div>
</div>

<div class="detail-card">
    <div class="detail-card-header"><h3>Account Information</h3></div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">Employee ID</span><strong><?= htmlspecialchars($user["employee_id"] ?? "-") ?></strong></div>
        <div class="detail-item"><span class="detail-label">Full Name</span><strong><?= htmlspecialchars($user["full_name"] ?? "-") ?></strong></div>
        <div class="detail-item"><span class="detail-label">Username</span><span><?= htmlspecialchars($user["username"] ?? "-") ?></span></div>
        <div class="detail-item"><span class="detail-label">Department</span><span><?= htmlspecialchars($user["department"] ?? "-") ?></span></div>
        <div class="detail-item"><span class="detail-label">Position</span><span><?= htmlspecialchars($user["position"] ?? "-") ?></span></div>
        <div class="detail-item"><span class="detail-label">Role</span><span><?= htmlspecialchars($user["role"] ?? "-") ?></span></div>
        <div class="detail-item"><span class="detail-label">Status</span><span><?= htmlspecialchars($user["status"] ?? "-") ?></span></div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById("personal-appearance-form");
    if (!form) return;
    const presets = <?= json_encode(app_theme_presets()) ?>;
    const fields = ["ui_theme_primary","ui_theme_primary_hover","ui_theme_sidebar","ui_theme_sidebar_hover","ui_theme_accent"];
    const syncPreset = () => {
        const preset = form.querySelector("#ui_theme_preset")?.value;
        if (preset && preset !== "custom" && preset !== "__default__" && presets[preset]) {
            fields.forEach((key) => {
                const short = key.replace("ui_theme_", "");
                const text = document.getElementById(key), picker = document.getElementById(key+"_picker");
                if (text && picker) { text.value = presets[preset][short]; picker.value = presets[preset][short]; }
            });
        }
    };
    form.querySelector("#ui_theme_preset")?.addEventListener("change", syncPreset);
    fields.forEach((key) => {
        const text=document.getElementById(key), picker=document.getElementById(key+"_picker");
        picker?.addEventListener("input",()=>text.value=picker.value.toUpperCase());
        text?.addEventListener("input",()=>{if (/^#[0-9A-Fa-f]{6}$/.test(text.value.trim())) picker.value=text.value.trim();});
    });
})();
</script>

<?php require_once "../../backend/includes/footer.php"; ?>
