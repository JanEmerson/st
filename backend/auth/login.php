<?php
require_once __DIR__ . "/../includes/security.php";
st_start_session();

require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../login.php");
    exit;
}

require_csrf();

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    header("Location: ../../login.php?error=empty");
    exit;
}

$sql = "
    SELECT
        id,
        employee_id,
        username,
        password,
        full_name,
        department,
        position,
        role,
        status,
        avatar_filename,
        ui_theme_preset, ui_theme_primary, ui_theme_primary_hover, ui_theme_sidebar, ui_theme_sidebar_hover, ui_theme_accent, ui_theme_mode
    FROM users
    WHERE username = :username
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ":username" => $username
]);

$user = $stmt->fetch();

if (!$user) {
    header("Location: ../../login.php?error=invalid");
    exit;
}

if ($user["status"] !== "Active") {
    header("Location: ../../login.php?error=inactive");
    exit;
}

if (!password_verify($password, $user["password"])) {
    header("Location: ../../login.php?error=invalid");
    exit;
}

session_regenerate_id(true);

$_SESSION["logged_in"] = true;
$_SESSION["user_id"] = $user["id"];
$_SESSION["employee_id"] = $user["employee_id"];
$_SESSION["username"] = $user["username"];
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["department"] = $user["department"];
$_SESSION["position"] = $user["position"];
$_SESSION["role"] = $user["role"];
$_SESSION["avatar_filename"] = $user["avatar_filename"] ?? "";
$_SESSION["ui_theme_mode"] = $user["ui_theme_mode"] ?? "system";

header("Location: ../../index.php");
exit;
