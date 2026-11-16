<?php
$page_title = "Settings";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/app_config.php";
require_once "../../backend/includes/upload_helpers.php";

if (!is_administrator_role(current_user_role())) {
    http_response_code(403);
    die("Access denied.");
}

$user_id = (int)($_SESSION["user_id"] ?? 0);
$error = "";
$success = "";

$app_settings = get_app_settings($conn);
$current_app_name = $app_settings["app_name"] ?? "ST Asset Management System";
$current_base_url = trim($app_settings["app_base_url"] ?? "") ?: "http://172.18.20.100/st";
$current_logo = trim($app_settings["app_logo_filename"] ?? "");
$current_logo_url = app_logo_url($conn);
$current_login_background = trim($app_settings["login_background_filename"] ?? "");
$current_login_background_url = app_login_background_url($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
}

/* Save application branding */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_branding") {

    $new_app_name = trim($_POST["app_name"] ?? "");

    if ($new_app_name === "") {
        $error = "Application name is required.";
    } elseif (mb_strlen($new_app_name) < 2 || mb_strlen($new_app_name) > 100) {
        $error = "Application name must be between 2 and 100 characters.";
    }

    $new_logo = $current_logo;
    $logo_uploaded = false;

    if ($error === "" && isset($_FILES["app_logo"]) && ($_FILES["app_logo"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = upload_image_file(
            $_FILES["app_logo"],
            __DIR__ . "/../../uploads/app",
            "app_logo",
            2097152
        );

        if (!$upload["success"]) {
            $error = $upload["error"] ?? "Unable to upload the logo.";
        } else {
            $new_logo = $upload["filename"];
            $logo_uploaded = true;
        }
    }

    if ($error === "") {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('app_name', :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([":value" => $new_app_name]);

            $remove_logo = isset($_POST["remove_logo"]) && $_POST["remove_logo"] === "1";

            if ($remove_logo && !$logo_uploaded) {
                $new_logo = "";
            }

            $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('app_logo_filename', :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([":value" => $new_logo]);

            $conn->commit();

            if ($current_logo !== "" && $current_logo !== $new_logo) {
                delete_uploaded_file(__DIR__ . "/../../uploads/app", $current_logo);
            }

            $current_app_name = $new_app_name;
            $current_logo = $new_logo;
            $current_logo_url = $current_logo !== ""
                ? "/st/uploads/app/" . rawurlencode($current_logo)
                : "";
            $success = "Application branding updated successfully.";

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            if ($logo_uploaded && $new_logo !== $current_logo) {
                delete_uploaded_file(__DIR__ . "/../../uploads/app", $new_logo);
            }

            $error = "Unable to save application branding. Please make sure the branding database update has been imported.";
        }
    }
}

/* Save login background - Administrator only */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_login_background") {
    $new_background = $current_login_background;
    $uploaded = false;
    if (isset($_FILES["login_background"]) && ($_FILES["login_background"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = upload_image_file($_FILES["login_background"], __DIR__ . "/../../uploads/app/login-background", "login_bg", 8388608);
        if (!$upload["success"]) { $error = $upload["error"] ?? "Unable to upload the login background."; }
        else { $new_background = $upload["filename"]; $uploaded = true; }
    }
    $remove = isset($_POST["remove_login_background"]) && $_POST["remove_login_background"] === "1";
    if ($error === "" && $remove && !$uploaded) $new_background = "";
    if ($error === "") {
        try {
            $stmt=$conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('login_background_filename', :value) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $stmt->execute([":value"=>$new_background]);
            if ($current_login_background !== "" && $current_login_background !== $new_background) delete_uploaded_file(__DIR__ . "/../../uploads/app/login-background", $current_login_background);
            $current_login_background=$new_background; $current_login_background_url=$new_background !== "" ? "/st/uploads/app/login-background/" . rawurlencode($new_background) : "";
            $success="Login background updated successfully.";
        } catch (Throwable $e) {
            if ($uploaded && $new_background !== $current_login_background) delete_uploaded_file(__DIR__ . "/../../uploads/app/login-background", $new_background);
            $error="Unable to save the login background. Please import the latest database migration.";
        }
    }
}

/* Save QR / LAN base URL */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_qr_settings") {
    $new_base_url = rtrim(trim($_POST["app_base_url"] ?? ""), "/");

    if ($new_base_url === "") {
        $error = "QR Base URL is required.";
    } elseif (!filter_var($new_base_url, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL, for example http://172.18.20.100/st";
    } elseif (!preg_match('#^https?://#i', $new_base_url)) {
        $error = "QR Base URL must start with http:// or https://";
    } elseif (preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\]|::1)(?::\d+)?/?$#i', $new_base_url) === 1) {
        $error = "Please use your computer's LAN IP, not localhost, so phones can access the QR links.";
    }

    if ($error === "") {
        try {
            $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('app_base_url', :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([":value" => $new_base_url]);
            $current_base_url = $new_base_url;
            $success = "QR Code / LAN Base URL saved successfully.";
        } catch (Throwable $e) {
            $error = "Unable to save the QR Base URL. Please make sure the QR database update has been imported.";
        }
    }
}

/* Save appearance / theme */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_theme") {
    $presets = app_theme_presets();
    $preset = trim($_POST["theme_preset"] ?? "st-yellow");

    if (!array_key_exists($preset, $presets)) {
        $error = "Please select a valid theme.";
    }

    $colors = [
        "theme_primary" => trim($_POST["theme_primary"] ?? ""),
        "theme_primary_hover" => trim($_POST["theme_primary_hover"] ?? ""),
        "theme_sidebar" => trim($_POST["theme_sidebar"] ?? ""),
        "theme_sidebar_hover" => trim($_POST["theme_sidebar_hover"] ?? ""),
        "theme_accent" => trim($_POST["theme_accent"] ?? "")
    ];

    if ($error === "" && $preset !== "custom") {
        foreach ($colors as $key => $_) {
            $colors[$key] = $presets[$preset][str_replace("theme_", "", $key)];
        }
    }

    if ($error === "") {
        foreach ($colors as $key => $value) {
            if (!is_valid_hex_color($value)) {
                $error = "Please use valid 6-digit HEX colors for the custom theme.";
                break;
            }
        }
    }

    if ($error === "") {
        try {
            $conn->beginTransaction();
            $pairs = array_merge(["theme_preset" => $preset], $colors);
            foreach ($pairs as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([":key" => $key, ":value" => $value]);
            }
            $conn->commit();
            $success = "Default appearance theme updated successfully.";
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "Unable to save the appearance theme.";
        }
    }
}

$app_theme = app_theme($conn);
$app_theme_presets = app_theme_presets();

/* Current account */
$stmt = $conn->prepare("SELECT employee_id, username, full_name, department, position, role, status, avatar_filename FROM users WHERE id = :id LIMIT 1");
$stmt->execute([":id" => $user_id]);
$current_user = $stmt->fetch();

if (!$current_user) {
    die("User account not found.");
}

$employee_id = htmlspecialchars($current_user["employee_id"] ?? "-");
$full_name = htmlspecialchars($current_user["full_name"] ?? "-");
$username = htmlspecialchars($current_user["username"] ?? "-");
$department = htmlspecialchars($current_user["department"] ?? "-");
$position = htmlspecialchars($current_user["position"] ?? "-");
$user_role = htmlspecialchars($current_user["role"] ?? "-");
$status = htmlspecialchars($current_user["status"] ?? "-");
$status_class = strtolower($current_user["status"] ?? "unknown");

?>

<div class="page-header">
    <div>
        <h2>Settings</h2>
        <p>System configuration and account information.</p>
    </div>
</div>

<?php if ($error !== ""): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success !== ""): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="detail-card branding-settings-card">
    <div class="detail-card-header">
        <h3>Application Branding</h3>
    </div>

    <div class="detail-card-body">
        <p>Change the application name and upload the logo used by the sidebar and login screen.</p>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_branding">

            <div class="branding-preview-row">
                <div class="branding-logo-preview">
                    <?php if ($current_logo_url !== ""): ?>
                        <img src="<?= htmlspecialchars($current_logo_url) ?>" alt="Current application logo">
                    <?php else: ?>
                        <span>ST</span>
                    <?php endif; ?>
                </div>

                <div>
                    <strong>Current Logo</strong>
                    <p class="secondary-text">JPG, JPEG, PNG, or WEBP. Maximum 2 MB.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group form-group-full">
                    <label for="app_name">Application Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="app_name"
                        name="app_name"
                        maxlength="100"
                        value="<?= htmlspecialchars($current_app_name) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="app_logo">Upload New Logo</label>
                    <input
                        type="file"
                        id="app_logo"
                        name="app_logo"
                        accept="image/jpeg,image/png,image/webp"
                    >
                </div>

                <?php if ($current_logo !== ""): ?>
                    <div class="form-group branding-remove-logo">
                        <label>&nbsp;</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="remove_logo" value="1">
                            Remove current logo
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Branding</button>
            </div>
        </form>
    </div>
</div>

<div class="detail-card login-background-card">
    <div class="detail-card-header"><div><h3>Login Background</h3><p class="detail-card-subtitle">Administrator-only global background shown before users sign in.</p></div></div>
    <div class="detail-card-body">
        <?php if ($current_login_background_url !== ""): ?>
            <div class="login-background-preview"><img src="<?= htmlspecialchars($current_login_background_url) ?>" alt="Current login background"></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_login_background">
            <div class="form-grid">
                <div class="form-group">
                    <label for="login_background">Upload HD Login Background</label>
                    <input type="file" id="login_background" name="login_background" accept="image/jpeg,image/png,image/webp">
                    <p class="secondary-text">JPG, PNG or WEBP. Maximum 8 MB. Recommended landscape image such as 1920×1080 or higher.</p>
                </div>
                <?php if ($current_login_background !== ""): ?>
                    <div class="form-group"><label>&nbsp;</label><label class="checkbox-label"><input type="checkbox" name="remove_login_background" value="1"> Remove current background</label></div>
                <?php endif; ?>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Save Login Background</button></div>
        </form>
    </div>
</div>

<div class="detail-card appearance-settings-card">
    <div class="detail-card-header">
        <div>
            <h3>Default Appearance &amp; Theme</h3>
            <p class="detail-card-subtitle">Set the default application colors for users who have not chosen personal appearance settings.</p>
        </div>
        <span class="theme-current-badge">Current: <?= htmlspecialchars($app_theme["label"]) ?></span>
    </div>

    <div class="detail-card-body">
        <form method="POST" id="appearance-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_theme">

            <div class="theme-preset-grid">
                <?php foreach ($app_theme_presets as $key => $preset): ?>
                    <label class="theme-preset-card<?= $app_theme["preset"] === $key ? " is-selected" : "" ?>">
                        <input type="radio" name="theme_preset" value="<?= htmlspecialchars($key) ?>" <?= $app_theme["preset"] === $key ? "checked" : "" ?>>
                        <span class="theme-swatch" style="--sw-primary:<?= htmlspecialchars($preset["primary"]) ?>;--sw-sidebar:<?= htmlspecialchars($preset["sidebar"]) ?>;--sw-accent:<?= htmlspecialchars($preset["accent"]) ?>;">
                            <i></i><i></i><i></i>
                        </span>
                        <strong><?= htmlspecialchars($preset["label"]) ?></strong>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="custom-theme-panel" id="custom-theme-panel">
                <div class="theme-panel-heading">Custom Colors</div>
                <p class="secondary-text">Use HEX colors such as <code>#E0A800</code>. Custom colors are enabled when the Custom theme is selected.</p>
                <div class="theme-color-grid">
                    <?php
                    $colorFields = [
                        "theme_primary" => "Primary",
                        "theme_primary_hover" => "Primary Hover",
                        "theme_sidebar" => "Sidebar",
                        "theme_sidebar_hover" => "Sidebar Hover",
                        "theme_accent" => "Accent"
                    ];
                    foreach ($colorFields as $name => $label):
                    ?>
                        <div class="theme-color-field">
                            <label for="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($label) ?></label>
                            <div class="theme-color-input">
                                <input type="color" id="<?= htmlspecialchars($name) ?>_picker" value="<?= htmlspecialchars($app_theme[str_replace("theme_", "", $name)]) ?>" aria-label="<?= htmlspecialchars($label) ?> color picker">
                                <input type="text" id="<?= htmlspecialchars($name) ?>" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($app_theme[str_replace("theme_", "", $name)]) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Appearance</button>
            </div>
        </form>
    </div>
</div>

<div class="detail-card qr-settings-card">
    <div class="detail-card-header"><h3>QR Code / LAN Settings</h3></div>
    <div class="detail-card-body">
        <p>Set the address that phones on your local network should open after scanning an ST asset QR code.</p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_qr_settings">
            <div class="form-group form-group-full">
                <label for="app_base_url">QR Base URL <span class="required">*</span></label>
                <input
                    type="url"
                    id="app_base_url"
                    name="app_base_url"
                    value="<?= htmlspecialchars($current_base_url) ?>"
                    placeholder="http://172.18.20.100/st"
                    required
                >
                <p class="secondary-text">For your current LAN setup, use <strong>http://172.18.20.100/st</strong>. Do not use localhost because phones cannot reach the PC's localhost.</p>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save QR Settings</button>
            </div>
        </form>
        <div class="qr-http-notice">
            <strong>HTTP / LAN mode</strong>
            <span>QR scanning is intended for mobile devices. The QR Scanner opens from the navigation on mobile and uses the phone camera to capture the QR code, which avoids requiring HTTPS for direct camera APIs.</span>
        </div>
    </div>
</div>

<div class="detail-card">
    <div class="detail-card-header"><h3>System Information</h3></div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">System Name</span><strong><?= htmlspecialchars($current_app_name) ?></strong></div>
        <div class="detail-item"><span class="detail-label">Version</span><span>1.5.1</span></div>
        <div class="detail-item"><span class="detail-label">Database</span><span>st_db</span></div>
        <div class="detail-item"><span class="detail-label">Environment</span><span>Localhost / XAMPP</span></div>
    </div>
</div>

<div class="detail-card">
    <div class="detail-card-header"><h3>Current Account</h3></div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">Employee ID</span><strong><?= $employee_id ?></strong></div>
        <div class="detail-item"><span class="detail-label">Full Name</span><strong><?= $full_name ?></strong></div>
        <div class="detail-item"><span class="detail-label">Username</span><span><?= $username ?></span></div>
        <div class="detail-item"><span class="detail-label">Department</span><span><?= $department ?></span></div>
        <div class="detail-item"><span class="detail-label">Position</span><span><?= $position ?></span></div>
        <div class="detail-item"><span class="detail-label">Role</span><span><?= $user_role ?></span></div>
        <div class="detail-item"><span class="detail-label">Status</span><span class="status-badge status-<?= htmlspecialchars($status_class) ?>"><?= $status ?></span></div>
    </div>
</div>

<div class="detail-card">
    <div class="detail-card-header"><h3>Security</h3></div>
    <div class="detail-card-body">
        <p>Manage your account password.</p>
        <a href="change_password.php" class="btn btn-primary">Change Password</a>
    </div>
</div>

<div class="detail-card">
    <div class="detail-card-header"><h3>Application Information</h3></div>
    <div class="detail-card-body">
        <p><strong><?= htmlspecialchars($current_app_name) ?></strong></p>
        <p>Asset inventory, assignment, transfer, turnover, history, and employee clearance management system.</p>
        <p class="secondary-text">Running on localhost using XAMPP, PHP, and MySQL/MariaDB.</p>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById("appearance-form");
    if (!form) return;
    const customPanel = document.getElementById("custom-theme-panel");
    const radios = form.querySelectorAll('input[name="theme_preset"]');
    const colorFields = ["theme_primary","theme_primary_hover","theme_sidebar","theme_sidebar_hover","theme_accent"];

    const syncCustomState = () => {
        const selected = form.querySelector('input[name="theme_preset"]:checked');
        const isCustom = selected && selected.value === "custom";
        customPanel.classList.toggle("is-disabled", !isCustom);
        customPanel.querySelectorAll("input").forEach(input => input.disabled = !isCustom);
    };

    radios.forEach(radio => radio.addEventListener("change", () => {
        const preset = <?= json_encode($app_theme_presets) ?>[radio.value];
        if (radio.value !== "custom" && preset) {
            colorFields.forEach(key => {
                const shortKey = key.replace("theme_", "");
                const text = document.getElementById(key);
                const picker = document.getElementById(key + "_picker");
                if (text && picker) { text.value = preset[shortKey]; picker.value = preset[shortKey]; }
            });
        }
        form.querySelectorAll(".theme-preset-card").forEach(card => card.classList.remove("is-selected"));
        radio.closest(".theme-preset-card")?.classList.add("is-selected");
        syncCustomState();
    }));

    colorFields.forEach(key => {
        const text = document.getElementById(key);
        const picker = document.getElementById(key + "_picker");
        picker?.addEventListener("input", () => { text.value = picker.value.toUpperCase(); });
        text?.addEventListener("input", () => {
            const value = text.value.trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) picker.value = value;
        });
    });
    syncCustomState();
})();
</script>

<?php require_once "../../backend/includes/footer.php"; ?>
