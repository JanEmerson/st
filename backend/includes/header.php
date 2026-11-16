<?php
ob_start();

require_once __DIR__ . "/security.php";
st_start_session();

require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/app_config.php";

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
header("Permissions-Policy: geolocation=(), microphone=()");


$page_title = $page_title ?? "ST Asset Management System";

$app_name = app_name($conn);
$app_logo = app_logo_url($conn);
$user_id = (int)($_SESSION["user_id"] ?? 0);
$app_theme = user_theme($conn, $user_id);
$primaryRgb = implode(", ", array_map("hexdec", str_split(substr($app_theme["primary"], 1), 2)));
$app_theme_style = sprintf(
    '--color-primary:%s;--color-primary-rgb:%s;--color-primary-hover:%s;--color-sidebar:%s;--color-sidebar-hover:%s;--color-accent:%s;',
    htmlspecialchars($app_theme["primary"], ENT_QUOTES),
    $primaryRgb,
    htmlspecialchars($app_theme["primary_hover"], ENT_QUOTES),
    htmlspecialchars($app_theme["sidebar"], ENT_QUOTES),
    htmlspecialchars($app_theme["sidebar_hover"], ENT_QUOTES),
    htmlspecialchars($app_theme["accent"], ENT_QUOTES)
);

$user_name = $_SESSION["full_name"] ?? "User";
$user_initial = strtoupper(substr(trim($user_name), 0, 1));
$user_avatar = trim($_SESSION["avatar_filename"] ?? "");

// Keep the current session avatar synchronized for existing sessions.
if ($user_avatar === "" && !empty($_SESSION["user_id"])) {
    try {
        $avatarStmt = $conn->prepare("SELECT avatar_filename FROM users WHERE id = :id LIMIT 1");
        $avatarStmt->execute([":id" => (int)$_SESSION["user_id"]]);
        $avatarRow = $avatarStmt->fetch();
        $user_avatar = trim($avatarRow["avatar_filename"] ?? "");
        $_SESSION["avatar_filename"] = $user_avatar;
    } catch (Throwable $e) {
        // The profile-image migration may not be installed yet.
    }
}

$user_avatar_url = $user_avatar !== ""
    ? "/st/uploads/avatars/" . rawurlencode($user_avatar)
    : "";

?>

<!DOCTYPE html>
<html lang="en" data-ui-mode="<?= htmlspecialchars($app_theme["mode"], ENT_QUOTES) ?>" style="<?= $app_theme_style ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link rel="icon" type="image/svg+xml" href="/st/frontend/assets/icons/favicon-industrial.svg?v=20261002">
    <link rel="apple-touch-icon" sizes="180x180" href="/st/frontend/assets/icons/apple-touch-industrial.png?v=20261002">

    <title>
        <?= htmlspecialchars($page_title . " - " . $app_name) ?>
    </title>

    <script>
        try {
            const uiMode = document.documentElement.dataset.uiMode || "system";
            const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
            if (uiMode === "dark" || (uiMode === "system" && prefersDark)) {
                document.documentElement.classList.add("dark-mode");
            }
        } catch (error) {}
    </script>

    <link
        rel="stylesheet"
        href="/st/frontend/css/style.css?v=20261008-ux16"
    >

    <script
        src="/st/frontend/js/app.js?v=20261008-ux16"
        defer
    ></script>

</head>

<body data-csrf-token="<?= htmlspecialchars(st_csrf_token(), ENT_QUOTES) ?>">

<div class="app">

    <?php require_once __DIR__ . "/sidebar.php"; ?>

    <div class="main-area">

        <header class="topbar">

            <div class="topbar-leading">

                <button
                    class="menu-toggle"
                    type="button"
                    aria-label="Open navigation"
                    aria-controls="app-sidebar"
                    aria-expanded="false"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div>
                    <p class="topbar-kicker">
                        <?= htmlspecialchars($app_name) ?>
                    </p>

                    <h1>
                        <?= htmlspecialchars($page_title) ?>
                    </h1>
                </div>
            </div>

            <div class="topbar-actions">

                <a
                    href="/st/frontend/pages/user_guide.php"
                    class="topbar-help-link"
                    title="Open the simple User Guide"
                    aria-label="Open User Guide"
                >
                    <span aria-hidden="true">?</span>
                    <span>Help</span>
                </a>

                <button
                    class="theme-toggle"
                    type="button"
                    aria-label="Switch to dark mode"
                    aria-pressed="false"
                >
                    <span class="theme-toggle-icon" aria-hidden="true">☾</span>
                    <span class="theme-toggle-text">Dark</span>
                </button>

                <div class="user-info">
                    <div class="user-copy">
                    <strong>
                        <?= htmlspecialchars($user_name) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($_SESSION["role"] ?? "") ?>
                    </span>
                    </div>

                <?php if ($user_avatar_url !== ""): ?>
                    <img
                        class="user-avatar user-avatar-image"
                        src="<?= htmlspecialchars($user_avatar_url) ?>"
                        alt="Profile picture of <?= htmlspecialchars($user_name) ?>"
                    >
                <?php else: ?>
                    <span class="user-avatar" aria-hidden="true">
                        <?= htmlspecialchars($user_initial ?: "U") ?>
                    </span>
                <?php endif; ?>

                </div>

            </div>

        </header>

        <main class="content">
