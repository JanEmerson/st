<?php
require_once __DIR__ . "/backend/includes/security.php";
st_start_session();

require_once __DIR__ . "/backend/config/database.php";
require_once __DIR__ . "/backend/includes/app_config.php";

$app_name = app_name($conn);
$app_logo = app_logo_url($conn);
$login_background = app_login_background_url($conn);
$app_theme = app_theme($conn);
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

if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    header("Location: index.php");
    exit;
}

$error = $_GET["error"] ?? "";

$message = "";

if ($error === "empty") {
    $message = "Please enter your username and password.";
} elseif ($error === "invalid") {
    $message = "Invalid username or password.";
} elseif ($error === "inactive") {
    $message = "Your account is inactive. Please contact the administrator.";
}

?>

<!DOCTYPE html>
<html lang="en" style="<?= $app_theme_style ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link rel="icon" type="image/svg+xml" href="/st/frontend/assets/icons/favicon-industrial.svg?v=20261002">
    <link rel="apple-touch-icon" sizes="180x180" href="/st/frontend/assets/icons/apple-touch-industrial.png?v=20261002">

    <title><?= htmlspecialchars($app_name) ?> - Login</title>

    <script>
        try {
            const savedTheme = localStorage.getItem("st-theme");
            const prefersDark = window.matchMedia(
                "(prefers-color-scheme: dark)"
            ).matches;

            if (savedTheme === "dark" || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add("dark-mode");
            }
        } catch (error) {
            // Keep the default light theme when local storage is unavailable.
        }
    </script>

    <style>

        :root {
            --color-primary: #E0A800;
            --color-primary-rgb: 224, 168, 0;
            --color-primary-hover: #B78100;
            --color-sidebar: #272D33;
            --color-sidebar-hover: #394149;
            --color-accent: #FFE08A;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #F6F5F2;
            color: #111827;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-image: linear-gradient(rgba(11,11,11,.54), rgba(11,11,11,.54)), url('<?= htmlspecialchars($login_background, ENT_QUOTES) ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .login-container {
            position: relative;
            width: 100%;
            max-width: 440px;
            padding: 24px;
        }

        .login-card {
            background: #FFFFFF;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(234, 223, 186, 0.9);
            border-radius: 8px;
            clip-path: none;
            padding: 38px;
            box-shadow: 0 6px 22px rgba(17, 24, 39, 0.09);
        }

        .login-card::before {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 3px;
            background: var(--color-primary);
            content: "";
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            display: grid;
            width: 54px;
            height: 54px;
            margin: 0 auto 13px;
            place-items: center;
            border-radius: 8px;
            clip-path: none;
            background: var(--color-primary);
            box-shadow: none;
            color: #FFFFFF;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        .login-logo-mark img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }

        .logo p {
            margin: 0;
            color: #64748B;
            font-size: 15px;
            font-weight: 650;
            letter-spacing: -0.015em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
            color: #344157;
        }

        input {
            width: 100%;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid #D3E2D8;
            border-radius: 6px;
            clip-path: none;
            background: #F3F4F5;
            color: #263247;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        input:focus {
            border-color: var(--color-primary);
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(var(--color-primary-rgb), 0.13);
        }

        button {
            width: 100%;
            min-height: 46px;
            padding: 12px;
            border: none;
            border-radius: 6px;
            clip-path: none;
            background: var(--color-primary);
            box-shadow: none;
            color: #FFFFFF;
            font-family: inherit;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        button:hover {
            background: var(--color-primary-hover);
            box-shadow: none;
            transform: none;
        }

        button:focus-visible {
            outline: 3px solid rgba(var(--color-primary-rgb), 0.28);
            outline-offset: 2px;
        }

        .error {
            margin-bottom: 18px;
            padding: 11px;
            border: 1px solid #F2B8B2;
            border-radius: 6px;
            clip-path: none;
            background: #FDE8E5;
            color: #9F2D22;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            color: #66717A;
            font-size: 12px;
        }

        .login-theme-toggle {
            position: absolute;
            top: -24px;
            right: 24px;
            z-index: 1;
            display: inline-flex;
            width: auto;
            min-height: 36px;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border: 1px solid #D0D5D9;
            border-radius: 6px;
            clip-path: none;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: none;
            color: #435169;
            font-size: 12px;
            font-weight: 750;
        }

        .login-theme-toggle:hover {
            background: #FFF4CE;
            color: var(--color-primary-hover);
        }

        .login-theme-icon {
            color: var(--color-primary);
            font-size: 15px;
            line-height: 1;
        }

        html.dark-mode {
            color-scheme: dark;
        }

        html.dark-mode body {
            background-color: var(--color-sidebar);
            color: #F5F5F5;
        }

        body.has-login-background .login-card {
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            background: rgba(255,255,255,.94);
        }

        html.dark-mode .login-card {
            border-color: #2f2f2f;
            background: rgba(18,18,18,.96);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
        }

        html.dark-mode .logo p,
        html.dark-mode .footer {
            color: #98A7BA;
        }

        html.dark-mode label {
            color: #CBD5E1;
        }

        html.dark-mode input {
            border-color: #394149;
            background: var(--color-sidebar);
            color: #E2ECE6;
        }

        html.dark-mode input:focus {
            background: #20252A;
        }

        html.dark-mode .error {
            border-color: #7A3430;
            background: #4A2027;
            color: #FFB4BD;
        }

        html.dark-mode .login-theme-toggle {
            border-color: #394149;
            background: #272D33;
            color: #D9E3EF;
        }

        html.dark-mode .login-theme-toggle:hover {
            background: var(--color-sidebar-hover);
            color: #FFFFFF;
        }

        html.dark-mode .login-theme-icon {
            color: var(--color-accent);
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 16px;
            }

            .login-card {
                padding: 30px 24px;
            }
        }

    </style>

</head>

<body class="<?= $login_background !== "" ? "has-login-background" : "" ?>">

<div class="login-container">

    <button
        class="login-theme-toggle"
        type="button"
        aria-label="Switch to dark mode"
        aria-pressed="false"
    >
        <span class="login-theme-icon" aria-hidden="true">☾</span>
        <span class="login-theme-text">Dark</span>
    </button>

    <div class="login-card">

        <div class="logo">
            <h1 class="login-logo-mark">
                <?php if ($app_logo !== ""): ?>
                    <img src="<?= htmlspecialchars($app_logo) ?>" alt="<?= htmlspecialchars($app_name) ?> logo">
                <?php else: ?>
                    <span>ST</span>
                <?php endif; ?>
            </h1>
            <p><?= htmlspecialchars($app_name) ?></p>
        </div>

        <?php if ($message !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="backend/auth/login.php"
        >
                <?= csrf_field() ?>

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter username"
                    autocomplete="username"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button type="submit">
                Login
            </button>

        </form>

        <div class="footer">
            <?= htmlspecialchars($app_name) ?>
        </div>

    </div>

</div>

<script>
    (() => {
        const toggle = document.querySelector(".login-theme-toggle");
        const icon = document.querySelector(".login-theme-icon");
        const text = document.querySelector(".login-theme-text");
        const root = document.documentElement;

        if (!toggle || !icon || !text) {
            return;
        }

        const updateToggle = () => {
            const isDark = root.classList.contains("dark-mode");
            toggle.setAttribute("aria-pressed", String(isDark));
            toggle.setAttribute(
                "aria-label",
                isDark ? "Switch to light mode" : "Switch to dark mode"
            );
            icon.textContent = isDark ? "☀" : "☾";
            text.textContent = isDark ? "Light" : "Dark";
        };

        toggle.addEventListener("click", () => {
            const isDark = root.classList.toggle("dark-mode");

            try {
                localStorage.setItem("st-theme", isDark ? "dark" : "light");
            } catch (error) {
                // The selected theme remains active for this page.
            }

            updateToggle();
        });

        updateToggle();
    })();
</script>

</body>

</html>
