<?php
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/xlsx_helper.php";
require_once "../../backend/includes/app_config.php";

$role = $_SESSION["role"] ?? "";
$allowed_roles = ["Administrator", "Asset Custodian"];

if (!in_array($role, $allowed_roles, true)) {
    header("Location: /st/index.php");
    exit;
}

/* Purpose: Normalize an Excel header for field matching. */

function st_import_normalize_header(string $header): string
{
    $header = trim(strtolower($header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header);
    return trim($header, '_');
}

/* Purpose: Convert supported Excel date formats to a database date. */

function st_import_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') return null;

    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'm-d-Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $d = DateTime::createFromFormat('!' . $format, $value);
        if ($d && $d->format($format) === $value) return $d->format('Y-m-d');
    }

    if (is_numeric($value)) {
        $excelDate = (int)$value;
        if ($excelDate > 0 && $excelDate < 100000) {
            $base = new DateTime('1899-12-30');
            $base->modify('+' . $excelDate . ' days');
            return $base->format('Y-m-d');
        }
    }

    return null;
}

/* Purpose: Normalize a currency value into a numeric amount. */

function st_import_money(string $value): ?float
{
    $value = trim(str_replace([',', '₱', '$'], '', $value));
    if ($value === '') return null;
    return is_numeric($value) ? round((float)$value, 2) : null;
}

/* Purpose: Protect imported spreadsheet text from formula injection. */

function st_import_safe_text(string $value): string
{
    $value = trim($value);
    if ($value !== '' && preg_match('/^[=+@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

/* Purpose: Read rows from an uploaded CSV/XLSX file. */

function st_import_rows_from_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please select a valid Excel file.');
    }
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('The Excel file must be 10 MB or smaller.');
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    if ($extension === 'csv') {
        $handle = fopen($file['tmp_name'], 'rb');
        if (!$handle) throw new RuntimeException('The CSV file could not be opened.');
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
        fclose($handle);
        return $rows;
    }

    if ($extension !== 'xlsx') {
        throw new RuntimeException('Only .xlsx or .csv files are supported.');
    }

    return st_xlsx_parse($file['tmp_name']);
}

/* Purpose: Map spreadsheet headers to application asset fields. */

function st_import_header_map(array $header): array
{
    $aliases = [
        'asset_code' => ['asset_code', 'asset_code_number', 'asset_no', 'property_number'],
        'asset_name' => ['asset_name', 'asset', 'item_name', 'description_of_asset'],
        'category' => ['category', 'category_name'],
        'asset_type' => ['asset_type', 'type'],
        'serial_number' => ['serial_number', 'serial', 'plate_number', 'plate_no', 'serial_plate_number'],
        'asset_source' => ['asset_source', 'source'],
        'condition_status' => ['condition_status', 'condition'],
        'legacy_reference' => ['legacy_reference', 'legacy_ref', 'old_reference', 'old_asset_number'],
        'date_added' => ['date_added', 'date_entered', 'date_added_to_system'],
        'supplier_name' => ['supplier_name', 'supplier', 'vendor'],
        'purchase_number' => ['purchase_number', 'purchase_no', 'po_number', 'po_no'],
        'purchase_date' => ['purchase_date', 'date_purchased'],
        'purchase_cost' => ['purchase_cost', 'cost', 'acquisition_cost'],
        'status' => ['status', 'asset_status'],
        'employee_id' => ['employee_id', 'current_employee_id', 'assigned_employee_id', 'employee_number'],
        'employee_name' => ['employee_name', 'current_employee_name', 'assigned_employee_name', 'employee'],
        'assignment_remarks' => ['assignment_remarks', 'assignment_notes', 'accountability_remarks'],
        'description' => ['description', 'remarks', 'notes'],
    ];

    $map = [];
    foreach ($header as $index => $name) {
        $normalized = st_import_normalize_header((string)$name);
        foreach ($aliases as $field => $names) {
            if (in_array($normalized, $names, true)) {
                $map[$field] = $index;
                break;
            }
        }
    }
    return $map;
}

/* Purpose: Read and sanitize one mapped spreadsheet value. */

function st_import_value(array $row, array $map, string $field): string
{
    if (!isset($map[$field])) return '';
    return st_import_safe_text((string)($row[$map[$field]] ?? ''));
}

/* Purpose: Build current asset rows for Excel export. */

function st_export_rows(PDO $conn): array
{
    // IMPORTANT: Keep the exported Asset Import sheet in the exact same
    // column order as the import format. This makes the exported workbook
    // directly editable and re-importable without manual column rearranging.
    $sql = "
        SELECT
            a.id,
            a.asset_code,
            a.asset_name,
            c.name AS category,
            a.asset_type,
            a.serial_number,
            a.asset_source,
            a.condition_status,
            a.legacy_reference,
            a.date_added,
            a.supplier_name,
            a.purchase_number,
            a.purchase_date,
            a.purchase_cost,
            a.status,
            u.employee_id AS current_employee_id,
            u.full_name AS current_employee_name,
            a.description,
            h.remarks AS assignment_remarks
        FROM assets a
        INNER JOIN categories c ON c.id = a.category_id
        LEFT JOIN users u ON u.id = a.current_user_id
        LEFT JOIN asset_history h ON h.id = (
            SELECT ah.id
            FROM asset_history ah
            WHERE ah.asset_id = a.id
              AND ah.action IN ('ASSIGNED', 'TRANSFERRED')
              AND ah.to_user_id = a.current_user_id
            ORDER BY ah.transaction_date DESC, ah.id DESC
            LIMIT 1
        )
        ORDER BY a.id ASC
    ";

    $stmt = $conn->query($sql);

    $rows = [[
        'Asset Code*', 'Asset Name*', 'Category*', 'Asset Type*', 'Serial / Plate Number',
        'Asset Source', 'Condition', 'Legacy Reference', 'Date Added', 'Supplier Name',
        'Purchase Number', 'Purchase Date', 'Purchase Cost', 'Status',
        'Current Employee ID', 'Current Employee Name', 'Description', 'Assignment Remarks'
    ]];

    foreach ($stmt->fetchAll() as $a) {
        $rows[] = [
            $a['asset_code'],
            $a['asset_name'],
            $a['category'],
            $a['asset_type'],
            $a['serial_number'] ?? '',
            $a['asset_source'] ?? 'Legacy / Existing',
            $a['condition_status'] ?? 'Good',
            $a['legacy_reference'] ?? '',
            $a['date_added'] ?? '',
            $a['supplier_name'] ?? '',
            $a['purchase_number'] ?? '',
            $a['purchase_date'] ?? '',
            $a['purchase_cost'] !== null ? (float)$a['purchase_cost'] : '',
            $a['status'] ?? 'Available',
            $a['current_employee_id'] ?? '',
            $a['current_employee_name'] ?? '',
            $a['description'] ?? '',
            $a['assignment_remarks'] ?? ''
        ];
    }

    return $rows;
}

/* Purpose: Build current user rows for Excel export. */

function st_user_reference_rows(PDO $conn): array
{
    $rows = [['Employee ID', 'Full Name', 'Status', 'Role', 'Position', 'Department']];
    $stmt = $conn->query("SELECT employee_id, full_name, department, position, role, status FROM users WHERE employee_id IS NOT NULL AND employee_id <> '' ORDER BY full_name ASC");
    foreach ($stmt->fetchAll() as $u) {
        $rows[] = [
            $u['employee_id'],
            $u['full_name'],
            $u['status'],
            $u['role'],
            $u['position'],
            $u['department']
        ];
    }
    return $rows;
}

/* Purpose: Build linked-item rows for Excel export. */

function st_linked_item_rows(PDO $conn): array
{
    $rows = [[
        'Asset Code*', 'Item Code', 'Item Name*', 'Item Type', 'Serial Number',
        'Quantity', 'Condition', 'Status', 'Remarks'
    ]];

    $stmt = $conn->query("\n        SELECT\n            a.asset_code,\n            li.item_code,\n            li.item_name,\n            li.item_type,\n            li.serial_number,\n            li.quantity,\n            li.condition_status,\n            li.status,\n            li.remarks\n        FROM asset_linked_items li\n        INNER JOIN assets a ON a.id = li.asset_id\n        ORDER BY a.asset_code ASC, li.id ASC\n    ");

    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            $row['asset_code'],
            $row['item_code'] ?? '',
            $row['item_name'],
            $row['item_type'],
            $row['serial_number'] ?? '',
            (int)$row['quantity'],
            $row['condition_status'],
            $row['status'],
            $row['remarks'] ?? ''
        ];
    }

    return $rows;
}

/* Purpose: Build the current dropdown/reference values for Excel. */

function st_reference_list_rows(PDO $conn): array
{
    $categories = $conn->query("SELECT name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $rows = [
        ['Categories', 'Asset Source', 'Condition', 'Status', 'Linked Item Type', 'Linked Item Status', 'User Role', 'User Status'],
    ];
    $max = max(
        count($categories),
        5, 5, 4, 6, 2, 4, 2
    );
    $sources = ['New Purchase','Legacy / Existing','Donated','Transferred','Other'];
    $conditions = ['New','Good','Fair','Poor','Damaged'];
    $statuses = ['Available','Assigned','Maintenance','Retired'];
    $itemTypes = ['Loose Tool','Accessory','Component','Safety Equipment','Included Item','Other'];
    $itemStatuses = ['With Asset','Removed'];
    $roles = ['Administrator','Asset Custodian','HR / Clearance Officer','Viewer'];
    $userStatuses = ['Active','Inactive'];
    for ($i = 0; $i < $max; $i++) {
        $rows[] = [
            $categories[$i] ?? '',
            $sources[$i] ?? '',
            $conditions[$i] ?? '',
            $statuses[$i] ?? '',
            $itemTypes[$i] ?? '',
            $itemStatuses[$i] ?? '',
            $roles[$i] ?? '',
            $userStatuses[$i] ?? '',
        ];
    }
    return $rows;
}

/* Purpose: Return Excel metadata for the Assets sheet. */

function st_asset_sheet_meta(PDO $conn): array
{
    $categories = $conn->query("SELECT name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $catEnd = max(2, count($categories) + 1);
    return [
        'required_columns' => [0,1,2,3],
        'header_styles' => [2,2,2,2,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
        'widths' => [20,30,24,24,24,22,18,24,18,24,20,18,18,18,24,30,42,42],
        'date_columns' => [8,11],
        'auto_filter' => true,
        'freeze_rows' => 1,
        'validations' => [
            ['type'=>'list','allow_blank'=>true,'sqref'=>'C2:C1000','formula1'=>"'Reference Lists'!\$A\$2:\$A\$".$catEnd],
            ['type'=>'list','allow_blank'=>true,'sqref'=>'F2:F1000','formula1'=>"'Reference Lists'!\$B\$2:\$B\$6"],
            ['type'=>'list','allow_blank'=>true,'sqref'=>'G2:G1000','formula1'=>"'Reference Lists'!\$C\$2:\$C\$6"],
            ['type'=>'list','allow_blank'=>true,'sqref'=>'N2:N1000','formula1'=>"'Reference Lists'!\$D\$2:\$D\$5"],
        ],
    ];
}

/* Purpose: Return Excel metadata for the Users Reference sheet. */

function st_user_sheet_meta(): array
{
    return [
        'required_columns' => [0,1],
        'header_styles' => [2,2,1,1,1,1],
        'widths' => [22,32,16,28,30,30],
        'auto_filter' => true,
        'freeze_rows' => 1,
        'validations' => [
            ['type'=>'list','allow_blank'=>true,'sqref'=>'C2:C1000','formula1'=>"'Reference Lists'!\$H\$2:\$H\$3"],
            ['type'=>'list','allow_blank'=>true,'sqref'=>'D2:D1000','formula1'=>"'Reference Lists'!\$G\$2:\$G\$5"],
        ],
    ];
}

/* Purpose: Return Excel metadata for the Linked Items sheet. */

function st_linked_sheet_meta(): array
{
    return [
        'required_columns' => [0,2],
        'header_styles' => [2,1,2,1,1,1,1,1,1],
        'widths' => [22,20,32,22,24,12,18,18,40],
        'auto_filter' => true,
        'freeze_rows' => 1,
        'validations' => [
            ['type'=>'list','allow_blank'=>true,'sqref'=>'D2:D1000','formula1'=>"'Reference Lists'!\$E\$2:\$E\$7"],
            ['type'=>'list','allow_blank'=>true,'sqref'=>'G2:G1000','formula1'=>"'Reference Lists'!\$C\$2:\$C\$6"],
            ['type'=>'list','allow_blank'=>true,'sqref'=>'H2:H1000','formula1'=>"'Reference Lists'!\$F\$2:\$F\$3"],
        ],
    ];
}

/* Purpose: Return Excel metadata for the Reference Lists sheet. */

function st_reference_sheet_meta(): array
{
    return [
        'header_styles' => [1,1,1,1,1,1,1,1],
        'widths' => [28,22,18,18,24,22,28,18],
        'freeze_rows' => 1,
        'auto_filter' => true,
    ];
}

/* Purpose: Build the blank Excel template rows. */

function st_template_rows(PDO $conn): array
{
    $rows = [
        [
            'Asset Code*', 'Asset Name*', 'Category*', 'Asset Type*', 'Serial / Plate Number',
            'Asset Source', 'Condition', 'Legacy Reference', 'Date Added', 'Supplier Name',
            'Purchase Number', 'Purchase Date', 'Purchase Cost', 'Status',
            'Current Employee ID', 'Current Employee Name', 'Description', 'Assignment Remarks'
        ],
        [
            '', '', '', '', '',
            'Legacy / Existing', 'Good', '', date('Y-m-d'), '',
            '', '', '', 'Available', '', '', '', ''
        ]
    ];

    $instructions = [
        ['ST Asset Management - Complete Asset Import / Update Template'],
        ['RED/PINK CELLS = REQUIRED. Asset Code, Asset Name, Category and Asset Type must be supplied for every asset row.'],
        ['GREEN/WHITE CELLS = OPTIONAL. Blank optional cells preserve the existing ST value when the Asset Code already exists.'],
        ['Category must exactly match a category already configured in ST. Use the Reference Lists sheet for valid values.'],
        ['Asset Source: New Purchase, Legacy / Existing, Donated, Transferred, Other.'],
        ['Condition: New, Good, Fair, Poor, Damaged. Status: Available, Assigned, Maintenance, Retired.'],
        ['For an Assigned asset, provide either Current Employee ID OR Current Employee Name. You do not need to fill in both.'],
        ['If both employee fields are supplied, they must identify the same active user. ST records assignment/transfer history when accountability changes.'],
        ['For existing assets, blank optional cells do not erase existing values. To clear a value, use the ST application directly.'],
        ['New Asset Code creates a new asset. Existing Asset Code updates the matching asset; no duplicate is created.'],
        ['QR tokens are generated and maintained by ST. Do not enter QR URLs or tokens in the import workbook.'],
        ['Linked Items sheet supports Loose Tools, Accessories, Components, Safety Equipment and other items attached to a main asset.'],
        ['Linked Items require Asset Code and Item Name. Item Code is optional; if supplied, it becomes the update key.'],
        ['Users Reference can update Full Name, Status, Role, Position and Department for matching Employee IDs.'],
        ['For a new user, Employee ID and Full Name are required. If Role is blank, ST uses Viewer. Passwords are never imported from Excel.'],
        ['Existing users are updated; users missing from the workbook are not deleted.'],
        ['Do not delete rows from the workbook to delete records from ST. Import is additive/update-only and does not delete absent database records.'],
        ['Dates: YYYY-MM-DD. Purchase Cost: numeric amount without currency symbols where possible.'],
        ['Use the dropdowns in the Asset Import and Linked Items sheets where available.'],
    ];

    return [$rows, $instructions];
}

if (isset($_GET['download'])) {
    $download = $_GET['download'];
    $tmp = tempnam(sys_get_temp_dir(), 'st_xlsx_');

    try {
        if ($download === 'export') {
            // Export is intentionally a populated WORKING workbook.
            // It uses the same Asset Import column structure so the user can:
            //   1) edit existing rows,
            //   2) append new asset rows, and
            //   3) import the same workbook back into ST.
            [$assetRows, $instructions] = st_template_rows($conn);
            $assetRows = st_export_rows($conn);

            $instructions = [
                ['ST Asset Management - Current Data Working Workbook'],
                ['This workbook was exported from the current ST database. Existing asset rows are safe to edit and re-import.'],
                ['To UPDATE an asset: keep its existing Asset Code and change the fields you need.'],
                ['To ADD an asset: append a new row with a new Asset Code, Asset Name, Category and Asset Type.'],
                ['Asset Code is the update key. Existing code = UPDATE; new code = CREATE.'],
                ['Rows missing from this workbook are NOT deleted from ST. Import is additive/update-only.'],
                ['Blank optional cells preserve the existing database value when the Asset Code already exists.'],
                ['For Assigned assets, use either Current Employee ID or Current Employee Name. If both are supplied, they must match.'],
                ['Do not edit QR tokens or QR URLs. QR identification is maintained automatically by ST.'],
                ['Users Reference, Linked Items, and Reference Lists are included as current supporting data.'],
                ['Passwords are never exported or imported.'],
                ['After editing, save the workbook as .xlsx and use ST → Import / Export → Import Assets.'],
            ];

            st_xlsx_create([
                'Asset Import' => ['rows' => $assetRows, 'meta' => st_asset_sheet_meta($conn)],
                'Instructions' => ['rows' => $instructions, 'meta' => ['header_styles' => [7], 'widths' => [120], 'freeze_rows' => 1]],
                'Users Reference' => ['rows' => st_user_reference_rows($conn), 'meta' => st_user_sheet_meta()],
                'Linked Items' => ['rows' => st_linked_item_rows($conn), 'meta' => st_linked_sheet_meta()],
                'Reference Lists' => ['rows' => st_reference_list_rows($conn), 'meta' => st_reference_sheet_meta()]
            ], $tmp);
            $filename = 'ST_Asset_Inventory_Working_' . date('Y-m-d_His') . '.xlsx';
        } elseif ($download === 'template') {
            [$assetRows, $instructions] = st_template_rows($conn);
            st_xlsx_create([
                'Asset Import' => ['rows' => $assetRows, 'meta' => st_asset_sheet_meta($conn)],
                'Instructions' => ['rows' => $instructions, 'meta' => ['header_styles' => [7], 'widths' => [120], 'freeze_rows' => 1]],
                'Users Reference' => ['rows' => st_user_reference_rows($conn), 'meta' => st_user_sheet_meta()],
                'Linked Items' => ['rows' => [['Asset Code*','Item Code','Item Name*','Item Type','Serial Number','Quantity','Condition','Status','Remarks'],['','','','','',1,'Good','With Asset','']], 'meta' => st_linked_sheet_meta()],
                'Reference Lists' => ['rows' => st_reference_list_rows($conn), 'meta' => st_reference_sheet_meta()]
            ], $tmp);
            $filename = 'ST_Legacy_Asset_Import_Template.xlsx';
        } else {
            throw new RuntimeException('Unknown download request.');
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($tmp);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Excel file error: ' . htmlspecialchars($e->getMessage());
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
    exit;
}

$successCount = 0;
$addedCount = 0;
$updatedCount = 0;
$assignedCount = 0;
$availableCount = 0;
$maintenanceCount = 0;
$retiredCount = 0;
$userUpdatedCount = 0;
$userAddedCount = 0;
$userRoleUpdatedCount = 0;
$userMissingCount = 0;
$linkedAddedCount = 0;
$linkedUpdatedCount = 0;
$errors = [];
$uploadedName = '';

/* Purpose: Find the employee account used for asset assignment. */

function st_find_user_for_assignment(PDO $conn, string $employeeId, string $employeeName): ?array
{
    $employeeId = trim($employeeId);
    $employeeName = trim($employeeName);

    if ($employeeId === '' && $employeeName === '') {
        return null;
    }

    if ($employeeId !== '') {
        $stmt = $conn->prepare("SELECT id, employee_id, full_name, department, position, status FROM users WHERE employee_id = :employee_id LIMIT 1");
        $stmt->execute([':employee_id' => $employeeId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('Employee ID not found in Users: ' . $employeeId . '.');
        }
    } else {
        $stmt = $conn->prepare("SELECT id, employee_id, full_name, department, position, status FROM users WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(:full_name)) ORDER BY id ASC");
        $stmt->execute([':full_name' => $employeeName]);
        $matches = $stmt->fetchAll();
        if (count($matches) === 0) {
            throw new RuntimeException('Employee Name not found in Users: ' . $employeeName . '.');
        }
        if (count($matches) > 1) {
            throw new RuntimeException('Employee Name is not unique in Users: ' . $employeeName . '. Please provide Current Employee ID.');
        }
        $user = $matches[0];
    }

    if (($user['status'] ?? '') !== 'Active') {
        throw new RuntimeException('Employee is inactive: ' . ($user['employee_id'] ?: $user['full_name']) . '.');
    }

    if ($employeeName !== '' && mb_strtolower(trim($employeeName)) !== mb_strtolower(trim($user['full_name']))) {
        throw new RuntimeException('Current Employee Name does not match the Users record for ' . ($user['employee_id'] ?: $employeeName) . '.');
    }

    return $user;
}

/* Purpose: Synchronize the Excel Users Reference sheet with ST users. */

function st_sync_users_reference(PDO $conn, array $rows, int $currentUserId, array &$errors, int &$userAddedCount, int &$userRoleUpdatedCount): int
{
    if (count($rows) < 2) return 0;

    $header = array_map('strval', $rows[0]);
    $aliases = [
        'employee_id' => ['employee_id', 'employee_number'],
        'full_name' => ['full_name', 'employee_name', 'name'],
        'status' => ['status', 'user_status'],
        'role' => ['role', 'user_role', 'access_role'],
        'position' => ['position', 'job_title'],
        'department' => ['department', 'dept'],
    ];
    $fieldMap = [];
    foreach ($header as $index => $name) {
        $normalized = st_import_normalize_header((string)$name);
        foreach ($aliases as $field => $names) {
            if (in_array($normalized, $names, true)) {
                $fieldMap[$field] = $index;
                break;
            }
        }
    }

    if (!isset($fieldMap['employee_id'])) return 0;

    $allowedRoles = ['Administrator', 'Asset Custodian', 'HR / Clearance Officer', 'Viewer'];
    $allowedStatuses = ['Active', 'Inactive'];

    $find = $conn->prepare("SELECT id, employee_id, full_name, department, position, role, status FROM users WHERE employee_id = :employee_id LIMIT 1");
    $update = $conn->prepare("UPDATE users SET full_name = :full_name, department = :department, position = :position, role = :role, status = :status WHERE id = :id");
    $checkUsername = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $insert = $conn->prepare("INSERT INTO users (employee_id, username, password, full_name, department, position, role, status) VALUES (:employee_id, :username, :password, :full_name, :department, :position, :role, :status)");

    $updated = 0;

    foreach (array_slice($rows, 1) as $index => $row) {
        $excelRow = $index + 2;
        $employeeId = trim((string)($row[$fieldMap['employee_id']] ?? ''));
        if ($employeeId === '') continue;

        $fullName = isset($fieldMap['full_name']) ? trim((string)($row[$fieldMap['full_name']] ?? '')) : '';
        $department = isset($fieldMap['department']) ? trim((string)($row[$fieldMap['department']] ?? '')) : '';
        $position = isset($fieldMap['position']) ? trim((string)($row[$fieldMap['position']] ?? '')) : '';
        $status = isset($fieldMap['status']) ? trim((string)($row[$fieldMap['status']] ?? '')) : '';
        $role = isset($fieldMap['role']) ? trim((string)($row[$fieldMap['role']] ?? '')) : '';

        try {
            $find->execute([':employee_id' => $employeeId]);
            $user = $find->fetch();

            if ($user) {
                $newName = $fullName !== '' ? $fullName : $user['full_name'];
                $newDepartment = $department !== '' ? $department : $user['department'];
                $newPosition = $position !== '' ? $position : $user['position'];
                $newStatus = $status !== '' ? $status : $user['status'];
                $newRole = $role !== '' ? $role : $user['role'];

                if (!in_array($newStatus, $allowedStatuses, true)) {
                    throw new RuntimeException('Invalid user status: ' . $newStatus);
                }
                if (!in_array($newRole, $allowedRoles, true)) {
                    throw new RuntimeException('Invalid user role: ' . $newRole);
                }

                if ((int)$user['id'] === $currentUserId && $newStatus === 'Inactive') {
                    $errors[] = 'Users Reference row ' . $excelRow . ': Your current Administrator account was not deactivated.';
                    $newStatus = $user['status'];
                }

                if ((int)$user['id'] === $currentUserId && $newRole !== 'Administrator') {
                    $errors[] = 'Users Reference row ' . $excelRow . ': Your current Administrator role was not changed.';
                    $newRole = $user['role'];
                }

                if ($newName !== $user['full_name'] || $newDepartment !== $user['department'] || $newPosition !== $user['position'] || $newStatus !== $user['status'] || $newRole !== $user['role']) {
                    $update->execute([
                        ':full_name' => $newName,
                        ':department' => $newDepartment,
                        ':position' => $newPosition,
                        ':role' => $newRole,
                        ':status' => $newStatus,
                        ':id' => (int)$user['id'],
                    ]);
                    $updated++;
                    if ($newRole !== $user['role']) $userRoleUpdatedCount++;
                }
                continue;
            }

            if ($fullName === '') {
                throw new RuntimeException('New Employee ID requires Full Name in Users Reference.');
            }

            $newStatus = $status !== '' ? $status : 'Active';
            $newRole = $role !== '' ? $role : 'Viewer';
            if (!in_array($newStatus, $allowedStatuses, true)) {
                throw new RuntimeException('Invalid user status: ' . $newStatus);
            }
            if (!in_array($newRole, $allowedRoles, true)) {
                throw new RuntimeException('Invalid user role: ' . $newRole);
            }

            $baseUsername = 'emp_' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $employeeId));
            $baseUsername = trim($baseUsername, '_');
            if ($baseUsername === 'emp_') $baseUsername = 'employee';
            $username = $baseUsername;
            $suffix = 1;
            while (true) {
                $checkUsername->execute([':username' => $username]);
                if (!$checkUsername->fetch()) break;
                $suffix++;
                $username = $baseUsername . '_' . $suffix;
            }

            // The random password is intentionally never displayed or written to Excel.
            // Administrator can set a real login password later from Users > Edit User.
            $randomPassword = bin2hex(random_bytes(32));
            $insert->execute([
                ':employee_id' => $employeeId,
                ':username' => $username,
                ':password' => password_hash($randomPassword, PASSWORD_DEFAULT),
                ':full_name' => $fullName,
                ':department' => $department !== '' ? $department : null,
                ':position' => $position !== '' ? $position : null,
                ':role' => $newRole,
                ':status' => $newStatus,
            ]);
            $userAddedCount++;
        } catch (Throwable $e) {
            $errors[] = 'Users Reference row ' . $excelRow . ': ' . $e->getMessage();
        }
    }

    return $updated;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $uploadedName = $_FILES['asset_file']['name'] ?? '';

    try {
        $rows = st_import_rows_from_upload($_FILES['asset_file'] ?? []);
        if (count($rows) < 1) throw new RuntimeException('The uploaded file is empty.');

        $extension = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));
        $userRows = [];
        $linkedRows = [];
        if ($extension === 'xlsx') {
            /*
             * Do not depend on worksheet numbers. Older ST templates and
             * workbooks edited in Excel may have the supporting sheets in a
             * different order. Identify the sheets by their header fields.
             */
            $xlsxSheets = [];
            for ($sheetNo = 1; $sheetNo <= 20; $sheetNo++) {
                try {
                    $sheetRows = st_xlsx_parse($_FILES['asset_file']['tmp_name'], $sheetNo);
                } catch (Throwable $sheetError) {
                    break;
                }
                if (!$sheetRows) continue;

                $header = array_map('strval', $sheetRows[0] ?? []);
                $normalizedHeader = [];
                foreach ($header as $headerCell) {
                    $normalizedHeader[] = st_import_normalize_header($headerCell);
                }

                $hasEmployeeId = in_array('employee_id', $normalizedHeader, true) || in_array('employee_number', $normalizedHeader, true);
                $hasFullName = in_array('full_name', $normalizedHeader, true) || in_array('employee_name', $normalizedHeader, true);
                $hasItemName = in_array('item_name', $normalizedHeader, true) || in_array('linked_item_name', $normalizedHeader, true);
                $hasAssetCode = in_array('asset_code', $normalizedHeader, true) || in_array('parent_asset_code', $normalizedHeader, true);

                if ($hasEmployeeId && $hasFullName) {
                    $userRows = $sheetRows;
                } elseif ($hasAssetCode && $hasItemName) {
                    $linkedRows = $sheetRows;
                }
            }
        }

        $performedBy = (int)($_SESSION['user_id'] ?? 0);
        if ($performedBy <= 0) throw new RuntimeException('Your login session is missing a valid user ID. Please log in again.');

        if ($userRows) {
            $userUpdatedCount = st_sync_users_reference($conn, $userRows, $performedBy, $errors, $userAddedCount, $userRoleUpdatedCount);
        }

        $header = array_map('strval', $rows[0]);
        $map = st_import_header_map($header);

        foreach (['asset_code', 'asset_name', 'category', 'asset_type'] as $required) {
            if (!isset($map[$required])) {
                throw new RuntimeException('Missing required column: ' . ucwords(str_replace('_', ' ', $required)) . '. Please use the ST import template.');
            }
        }

        $categories = [];
        foreach ($conn->query("SELECT id, name FROM categories") as $cat) {
            $categories[mb_strtolower(trim($cat['name']))] = (int)$cat['id'];
        }

        $allowedSources = ['New Purchase', 'Legacy / Existing', 'Donated', 'Transferred', 'Other'];
        $allowedConditions = ['New', 'Good', 'Fair', 'Poor', 'Damaged'];
        $allowedStatuses = ['Available', 'Assigned', 'Maintenance', 'Retired'];

        $findAsset = $conn->prepare("SELECT * FROM assets WHERE asset_code = :asset_code LIMIT 1");
        $checkSerial = $conn->prepare("SELECT id FROM assets WHERE serial_number = :value AND id != :id LIMIT 1");
        $historyInsert = $conn->prepare("INSERT INTO asset_history (asset_id, action, from_user_id, to_user_id, performed_by, transaction_date, condition_status, remarks) VALUES (:asset_id, :action, :from_user_id, :to_user_id, :performed_by, :transaction_date, :condition_status, :remarks)");

        foreach (array_slice($rows, 1) as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $allBlank = true;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') { $allBlank = false; break; }
            }
            if ($allBlank) continue;

            try {
                $assetCode = st_import_value($row, $map, 'asset_code');
                $assetName = st_import_value($row, $map, 'asset_name');
                $categoryName = st_import_value($row, $map, 'category');
                $assetType = st_import_value($row, $map, 'asset_type');

                if ($assetCode === '' || $assetName === '' || $categoryName === '' || $assetType === '') {
                    throw new RuntimeException('Required field is blank.');
                }

                $categoryId = $categories[mb_strtolower($categoryName)] ?? null;
                if (!$categoryId) throw new RuntimeException('Category not found: ' . $categoryName);

                $findAsset->execute([':asset_code' => $assetCode]);
                $existing = $findAsset->fetch();

                $serial = st_import_value($row, $map, 'serial_number');
                $source = st_import_value($row, $map, 'asset_source');
                $condition = st_import_value($row, $map, 'condition_status');
                $statusInput = st_import_value($row, $map, 'status');
                $employeeId = st_import_value($row, $map, 'employee_id');
                $employeeName = st_import_value($row, $map, 'employee_name');

                if ($existing) {
                    $status = $statusInput !== '' ? $statusInput : $existing['status'];
                    $source = $source !== '' ? $source : $existing['asset_source'];
                    $condition = $condition !== '' ? $condition : $existing['condition_status'];
                    $dateAdded = st_import_date(st_import_value($row, $map, 'date_added')) ?: ($existing['date_added'] ?: date('Y-m-d'));
                    $currentUserId = $existing['current_user_id'] !== null ? (int)$existing['current_user_id'] : null;
                } else {
                    $status = $statusInput !== '' ? $statusInput : 'Available';
                    $source = $source !== '' ? $source : 'Legacy / Existing';
                    $condition = $condition !== '' ? $condition : 'Good';
                    $dateAdded = st_import_date(st_import_value($row, $map, 'date_added')) ?: date('Y-m-d');
                    $currentUserId = null;
                }

                if (!in_array($source, $allowedSources, true)) throw new RuntimeException('Invalid Asset Source.');
                if (!in_array($condition, $allowedConditions, true)) throw new RuntimeException('Invalid Condition.');
                if (!in_array($status, $allowedStatuses, true)) throw new RuntimeException('Invalid Status.');

                $assignedUser = null;
                if ($employeeId !== '' || $employeeName !== '') {
                    $assignedUser = st_find_user_for_assignment($conn, $employeeId, $employeeName);
                    $currentUserId = (int)$assignedUser['id'];
                    $status = 'Assigned';
                } elseif ($statusInput === 'Assigned') {
                    if ($existing && $existing['current_user_id'] !== null) {
                        $currentUserId = (int)$existing['current_user_id'];
                    } else {
                        throw new RuntimeException('Status is Assigned but neither Current Employee ID nor Current Employee Name was supplied.');
                    }
                } elseif ($statusInput !== '' && $statusInput !== 'Assigned') {
                    $currentUserId = null;
                }

                $purchaseDateRaw = st_import_value($row, $map, 'purchase_date');
                $purchaseDate = $purchaseDateRaw === '' ? ($existing['purchase_date'] ?? null) : st_import_date($purchaseDateRaw);
                if ($purchaseDateRaw !== '' && $purchaseDate === null) throw new RuntimeException('Invalid Purchase Date.');
                $purchaseCostRaw = st_import_value($row, $map, 'purchase_cost');
                $purchaseCost = $purchaseCostRaw === '' ? ($existing['purchase_cost'] ?? null) : st_import_money($purchaseCostRaw);
                if ($purchaseCostRaw !== '' && $purchaseCost === null) throw new RuntimeException('Invalid Purchase Cost.');

                $legacyReference = st_import_value($row, $map, 'legacy_reference');
                $supplierName = st_import_value($row, $map, 'supplier_name');
                $purchaseNumber = st_import_value($row, $map, 'purchase_number');
                $description = st_import_value($row, $map, 'description');
                $assignmentRemarks = st_import_value($row, $map, 'assignment_remarks');

                if ($existing) {
                    $serialToSave = $serial !== '' ? $serial : $existing['serial_number'];
                    if ($serialToSave !== null && $serialToSave !== '') {
                        $checkSerial->execute([':value' => $serialToSave, ':id' => (int)$existing['id']]);
                        if ($checkSerial->fetch()) throw new RuntimeException('Serial / Plate Number belongs to another asset.');
                    } else {
                        $serialToSave = null;
                    }

                    $sets = [
                        'asset_name = :asset_name',
                        'category_id = :category_id',
                        'asset_type = :asset_type',
                        'serial_number = :serial_number',
                        'asset_source = :asset_source',
                        'condition_status = :condition_status',
                        'legacy_reference = :legacy_reference',
                        'date_added = :date_added',
                        'supplier_name = :supplier_name',
                        'purchase_number = :purchase_number',
                        'purchase_date = :purchase_date',
                        'purchase_cost = :purchase_cost',
                        'status = :status',
                        'current_user_id = :current_user_id',
                    ];
                    $params = [
                        ':asset_name' => $assetName,
                        ':category_id' => $categoryId,
                        ':asset_type' => $assetType,
                        ':serial_number' => $serialToSave,
                        ':asset_source' => $source,
                        ':condition_status' => $condition,
                        ':legacy_reference' => $legacyReference !== '' ? $legacyReference : $existing['legacy_reference'],
                        ':date_added' => $dateAdded,
                        ':supplier_name' => $supplierName !== '' ? $supplierName : $existing['supplier_name'],
                        ':purchase_number' => $purchaseNumber !== '' ? $purchaseNumber : $existing['purchase_number'],
                        ':purchase_date' => $purchaseDate,
                        ':purchase_cost' => $purchaseCost,
                        ':status' => $status,
                        ':current_user_id' => $currentUserId,
                        ':id' => (int)$existing['id'],
                    ];

                    if ($description !== '') { $sets[] = 'description = :description'; $params[':description'] = $description; }

                    $update = $conn->prepare('UPDATE assets SET ' . implode(', ', $sets) . ' WHERE id = :id');
                    $conn->beginTransaction();
                    try {
                        $lockAsset = $conn->prepare('SELECT id FROM assets WHERE id = :id FOR UPDATE');
                        $lockAsset->execute([':id' => (int)$existing['id']]);
                        if (!$lockAsset->fetch()) throw new RuntimeException('Asset no longer exists.');

                        $activeMaintenance = $conn->prepare("SELECT id FROM maintenance_records WHERE asset_id = :asset_id AND status IN ('Open', 'In Progress', 'Waiting for Parts') LIMIT 1");
                        $activeMaintenance->execute([':asset_id' => (int)$existing['id']]);
                        if ($activeMaintenance->fetch() && ($status !== 'Maintenance' || $currentUserId !== null)) {
                            throw new RuntimeException('Asset has an active maintenance work order; close it before changing its status or assignment.');
                        }

                        $update->execute($params);

                        $oldUserId = $existing['current_user_id'] !== null ? (int)$existing['current_user_id'] : null;
                        if ($status === 'Assigned' && $currentUserId !== null && $oldUserId !== $currentUserId) {
                            $action = $oldUserId !== null ? 'TRANSFERRED' : 'ASSIGNED';
                            $remarks = $assignmentRemarks !== '' ? $assignmentRemarks : 'Legacy asset assignment updated from Excel import.';
                            $historyInsert->execute([
                                ':asset_id' => (int)$existing['id'],
                                ':action' => $action,
                                ':from_user_id' => $oldUserId,
                                ':to_user_id' => $currentUserId,
                                ':performed_by' => $performedBy,
                                ':transaction_date' => date('Y-m-d H:i:s'),
                                ':condition_status' => $condition,
                                ':remarks' => $remarks,
                            ]);
                        } elseif ($status !== 'Assigned' && $oldUserId !== null && $currentUserId === null) {
                            $remarks = $assignmentRemarks !== '' ? $assignmentRemarks : 'Legacy asset assignment cleared from Excel import.';
                            $historyInsert->execute([
                                ':asset_id' => (int)$existing['id'],
                                ':action' => 'RETURNED',
                                ':from_user_id' => $oldUserId,
                                ':to_user_id' => null,
                                ':performed_by' => $performedBy,
                                ':transaction_date' => date('Y-m-d H:i:s'),
                                ':condition_status' => $condition,
                                ':remarks' => $remarks,
                            ]);
                        } elseif ($status === 'Assigned' && $currentUserId !== null && $assignmentRemarks !== '' && $oldUserId === $currentUserId) {
                            // Keep the current assignment unchanged; no duplicate history is created.
                        }
                        $conn->commit();
                    } catch (Throwable $e) {
                        if ($conn->inTransaction()) $conn->rollBack();
                        throw $e;
                    }
                    $successCount++; $updatedCount++;
                } else {
                    if ($serial !== '') {
                        $checkSerial->execute([':value' => $serial, ':id' => 0]);
                        if ($checkSerial->fetch()) throw new RuntimeException('Serial / Plate Number already belongs to another asset.');
                    }

                    $qrToken = bin2hex(random_bytes(24));
                    $insert = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category_id, asset_type, serial_number, asset_source, condition_status, legacy_reference, date_added, supplier_name, purchase_number, purchase_date, purchase_cost, status, current_user_id, description, qr_token, qr_enabled) VALUES (:asset_code, :asset_name, :category_id, :asset_type, :serial_number, :asset_source, :condition_status, :legacy_reference, :date_added, :supplier_name, :purchase_number, :purchase_date, :purchase_cost, :status, :current_user_id, :description, :qr_token, 1)");
                    $conn->beginTransaction();
                    try {
                        $insert->execute([
                            ':asset_code' => $assetCode,
                            ':asset_name' => $assetName,
                            ':category_id' => $categoryId,
                            ':asset_type' => $assetType,
                            ':serial_number' => $serial !== '' ? $serial : null,
                            ':asset_source' => $source,
                            ':condition_status' => $condition,
                            ':legacy_reference' => $legacyReference !== '' ? $legacyReference : null,
                            ':date_added' => $dateAdded,
                            ':supplier_name' => $supplierName !== '' ? $supplierName : null,
                            ':purchase_number' => $purchaseNumber !== '' ? $purchaseNumber : null,
                            ':purchase_date' => $purchaseDate,
                            ':purchase_cost' => $purchaseCost,
                            ':status' => $status,
                            ':current_user_id' => $currentUserId,
                            ':description' => $description !== '' ? $description : null,
                            ':qr_token' => $qrToken,
                        ]);
                        $assetId = (int)$conn->lastInsertId();
                        if ($status === 'Assigned' && $currentUserId !== null) {
                            $remarks = $assignmentRemarks !== '' ? $assignmentRemarks : 'Legacy asset imported with existing assignment.';
                            $historyInsert->execute([
                                ':asset_id' => $assetId,
                                ':action' => 'ASSIGNED',
                                ':from_user_id' => null,
                                ':to_user_id' => $currentUserId,
                                ':performed_by' => $performedBy,
                                ':transaction_date' => date('Y-m-d H:i:s'),
                                ':condition_status' => $condition,
                                ':remarks' => $remarks,
                            ]);
                        }
                        $conn->commit();
                    } catch (Throwable $e) {
                        if ($conn->inTransaction()) $conn->rollBack();
                        throw $e;
                    }
                    $successCount++; $addedCount++;
                }

                if ($status === 'Assigned') $assignedCount++;
                elseif ($status === 'Available') $availableCount++;
                elseif ($status === 'Maintenance') $maintenanceCount++;
                elseif ($status === 'Retired') $retiredCount++;
            } catch (Throwable $rowError) {
                $errors[] = 'Asset row ' . $excelRow . ': ' . $rowError->getMessage();
            }
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }


        if ($linkedRows && count($linkedRows) >= 2) {
            $linkedHeader = array_map('strval', $linkedRows[0]);
            $linkedMap = [];
            $linkedAliases = [
                'asset_code' => ['asset_code', 'parent_asset_code'],
                'item_code' => ['item_code', 'linked_item_code'],
                'item_name' => ['item_name', 'linked_item_name', 'name'],
                'item_type' => ['item_type', 'linked_item_type', 'type'],
                'serial_number' => ['serial_number', 'serial'],
                'quantity' => ['quantity', 'qty'],
                'condition_status' => ['condition', 'condition_status'],
                'status' => ['status', 'item_status'],
                'remarks' => ['remarks', 'description', 'notes'],
            ];
            foreach ($linkedHeader as $index => $name) {
                $normalized = st_import_normalize_header((string)$name);
                foreach ($linkedAliases as $field => $names) {
                    if (in_array($normalized, $names, true)) {
                        $linkedMap[$field] = $index;
                        break;
                    }
                }
            }
            if (!isset($linkedMap['asset_code'], $linkedMap['item_name'])) {
                $errors[] = 'Linked Items sheet was skipped because Asset Code and Item Name columns are missing.';
            } else {
                $findParent = $conn->prepare('SELECT id FROM assets WHERE asset_code = :asset_code LIMIT 1');
                $findByCode = $conn->prepare('SELECT * FROM asset_linked_items WHERE item_code = :item_code LIMIT 1');
                $findNatural = $conn->prepare('SELECT * FROM asset_linked_items WHERE asset_id = :asset_id AND LOWER(TRIM(item_name)) = LOWER(TRIM(:item_name)) AND COALESCE(serial_number, "") = COALESCE(:serial_number, "") LIMIT 1');
                $insertLinked = $conn->prepare('INSERT INTO asset_linked_items (asset_id, item_code, item_name, item_type, serial_number, quantity, condition_status, status, remarks) VALUES (:asset_id, :item_code, :item_name, :item_type, :serial_number, :quantity, :condition_status, :status, :remarks)');
                foreach (array_slice($linkedRows, 1) as $rowIndex => $row) {
                    $excelRow = $rowIndex + 2;
                    $allBlank = true;
                    foreach ($row as $cell) { if (trim((string)$cell) !== '') { $allBlank = false; break; } }
                    if ($allBlank) continue;
                    try {
                        $get = static function(array $r, array $m, string $f): string {
                            return isset($m[$f]) ? st_import_safe_text((string)($r[$m[$f]] ?? '')) : '';
                        };
                        $assetCode = $get($row, $linkedMap, 'asset_code');
                        $itemName = $get($row, $linkedMap, 'item_name');
                        if ($assetCode === '' || $itemName === '') throw new RuntimeException('Asset Code and Item Name are required.');
                        $findParent->execute([':asset_code' => $assetCode]);
                        $parent = $findParent->fetch();
                        if (!$parent) throw new RuntimeException('Parent Asset Code not found: ' . $assetCode);
                        $itemCode = $get($row, $linkedMap, 'item_code');
                        $itemType = $get($row, $linkedMap, 'item_type') ?: 'Loose Tool';
                        $serial = $get($row, $linkedMap, 'serial_number');
                        $quantityRaw = $get($row, $linkedMap, 'quantity');
                        $quantity = $quantityRaw === '' ? 1 : (int)$quantityRaw;
                        $condition = $get($row, $linkedMap, 'condition_status') ?: 'Good';
                        $itemStatus = $get($row, $linkedMap, 'status') ?: 'With Asset';
                        $remarks = $get($row, $linkedMap, 'remarks');
                        $allowedItemTypes = ['Loose Tool','Accessory','Component','Safety Equipment','Included Item','Other'];
                        $allowedItemConditions = ['New','Good','Fair','Poor','Damaged'];
                        $allowedItemStatuses = ['With Asset','Removed'];
                        if (!in_array($itemType, $allowedItemTypes, true)) throw new RuntimeException('Invalid Item Type.');
                        if (!in_array($condition, $allowedItemConditions, true)) throw new RuntimeException('Invalid Condition.');
                        if (!in_array($itemStatus, $allowedItemStatuses, true)) throw new RuntimeException('Invalid Item Status.');
                        if ($quantity < 1) throw new RuntimeException('Quantity must be at least 1.');

                        $existingLinked = null;
                        if ($itemCode !== '') {
                            $findByCode->execute([':item_code' => $itemCode]);
                            $existingLinked = $findByCode->fetch() ?: null;
                        }
                        if (!$existingLinked) {
                            $findNatural->execute([
                                ':asset_id' => (int)$parent['id'],
                                ':item_name' => $itemName,
                                ':serial_number' => $serial !== '' ? $serial : null,
                            ]);
                            $existingLinked = $findNatural->fetch() ?: null;
                        }

                        if ($existingLinked) {
                            $updateLinked = $conn->prepare('UPDATE asset_linked_items SET asset_id = :asset_id, item_code = :item_code, item_name = :item_name, item_type = :item_type, serial_number = :serial_number, quantity = :quantity, condition_status = :condition_status, status = :status, remarks = :remarks WHERE id = :id');
                            $updateLinked->execute([
                                ':asset_id' => (int)$parent['id'],
                                ':item_code' => $itemCode !== '' ? $itemCode : $existingLinked['item_code'],
                                ':item_name' => $itemName,
                                ':item_type' => $itemType,
                                ':serial_number' => $serial !== '' ? $serial : $existingLinked['serial_number'],
                                ':quantity' => $quantity,
                                ':condition_status' => $condition,
                                ':status' => $itemStatus,
                                ':remarks' => $remarks !== '' ? $remarks : $existingLinked['remarks'],
                                ':id' => (int)$existingLinked['id'],
                            ]);
                            $linkedUpdatedCount++;
                        } else {
                            $insertLinked->execute([
                                ':asset_id' => (int)$parent['id'],
                                ':item_code' => $itemCode !== '' ? $itemCode : null,
                                ':item_name' => $itemName,
                                ':item_type' => $itemType,
                                ':serial_number' => $serial !== '' ? $serial : null,
                                ':quantity' => $quantity,
                                ':condition_status' => $condition,
                                ':status' => $itemStatus,
                                ':remarks' => $remarks !== '' ? $remarks : null,
                            ]);
                            $linkedAddedCount++;
                        }
                    } catch (Throwable $linkedError) {
                        $errors[] = 'Linked Items row ' . $excelRow . ': ' . $linkedError->getMessage();
                    }
                }
            }
        }
}

$page_title = 'Asset Import / Export';
require_once "../../backend/includes/header.php";
?>

<div class="page-header">
    <div>
        <p>Import or update the complete asset register, linked items / loose tools, and employee information from one controlled Excel workbook.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="asset_import_export.php?download=template">Download Blank Template</a>
        <a class="btn btn-primary" href="asset_import_export.php?download=export">Download Current ST Data (Excel)</a>
    </div>
</div>

<div class="alert alert-info">
    <strong>Recommended workflow:</strong> Download <strong>Current ST Data (Excel)</strong> to get the actual records from ST. Edit existing rows or add new asset rows, then import the same workbook back. Asset Code is the update key; missing rows are not deleted. A blank template is also available when starting a completely new workbook.
</div>

<?php if ($successCount > 0): ?>
    <div class="alert alert-success">
        <strong><?= (int)$successCount ?> asset row(s) processed successfully.</strong>
        <div style="margin-top:8px;">
            Added: <?= (int)$addedCount ?> &nbsp;•&nbsp; Updated: <?= (int)$updatedCount ?> &nbsp;•&nbsp; Assigned: <?= (int)$assignedCount ?> &nbsp;•&nbsp;
            Available: <?= (int)$availableCount ?> &nbsp;•&nbsp;
            Maintenance: <?= (int)$maintenanceCount ?> &nbsp;•&nbsp;
            Retired: <?= (int)$retiredCount ?> &nbsp;•&nbsp; Linked Items Added: <?= (int)$linkedAddedCount ?> &nbsp;•&nbsp; Linked Items Updated: <?= (int)$linkedUpdatedCount ?>
        </div>
        <?php if ($userUpdatedCount > 0): ?>
            <div style="margin-top:8px;">Updated <?= (int)$userUpdatedCount ?> Users record(s). Added <?= (int)$userAddedCount ?> new employee user record(s). Role changes from Excel: <?= (int)$userRoleUpdatedCount ?>. Passwords are never imported from Excel; set login passwords from Users > Edit User when needed.</div>
        <?php endif; ?>
        <?php if ($assignedCount > 0): ?>
            <div style="margin-top:8px;">Assigned legacy assets were linked to Users using either Employee ID or Employee Name, and assignment/transfer history was recorded when the assignee changed.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <strong>Import completed with issues:</strong>
        <ul style="margin:8px 0 0 18px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <section class="form-card">
        <div class="section-header">
            <div>
                <h2>Import Legacy Assets</h2>
                <p>Use the Excel template to add existing company assets while preserving their current status and accountability.</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="asset_file">Excel File (.xlsx) or CSV</label>
                <input type="file" id="asset_file" name="asset_file" accept=".xlsx,.csv" required>
                <small class="secondary-text">Maximum 10 MB. Existing Asset Codes are updated instead of duplicated. Blank Excel cells do not erase existing data.</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Import Assets</button>
                <a href="assets.php" class="btn btn-secondary">Back to Assets</a>
            </div>
        </form>
    </section>

    <section class="form-card">
        <div class="section-header">
            <div>
                <h2>How Legacy Import Works</h2>
                <p>ST converts the spreadsheet into proper asset/accountability records.</p>
            </div>
        </div>
        <ol class="guide-list">
            <li>Download <strong>Current ST Data (Excel)</strong> to start with the existing database records.</li>
            <li>Edit existing asset rows or append one new asset per row.</li>
            <li>Use the exact category names already configured in ST.</li>
            <li>Set the real <strong>Status</strong>: Available, Assigned, Maintenance, or Retired.</li>
            <li>For an Assigned asset, enter either <strong>Current Employee ID</strong> or <strong>Current Employee Name</strong>. You do not need to complete both.</li>
            <li>ST finds the matching Active user, links the asset, and records the assignment or transfer in <strong>History</strong>.</li>
            <li>The <strong>Users Reference</strong> sheet updates matching Users records (name, department, position, status, role) and can create missing employee user records. Passwords are never imported from Excel. New employee usernames are generated automatically; set a login password later from Users > Edit User.</li>
            <li>Available, Maintenance, and Retired assets have no current employee; existing assigned users are cleared only when the Excel status explicitly changes to one of these statuses.</li>
            <li>QR tokens are generated automatically for every imported asset.</li>
        </ol>
    </section>
</div>

<div class="dashboard-section">
    <div class="section-header">
        <div>
            <h2>Legacy Import Rules</h2>
            <p>These rules keep imported data consistent with the existing ST workflow.</p>
        </div>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>Situation in Excel</th><th>What ST Does</th></tr></thead>
            <tbody>
                <tr><td><strong>Available</strong></td><td>Creates the asset as Available with no current employee.</td></tr>
                <tr><td><strong>Assigned + Employee ID or Name</strong></td><td>Links the asset to the matching Active user. Both fields are not required; if both are supplied they must match.</td></tr>
                <tr><td><strong>Maintenance</strong></td><td>Creates the asset as Maintenance with no current employee.</td></tr>
                <tr><td><strong>Retired</strong></td><td>Creates the asset as Retired with no current employee. The asset remains in the system.</td></tr>
                <tr><td>Employee ID or Name not found</td><td>Row is rejected. Add the employee in Users first; ST does not create a login/account from an asset spreadsheet.</td></tr>
                <tr><td>Both Employee ID and Name supplied but mismatch</td><td>Row is rejected so the asset is not silently linked to the wrong person.</td></tr>
                <tr><td>Existing Asset Code</td><td>Existing asset is updated with supplied values. Blank cells preserve existing values. No duplicate asset is created.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-section">
    <div class="section-header">
        <div>
            <h2>Import / Export Fields</h2>
            <p>Status, current accountability, and Users Reference updates are handled by the import/export workflow.</p>
        </div>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>Field</th><th>Required</th><th>Purpose</th></tr></thead>
            <tbody>
                <tr><td>Asset Code</td><td>Yes</td><td>Unique ST asset identifier.</td></tr>
                <tr><td>Asset Name</td><td>Yes</td><td>Name shown in ST.</td></tr>
                <tr><td>Category</td><td>Yes</td><td>Must match an existing ST category.</td></tr>
                <tr><td>Asset Type</td><td>Yes</td><td>Example: Laptop, Office Chair, Car.</td></tr>
                <tr><td>Serial / Plate Number</td><td>No</td><td>Unique when supplied.</td></tr>
                <tr><td>Asset Source</td><td>No</td><td>Defaults to Legacy / Existing.</td></tr>
                <tr><td>Condition</td><td>No</td><td>Defaults to Good.</td></tr>
                <tr><td>Legacy Reference</td><td>No</td><td>Old property/reference number.</td></tr>
                <tr><td>Date Added</td><td>No</td><td>Defaults to today.</td></tr>
                <tr><td>Supplier / Purchase fields</td><td>No</td><td>Historical procurement information.</td></tr>
                <tr><td><strong>Status</strong></td><td>No</td><td>Available, Assigned, Maintenance, or Retired. Defaults to Available.</td></tr>
                <tr><td><strong>Current Employee ID</strong></td><td>Optional</td><td>One of Employee ID or Employee Name may identify the assigned user.</td></tr>
                <tr><td>Current Employee Name</td><td>Optional</td><td>May be used instead of Employee ID; if both are supplied they must identify the same user.</td></tr>
                <tr><td>Assignment Remarks</td><td>No</td><td>Stored in the initial legacy assignment history.</td></tr>
                <tr><td>Description</td><td>No</td><td>Additional asset notes.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "../../backend/includes/footer.php"; ?>
