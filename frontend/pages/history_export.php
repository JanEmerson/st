<?php
/*
 * ST Asset Management System
 * File: frontend/pages/history_export.php
 * Purpose: Export filtered asset history records to XLSX.
 */

require_once "../../backend/includes/security.php";
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/xlsx_helper.php";

$allowedRoles = ["Administrator", "Asset Custodian", "HR / Clearance Officer", "Viewer"];
if (!in_array($_SESSION["role"] ?? "", $allowedRoles, true)) {
    http_response_code(403);
    exit("Access denied.");
}

$search = trim($_GET["search"] ?? "");
$action = $_GET["action"] ?? "";
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");
$includePrice = ($_GET["include_price"] ?? "") === "1";
$includePurchase = ($_GET["include_purchase"] ?? "") === "1";
$includeCondition = ($_GET["include_condition"] ?? "") === "1";
$includeLinkedItems = ($_GET["include_linked_items"] ?? "") === "1";
$includeAssignmentDetails = ($_GET["include_assignment_details"] ?? "") === "1";

if (!in_array($action, ["", "ASSIGNED", "TRANSFERRED", "RETURNED"], true)) {
    $action = "";
}

foreach (["dateFrom", "dateTo"] as $dateField) {
    $dateValue = $$dateField;
    $parsedDate = DateTime::createFromFormat("!Y-m-d", $dateValue);
    if ($dateValue !== "" && (!$parsedDate || $parsedDate->format("Y-m-d") !== $dateValue)) {
        $$dateField = "";
    }
}

$sql = "
    SELECT
        h.asset_id,
        h.transaction_date,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,
        a.purchase_cost,
        a.supplier_name,
        a.purchase_number,
        a.purchase_date,
        a.description,
        h.action,
        from_user.employee_id AS from_employee_id,
        from_user.full_name AS from_full_name,
        to_user.employee_id AS to_employee_id,
        to_user.full_name AS to_full_name,
        performer.full_name AS performed_by_name,
        h.condition_status,
        h.remarks
    FROM asset_history h
    INNER JOIN assets a ON h.asset_id = a.id
    LEFT JOIN users from_user ON h.from_user_id = from_user.id
    LEFT JOIN users to_user ON h.to_user_id = to_user.id
    INNER JOIN users performer ON h.performed_by = performer.id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= "
        AND (
            a.asset_code LIKE :search
            OR a.asset_name LIKE :search
            OR a.asset_type LIKE :search
            OR a.serial_number LIKE :search
            OR h.action LIKE :search
            OR h.condition_status LIKE :search
            OR from_user.employee_id LIKE :search
            OR from_user.full_name LIKE :search
            OR to_user.employee_id LIKE :search
            OR to_user.full_name LIKE :search
            OR performer.full_name LIKE :search
            OR h.remarks LIKE :search
        )
    ";
    $params[":search"] = "%" . $search . "%";
}

if ($action !== "") {
    $sql .= " AND h.action = :action";
    $params[":action"] = $action;
}
if ($dateFrom !== "") {
    $sql .= " AND h.transaction_date >= :date_from";
    $params[":date_from"] = $dateFrom;
}
if ($dateTo !== "") {
    $sql .= " AND h.transaction_date < DATE_ADD(:date_to, INTERVAL 1 DAY)";
    $params[":date_to"] = $dateTo;
}

$sql .= " ORDER BY h.transaction_date DESC, h.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$headers = [
    "Date / Time",
    "Asset Code",
    "Asset Name",
    "Asset Type",
    "Serial / Plate Number",
    "Action",
    "From Employee ID",
    "From Employee Name",
    "To Employee ID",
    "To Employee Name",
    "Performed By",
    "Remarks"
];
$widths = [22, 18, 30, 22, 24, 18, 20, 28, 20, 28, 28, 44];

if ($includePrice) {
    $headers[] = "Purchase Cost";
    $widths[] = 18;
}
if ($includePurchase) {
    $headers[] = "Supplier";
    $headers[] = "Purchase Number";
    $headers[] = "Purchase Date";
    array_push($widths, 28, 22, 18);
}
if ($includeCondition) {
    $headers[] = "Condition at Transaction";
    $headers[] = "Asset Description";
    array_push($widths, 24, 44);
}

$rows = [$headers];

foreach ($records as $record) {
    $row = [
        $record["transaction_date"] ?? "",
        $record["asset_code"] ?? "",
        $record["asset_name"] ?? "",
        $record["asset_type"] ?? "",
        $record["serial_number"] ?? "",
        $record["action"] ?? "",
        $record["from_employee_id"] ?? "",
        $record["from_full_name"] ?? "",
        $record["to_employee_id"] ?? "",
        $record["to_full_name"] ?? "",
        $record["performed_by_name"] ?? "",
        $record["remarks"] ?? ""
    ];

    if ($includePrice) {
        $row[] = $record["purchase_cost"] ?? "";
    }
    if ($includePurchase) {
        $row[] = $record["supplier_name"] ?? "";
        $row[] = $record["purchase_number"] ?? "";
        $row[] = $record["purchase_date"] ?? "";
    }
    if ($includeCondition) {
        $row[] = $record["condition_status"] ?? "";
        $row[] = $record["description"] ?? "";
    }
    $rows[] = $row;
}

$assetIds = array_values(array_unique(array_filter(array_map(
    static fn($record) => (int)($record["asset_id"] ?? 0),
    $records
))));

$linkedRows = [[
    "Asset Code", "Asset Name", "Current Employee ID", "Current Employee Name",
    "Item Code", "Item Name", "Item Type", "Serial Number", "Quantity",
    "Condition", "Status", "Remarks"
]];
$assignmentRows = [[
    "Asset Code", "Asset Name", "Assignment Date", "Employee ID",
    "Employee Name", "Assigned By", "Assignment Remarks"
]];

if ($assetIds && ($includeLinkedItems || $includeAssignmentDetails)) {
    $idPlaceholders = [];
    $idParams = [];
    foreach ($assetIds as $index => $assetId) {
        $key = ":asset_id_" . $index;
        $idPlaceholders[] = $key;
        $idParams[$key] = $assetId;
    }
    $inClause = implode(", ", $idPlaceholders);

    if ($includeLinkedItems) {
        $linkedStmt = $conn->prepare("
            SELECT
                a.asset_code,
                a.asset_name,
                asset_user.employee_id,
                asset_user.full_name,
                li.item_code,
                li.item_name,
                li.item_type,
                li.serial_number,
                li.quantity,
                li.condition_status,
                li.status,
                li.remarks
            FROM asset_linked_items li
            INNER JOIN assets a ON a.id = li.asset_id
            LEFT JOIN users asset_user ON asset_user.id = a.current_user_id
            WHERE li.asset_id IN ($inClause)
              AND li.status = 'With Asset'
            ORDER BY a.asset_code ASC, li.id ASC
        ");
        $linkedStmt->execute($idParams);
        foreach ($linkedStmt->fetchAll() as $item) {
            $linkedRows[] = [
                $item["asset_code"] ?? "", $item["asset_name"] ?? "",
                $item["employee_id"] ?? "", $item["full_name"] ?? "",
                $item["item_code"] ?? "", $item["item_name"] ?? "",
                $item["item_type"] ?? "", $item["serial_number"] ?? "",
                (int)($item["quantity"] ?? 0), $item["condition_status"] ?? "",
                $item["status"] ?? "", $item["remarks"] ?? ""
            ];
        }
    }

    if ($includeAssignmentDetails) {
        $assignmentStmt = $conn->prepare("
            SELECT
                a.asset_code,
                a.asset_name,
                h.transaction_date,
                assignee.employee_id,
                assignee.full_name,
                performer.full_name AS assigned_by,
                h.remarks
            FROM asset_history h
            INNER JOIN assets a ON a.id = h.asset_id
            LEFT JOIN users assignee ON assignee.id = h.to_user_id
            INNER JOIN users performer ON performer.id = h.performed_by
            WHERE h.action = 'ASSIGNED'
              AND h.asset_id IN ($inClause)
            ORDER BY h.transaction_date DESC, h.id DESC
        ");
        $assignmentStmt->execute($idParams);
        foreach ($assignmentStmt->fetchAll() as $assignment) {
            $assignmentRows[] = [
                $assignment["asset_code"] ?? "", $assignment["asset_name"] ?? "",
                $assignment["transaction_date"] ?? "", $assignment["employee_id"] ?? "",
                $assignment["full_name"] ?? "", $assignment["assigned_by"] ?? "",
                $assignment["remarks"] ?? ""
            ];
        }
    }
}

$summaryRows = [
    ["ST Asset Management System - Asset History Report"],
    ["Generated", date("Y-m-d H:i:s")],
    ["Search filter", $search !== "" ? $search : "All history records"],
    ["Action filter", $action !== "" ? $action : "All actions"],
    ["From date", $dateFrom !== "" ? $dateFrom : "Any"],
    ["To date", $dateTo !== "" ? $dateTo : "Any"],
    ["History records", count($records)],
    ["Purchase price included", $includePrice ? "Yes" : "No"],
    ["Supplier/purchase details included", $includePurchase ? "Yes" : "No"],
    ["Condition/description included", $includeCondition ? "Yes" : "No"],
    ["Loose tools/linked items included", $includeLinkedItems ? "Yes" : "No"],
    ["Assignment details included", $includeAssignmentDetails ? "Yes" : "No"]
];

if ($includePrice) {
    $purchaseCosts = [];
    foreach ($records as $record) {
        $assetId = (int)($record["asset_id"] ?? 0);
        if (!array_key_exists($assetId, $purchaseCosts)) {
            $purchaseCosts[$assetId] = (float)($record["purchase_cost"] ?? 0);
        }
    }
    $summaryRows[] = ["Total purchase cost of unique assets", array_sum($purchaseCosts)];
}

$tmp = tempnam(sys_get_temp_dir(), "st_history_");
if ($tmp === false) {
    http_response_code(500);
    exit("Unable to create the Excel file.");
}

try {
    $sheets = [
        "Asset History" => [
            "rows" => $rows,
            "meta" => [
                "widths" => $widths,
                "auto_filter" => true,
                "freeze_rows" => 1
            ]
        ],
        "Summary" => [
            "rows" => $summaryRows,
            "meta" => ["widths" => [42, 72], "freeze_rows" => 0]
        ]
    ];

    if ($includeLinkedItems) {
        $sheets["Loose Tools & Items"] = [
            "rows" => $linkedRows,
            "meta" => [
                "widths" => [18, 30, 18, 28, 18, 30, 22, 24, 12, 18, 16, 42],
                "auto_filter" => true,
                "freeze_rows" => 1
            ]
        ];
    }

    if ($includeAssignmentDetails) {
        $sheets["Assignment Details"] = [
            "rows" => $assignmentRows,
            "meta" => [
                "widths" => [18, 30, 22, 18, 28, 28, 42],
                "auto_filter" => true,
                "freeze_rows" => 1
            ]
        ];
    }

    st_xlsx_create($sheets, $tmp);

    $filename = "ST_Asset_History_" . date("Y-m-d_H-i-s") . ".xlsx";
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Length: " . filesize($tmp));
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    readfile($tmp);
} finally {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

exit;
