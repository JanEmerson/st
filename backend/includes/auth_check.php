<?php
require_once __DIR__ . "/security.php";
st_start_session();

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {
    header("Location: /st/login.php");
    exit;
}
