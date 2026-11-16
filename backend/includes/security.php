<?php
if (!function_exists('st_start_session')) {
    /* Purpose: Start the application session with the configured cookie/security settings. */
    function st_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/st',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

if (!function_exists('st_csrf_token')) {
    /* Purpose: Return the current CSRF token, creating one when necessary. */
    function st_csrf_token(): string
    {
        st_start_session();

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /* Purpose: Render the hidden CSRF input used by state-changing forms. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' .
            htmlspecialchars(st_csrf_token(), ENT_QUOTES, 'UTF-8') .
            '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /* Purpose: Validate a submitted CSRF token against the current session token. */
    function verify_csrf_token(?string $token): bool
    {
        st_start_session();
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return is_string($token) && $token !== '' && is_string($sessionToken) &&
            $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('require_csrf')) {
    /* Purpose: Stop the request when the CSRF token is missing or invalid. */
    function require_csrf(): void
    {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Security check failed. Please go back, refresh the page, and try again.');
        }
    }
}
