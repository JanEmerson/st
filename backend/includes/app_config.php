<?php
if (!function_exists("get_app_settings")) {
    /* Purpose: Return the application's default theme palette. */
    function default_theme_palette(): array
    {
        return [
            "primary" => "#E0A800",
            "primary_hover" => "#B78100",
            "sidebar" => "#272D33",
            "sidebar_hover" => "#394149",
            "accent" => "#FFE08A"
        ];
    }

    /* Purpose: Load application-wide settings from the database. */
    function get_app_settings(PDO $conn): array
    {
        static $settings = null;

        if ($settings !== null) {
            return $settings;
        }

        $defaultTheme = default_theme_palette();

        $settings = [
            "app_name" => "ST Asset Management System",
            "app_logo_filename" => "",
            "app_base_url" => "",
            "login_background_filename" => "",
            "theme_preset" => "st-yellow",
            "theme_primary" => $defaultTheme["primary"],
            "theme_primary_hover" => $defaultTheme["primary_hover"],
            "theme_sidebar" => $defaultTheme["sidebar"],
            "theme_sidebar_hover" => $defaultTheme["sidebar_hover"],
            "theme_accent" => $defaultTheme["accent"]
        ];

        try {
            $stmt = $conn->query("SELECT setting_key, setting_value FROM app_settings");

            foreach ($stmt->fetchAll() as $row) {
                $settings[$row["setting_key"]] = $row["setting_value"] ?? "";
            }
        } catch (Throwable $e) {
            // Keep safe defaults when the branding migration has not been imported yet.
        }

        $settings["app_name"] = trim($settings["app_name"] ?? "") ?: "ST Asset Management System";

        return $settings;
    }


    /* Purpose: Return the predefined company theme palettes. */


    function app_theme_presets(): array
    {
        $defaultTheme = default_theme_palette();

        return [
            "st-yellow" => [
                "label" => "Safety Yellow",
                "primary" => $defaultTheme["primary"],
                "primary_hover" => $defaultTheme["primary_hover"],
                "sidebar" => $defaultTheme["sidebar"],
                "sidebar_hover" => $defaultTheme["sidebar_hover"],
                "accent" => $defaultTheme["accent"]
            ],
            "ocean" => [
                "label" => "Steel Blue",
                "primary" => "#5A7185",
                "primary_hover" => "#405767",
                "sidebar" => "#202A33",
                "sidebar_hover" => "#364450",
                "accent" => "#B9C9D5"
            ],
            "slate" => [
                "label" => "Graphite",
                "primary" => "#68727B",
                "primary_hover" => "#4B535B",
                "sidebar" => "#20252A",
                "sidebar_hover" => "#343B42",
                "accent" => "#CAD0D5"
            ],
            "purple" => [
                "label" => "Safety Orange",
                "primary" => "#D77B16",
                "primary_hover" => "#A9570C",
                "sidebar" => "#352A20",
                "sidebar_hover" => "#4A3827",
                "accent" => "#FFC36F"
            ],
            "custom" => [
                "label" => "Custom",
                "primary" => $defaultTheme["primary"],
                "primary_hover" => $defaultTheme["primary_hover"],
                "sidebar" => $defaultTheme["sidebar"],
                "sidebar_hover" => $defaultTheme["sidebar_hover"],
                "accent" => $defaultTheme["accent"]
            ]
        ];
    }

    /* Purpose: Validate a six-digit hexadecimal color value. */

    function is_valid_hex_color(string $value): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', trim($value));
    }

    /* Purpose: Resolve the current company-wide theme configuration. */

    function app_theme(PDO $conn): array
    {
        $settings = get_app_settings($conn);
        $presets = app_theme_presets();
        $presetKey = $settings["theme_preset"] ?? "st-yellow";
        $preset = $presets[$presetKey] ?? $presets["st-yellow"];

        $keys = ["primary", "primary_hover", "sidebar", "sidebar_hover", "accent"];
        $theme = [];
        foreach ($keys as $key) {
            $stored = trim((string)($settings["theme_{$key}"] ?? ""));
            $theme[$key] = $presetKey === "custom" && is_valid_hex_color($stored) ? $stored : $preset[$key];
        }
        $theme["preset"] = array_key_exists($presetKey, $presets) ? $presetKey : "st-yellow";
        $theme["label"] = $presets[$theme["preset"]]["label"];
        return $theme;
    }


    /* Purpose: Build the URL for the configured login background image. */


    function app_login_background_url(PDO $conn): string
    {
        $filename = trim(get_app_settings($conn)["login_background_filename"] ?? "");
        if ($filename === "") {
            return "";
        }
        return "/st/uploads/app/login-background/" . rawurlencode($filename);
    }

    /* Purpose: Resolve the appearance settings for the current user. */

    function user_theme(PDO $conn, int $userId): array
    {
        $default = app_theme($conn);
        $mode = "system";
        if ($userId <= 0) {
            $default["mode"] = $mode;
            return $default;
        }
        try {
            $stmt = $conn->prepare("SELECT ui_theme_preset, ui_theme_primary, ui_theme_primary_hover, ui_theme_sidebar, ui_theme_sidebar_hover, ui_theme_accent, ui_theme_mode FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([":id" => $userId]);
            $row = $stmt->fetch() ?: [];
            $presetKey = trim((string)($row["ui_theme_preset"] ?? ""));
            $presets = app_theme_presets();
            $preset = $presets[$presetKey] ?? $default;
            $theme = [];
            foreach (["primary", "primary_hover", "sidebar", "sidebar_hover", "accent"] as $key) {
                $stored = trim((string)($row["ui_theme_{$key}"] ?? ""));
                $theme[$key] = $presetKey === "custom" && is_valid_hex_color($stored) ? $stored : ($preset[$key] ?? $default[$key]);
            }
            $theme["preset"] = array_key_exists($presetKey, $presets) ? $presetKey : $default["preset"];
            $theme["label"] = $presets[$theme["preset"]]["label"] ?? $default["label"];
            $theme["mode"] = in_array(($row["ui_theme_mode"] ?? "system"), ["light","dark","system"], true) ? $row["ui_theme_mode"] : "system";
            return $theme;
        } catch (Throwable $e) {
            $default["mode"] = $mode;
            return $default;
        }
    }

    /* Purpose: Return the current authenticated user's role. */

    function current_user_role(): string
    {
        return trim((string)($_SESSION["role"] ?? ""));
    }

    /* Purpose: Whether the provided role is allowed for the requested access group. */

    function user_has_role(string $role, array $allowedRoles): bool
    {
        return in_array($role, $allowedRoles, true);
    }

    /* Purpose: Require the current user role to match one of the allowed roles. */

    function require_role(array $allowedRoles, string $redirect = "/st/index.php"): void
    {
        if (!user_has_role(current_user_role(), $allowedRoles)) {
            header("Location: " . $redirect);
            exit;
        }
    }

    /* Purpose: Require the current user to be an administrator. */

    function require_admin_role(string $redirect = "/st/index.php"): void
    {
        if (!is_administrator_role(current_user_role())) {
            header("Location: " . $redirect);
            exit;
        }
    }

    /* Purpose: Whether the provided role is the administrator role. */

    function is_administrator_role(string $role): bool
    {
        return $role === "Administrator";
    }

    /* Purpose: Return the configured application name. */

    function app_name(PDO $conn): string
    {
        return get_app_settings($conn)["app_name"];
    }

    /* Purpose: Return the configured LAN/base URL used by the application. */

    function app_base_url(PDO $conn): string
    {
        $configured = trim(get_app_settings($conn)["app_base_url"] ?? "");
        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $requestHost = trim((string)($_SERVER["HTTP_HOST"] ?? ""));
        $fallbackHost = $requestHost !== "" ? $requestHost : "localhost";

        if ($configured !== "") {
            $normalized = rtrim($configured, "/");
            $isLocalhostConfig = preg_match('/^https?:\/\/(localhost|127\.0\.0\.1|\[::1\]|::1)(?::\d+)?(?:\/.*)?$/i', $normalized) === 1;

            if ($isLocalhostConfig && $requestHost !== "") {
                return $scheme . "://" . $fallbackHost . "/st";
            }

            return $normalized;
        }

        return $scheme . "://" . $fallbackHost . "/st";
    }

    /* Purpose: Build the URL for the configured application logo. */

    function app_logo_url(PDO $conn): string
    {
        $filename = trim(get_app_settings($conn)["app_logo_filename"] ?? "");

        if ($filename === "") {
            return "";
        }

        return "/st/uploads/app/" . rawurlencode($filename);
    }
}
