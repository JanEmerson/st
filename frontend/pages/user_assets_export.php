<?php
/*
 * ST Asset Management System
 * File: frontend/pages/user_assets_export.php
 * Purpose: Generate an Excel workbook containing the current assets assigned
 *          to one employee/user.
 * Debugging: Check user_asset_report.php for the query and xlsx_helper.php
 *             for workbook generation.
 */

require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/xlsx_helper.php";
require_once "../../backend/includes/user_asset_report.php";

/* User asset reports are an Administrator-only feature. */
if (($_SESSION["role"] ?? "") !== "Administrator") {
    http_response_code(403);
    die("Access denied.");
}

$userId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($userId <= 0) {
    http_response_code(400);
    die("Invalid user.");
}

$user = st_get_user_for_asset_report($conn, $userId);

if (!$user) {
    http_response_code(404);
    die("User not found.");
}

$assets = st_get_user_assigned_assets($conn, $userId);
$rows = st_user_asset_excel_rows($assets, $user);

/*
 * Create the workbook in the server's temporary directory so no generated
 * Excel file is permanently stored inside the public web root.
 */
$tmp = tempnam(sys_get_temp_dir(), "st_user_assets_");

if ($tmp === false) {
    http_response_code(500);
    die("Unable to create the Excel file.");
}

try {
    $instructions = [
        ["ST Asset Management - Assigned Asset Report"],
        ["Employee ID", $user["employee_id"] ?? ""],
        ["Employee Name", $user["full_name"] ?? ""],
        ["Department", $user["department"] ?? ""],
        ["Position", $user["position"] ?? ""],
        ["Current Assigned Asset Count", count($assets)],
        ["Report Date", date("Y-m-d H:i:s")],
        ["Note", "This report contains the assets currently assigned to this employee. Historical transactions remain in ST Asset History."],
    ];

    st_xlsx_create([
        "Assigned Assets" => [
            "rows" => $rows,
            "meta" => [
                "widths" => [18, 28, 20, 20, 22, 20, 16, 16, 24, 22, 16, 16, 16, 24, 20, 24, 20, 36, 40],
                "auto_filter" => true,
                "freeze_rows" => 1,
                "header_styles" => array_fill(0, count($rows[0] ?? []), 2),
            ],
        ],
        "Employee Summary" => [
            "rows" => $instructions,
            "meta" => [
                "widths" => [30, 60],
                "freeze_rows" => 0,
                "header_styles" => [7, 7],
            ],
        ],
    ], $tmp);

    $safeEmployeeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($user["employee_id"] ?? "user"));
    $filename = "ST_Assigned_Assets_" . trim($safeEmployeeId, "_") . "_" . date("Y-m-d") . ".xlsx";

    /* Prevent stale browser/proxy responses for a newly generated report. */
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Length: " . filesize($tmp));
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    readfile($tmp);
} finally {
    /* Always remove the temporary workbook after sending it. */
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

exit;
