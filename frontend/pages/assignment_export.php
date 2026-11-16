<?php
/*
 * ST Asset Management System
 * File: frontend/pages/assignment_export.php
 * Version: 1.5.1
 * Purpose: Export the currently filtered asset assignments to XLSX.
 *
 * Export design:
 * - The assignment list always contains the core accountability fields.
 * - Optional columns/sheets are controlled by the user's export options.
 * - Loose tools / linked items are exported to a separate sheet so one asset
 *   is not duplicated into multiple rows in the main assignment list.
 * - Export never changes database records.
 */

require_once "../../backend/includes/security.php";
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/xlsx_helper.php";

$role = $_SESSION["role"] ?? "";
$allowed_roles = ["Administrator", "Asset Custodian"];

if (!in_array($role, $allowed_roles, true)) {
    http_response_code(403);
    exit("Access denied.");
}

/* -------------------------------------------------------------
 | Read and normalize the filter/export options.
 | ------------------------------------------------------------- */
$search = trim($_GET["search"] ?? "");
$include_price = ($_GET["include_price"] ?? "") === "1";
$include_purchase = ($_GET["include_purchase"] ?? "") === "1";
$include_condition = ($_GET["include_condition"] ?? "") === "1";
$include_linked_items = ($_GET["include_linked_items"] ?? "") === "1";
$include_assignment_details = ($_GET["include_assignment_details"] ?? "") === "1";

/* -------------------------------------------------------------
 | Fetch the current assigned assets using the same search logic
 | as the Asset Assignments page.
 | ------------------------------------------------------------- */
$sql = "
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,
        a.purchase_cost,
        a.supplier_name,
        a.purchase_number,
        a.purchase_date,
        a.condition_status,
        a.status,
        a.description,
        u.employee_id,
        u.full_name,
        u.department,
        u.position,
        ah.assignment_date,
        ah.assigned_by,
        ah.assignment_remarks
    FROM assets a
    LEFT JOIN users u
        ON a.current_user_id = u.id
    LEFT JOIN (
        SELECT
            h.asset_id,
            h.transaction_date AS assignment_date,
            performer.full_name AS assigned_by,
            h.remarks AS assignment_remarks
        FROM asset_history h
        LEFT JOIN users performer
            ON h.performed_by = performer.id
        INNER JOIN (
            SELECT asset_id, MAX(id) AS max_id
            FROM asset_history
            WHERE action = 'ASSIGNED'
            GROUP BY asset_id
        ) latest
            ON latest.asset_id = h.asset_id
            AND latest.max_id = h.id
        WHERE h.action = 'ASSIGNED'
    ) ah
        ON ah.asset_id = a.id
    WHERE a.status = 'Assigned'
";

$params = [];

if ($search !== "") {
    $sql .= "
        AND (
            a.asset_code LIKE :search
            OR a.asset_name LIKE :search
            OR a.asset_type LIKE :search
            OR a.serial_number LIKE :search
            OR u.employee_id LIKE :search
            OR u.full_name LIKE :search
        )
    ";
    $params[":search"] = "%" . $search . "%";
}

$sql .= " ORDER BY a.asset_code ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* -------------------------------------------------------------
 | Build the main Assignment List sheet dynamically.
 | ------------------------------------------------------------- */
$headers = [
    "Asset Code",
    "Asset Name",
    "Asset Type",
    "Serial / Plate Number",
    "Employee ID",
    "Employee Name",
    "Department",
    "Position",
    "Status"
];

$widths = [18, 30, 22, 24, 18, 28, 28, 28, 16];

if ($include_price) {
    $headers[] = "Purchase Cost";
    $widths[] = 18;
}

if ($include_purchase) {
    $headers[] = "Supplier";
    $headers[] = "Purchase Number";
    $headers[] = "Purchase Date";
    $widths[] = 28;
    $widths[] = 22;
    $widths[] = 18;
}

if ($include_condition) {
    $headers[] = "Condition";
    $headers[] = "Description";
    $widths[] = 18;
    $widths[] = 42;
}

if ($include_assignment_details) {
    $headers[] = "Assignment Date";
    $headers[] = "Assigned By";
    $headers[] = "Assignment Remarks";
    $widths[] = 20;
    $widths[] = 28;
    $widths[] = 42;
}

$assignmentRows = [$headers];
$total_purchase_cost = 0.0;

foreach ($rows as $row) {
    $line = [
        $row["asset_code"] ?? "",
        $row["asset_name"] ?? "",
        $row["asset_type"] ?? "",
        $row["serial_number"] ?? "",
        $row["employee_id"] ?? "",
        $row["full_name"] ?? "",
        $row["department"] ?? "",
        $row["position"] ?? "",
        $row["status"] ?? "Assigned"
    ];

    if ($include_price) {
        $cost = ($row["purchase_cost"] !== null && $row["purchase_cost"] !== "")
            ? (float)$row["purchase_cost"]
            : 0.0;
        $total_purchase_cost += $cost;
        $line[] = $cost;
    }

    if ($include_purchase) {
        $line[] = $row["supplier_name"] ?? "";
        $line[] = $row["purchase_number"] ?? "";
        $line[] = $row["purchase_date"] ?? "";
    }

    if ($include_condition) {
        $line[] = $row["condition_status"] ?? "";
        $line[] = $row["description"] ?? "";
    }

    if ($include_assignment_details) {
        $line[] = $row["assignment_date"] ?? "";
        $line[] = $row["assigned_by"] ?? "";
        $line[] = $row["assignment_remarks"] ?? "";
    }

    $assignmentRows[] = $line;
}

/* -------------------------------------------------------------
 | Optional Loose Tools / Linked Items sheet.
 | This sheet includes quantity explicitly and only includes
 | items currently marked "With Asset".
 | ------------------------------------------------------------- */
$linkedRows = [];

if ($include_linked_items) {
    $linkedRows[] = [
        "Asset Code",
        "Asset Name",
        "Employee ID",
        "Employee Name",
        "Item Code",
        "Item Name",
        "Item Type",
        "Serial Number",
        "Quantity",
        "Condition",
        "Status",
        "Remarks"
    ];

    if (!empty($rows)) {
        $assetIds = array_values(array_filter(array_map(
            static fn($row) => (int)($row["id"] ?? 0),
            $rows
        )));

        if ($assetIds) {
            $placeholders = [];
            $itemParams = [];
            foreach ($assetIds as $index => $assetId) {
                $key = ":asset_id_" . $index;
                $placeholders[] = $key;
                $itemParams[$key] = $assetId;
            }

            $itemSql = "
                SELECT
                    li.asset_id,
                    a.asset_code,
                    a.asset_name,
                    u.employee_id,
                    u.full_name,
                    li.item_code,
                    li.item_name,
                    li.item_type,
                    li.serial_number,
                    li.quantity,
                    li.condition_status,
                    li.status,
                    li.remarks
                FROM asset_linked_items li
                INNER JOIN assets a
                    ON a.id = li.asset_id
                LEFT JOIN users u
                    ON a.current_user_id = u.id
                WHERE li.asset_id IN (" . implode(",", $placeholders) . ")
                  AND li.status = 'With Asset'
                ORDER BY a.asset_code ASC, li.id ASC
            ";

            $itemStmt = $conn->prepare($itemSql);
            $itemStmt->execute($itemParams);

            foreach ($itemStmt->fetchAll() as $item) {
                $linkedRows[] = [
                    $item["asset_code"] ?? "",
                    $item["asset_name"] ?? "",
                    $item["employee_id"] ?? "",
                    $item["full_name"] ?? "",
                    $item["item_code"] ?? "",
                    $item["item_name"] ?? "",
                    $item["item_type"] ?? "",
                    $item["serial_number"] ?? "",
                    (int)($item["quantity"] ?? 0),
                    $item["condition_status"] ?? "",
                    $item["status"] ?? "",
                    $item["remarks"] ?? ""
                ];
            }
        }
    }
}

/* -------------------------------------------------------------
 | Summary sheet explains exactly what was included in the export.
 | ------------------------------------------------------------- */
$summaryRows = [
    ["ST Asset Management System - Asset Assignments Report"],
    ["Generated", date("Y-m-d H:i:s")],
    ["Search filter", $search !== "" ? $search : "All current assignments"],
    ["Current assigned assets", count($rows)],
    ["Purchase price included", $include_price ? "Yes" : "No"],
    ["Purchase/supplier details included", $include_purchase ? "Yes" : "No"],
    ["Condition/description included", $include_condition ? "Yes" : "No"],
    ["Loose tools / linked items included", $include_linked_items ? "Yes" : "No"],
    ["Assignment details included", $include_assignment_details ? "Yes" : "No"]
];

if ($include_price) {
    $summaryRows[] = ["Total purchase cost of listed assets", $total_purchase_cost];
}

if ($include_linked_items) {
    $linkedCount = max(0, count($linkedRows) - 1);
    $linkedQuantity = 0;
    foreach (array_slice($linkedRows, 1) as $linkedRow) {
        $linkedQuantity += (int)($linkedRow[8] ?? 0);
    }
    $summaryRows[] = ["Linked item records", $linkedCount];
    $summaryRows[] = ["Linked item quantity", $linkedQuantity];
}

$summaryRows[] = [];
$summaryRows[] = ["Note"];
$summaryRows[] = ["The Assignment List reflects current accountability at export time. Loose tools / linked items are kept on a separate sheet so asset rows are not duplicated."];

$outputFile = tempnam(sys_get_temp_dir(), "st_assignments_");

try {
    $sheets = [
        "Assignment List" => [
            "rows" => $assignmentRows,
            "meta" => [
                "freeze_rows" => 1,
                "auto_filter" => true,
                "widths" => $widths
            ]
        ],
        "Summary" => [
            "rows" => $summaryRows,
            "meta" => ["widths" => [42, 90]]
        ]
    ];

    if ($include_linked_items) {
        $sheets["Loose Tools & Items"] = [
            "rows" => $linkedRows,
            "meta" => [
                "freeze_rows" => 1,
                "auto_filter" => true,
                "widths" => [18, 30, 18, 28, 18, 30, 22, 24, 12, 18, 16, 42]
            ]
        ];
    }

    st_xlsx_create($sheets, $outputFile);

    $filename = "ST_Asset_Assignments_" . date("Y-m-d_H-i-s") . ".xlsx";

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Length: " . filesize($outputFile));
    header("Cache-Control: no-store, no-cache, must-revalidate");

    readfile($outputFile);
} finally {
    if (is_file($outputFile)) {
        unlink($outputFile);
    }
}
