<?php
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/app_config.php";

$app_name = app_name($conn);
$app_logo = app_logo_url($conn);

/*
|--------------------------------------------------------------------------
| GET EMPLOYEE
|--------------------------------------------------------------------------
*/

$employee_id = isset($_GET["employee_id"])
    ? (int) $_GET["employee_id"]
    : 0;

if ($employee_id <= 0) {
    die("Invalid employee.");
}


/*
|--------------------------------------------------------------------------
| GET EMPLOYEE INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        employee_id,
        full_name,
        department,
        position,
        status
    FROM users
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $employee_id
]);

$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found.");
}


/*
|--------------------------------------------------------------------------
| GET CURRENTLY ASSIGNED ASSETS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,
        a.status,
        c.name AS category_name
    FROM assets a
    INNER JOIN categories c
        ON a.category_id = c.id
    WHERE a.current_user_id = :employee_id
    ORDER BY a.asset_code ASC
");

$stmt->execute([
    ":employee_id" => $employee_id
]);

$assets = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <link rel="icon" type="image/svg+xml" href="/st/frontend/assets/icons/favicon-industrial.svg?v=20261002">
    <link rel="apple-touch-icon" sizes="180x180" href="/st/frontend/assets/icons/apple-touch-industrial.png?v=20261002">



    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Employee Clearance - <?= htmlspecialchars($employee["full_name"]) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 30px;
            color: #000;
            background: #fff;
        }

        .document {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            position: relative;
            min-height: 78px;
            margin-bottom: 25px;
            padding-left: 115px;
            text-align: center;
        }

        .header-logo {
            position: absolute;
            top: 0;
            left: 0;
            display: flex;
            width: 95px;
            height: 70px;
            align-items: center;
            justify-content: flex-start;
        }

        .header-logo img {
            display: block;
            max-width: 90px;
            max-height: 65px;
            object-fit: contain;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .header h2 {
            margin: 6px 0 0;
            font-size: 18px;
            font-weight: normal;
        }

        .employee-info {
            border: 1px solid #000;
            padding: 15px;
            margin-bottom: 20px;
        }

        .employee-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-info td {
            padding: 6px;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
            width: 150px;
        }

        table.assets {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.assets th,
        table.assets td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 13px;
        }

        table.assets th {
            text-align: center;
            font-weight: bold;
        }

        .section-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 8px;
        }

        .clearance-box {
            border: 1px solid #000;
            margin-top: 20px;
            padding: 15px;
        }

        .clearance-line {
            margin: 10px 0;
        }

        .remarks {
            height: 70px;
            border-bottom: 1px solid #000;
            margin-top: 10px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 70px;
        }

        .signature {
            width: 30%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 6px;
        }

        .print-button {
            text-align: center;
            margin-bottom: 25px;
        }

        .print-button button {
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
        }

        .no-assets {
            text-align: center;
            padding: 20px;
            border: 1px solid #000;
        }

        @media print {

            body {
                padding: 0;
            }

            .print-button {
                display: none;
            }

            .document {
                max-width: none;
            }

        }

    </style>


<style id="st-a4-print-layout">
/* A4 print density control: standard / compact / extra compact. */
@page { size: A4 portrait; margin: 9mm; }
.st-print-density-control { display:inline-flex; align-items:center; gap:7px; margin:0 8px; font:12px Arial,sans-serif; }
.st-print-density-control select { padding:6px; border:1px solid #999; border-radius:4px; background:white; color:#111; }
@media print {
 html, body { width:auto !important; margin:0 !important; }
 body { padding:0 !important; background:white !important; }
 .document { width:100% !important; max-width:none !important; min-height:0 !important; margin:0 !important; padding:0 !important; }
 .st-print-density-control, .toolbar, .print-buttons { display:none !important; }
 table { width:100%; border-collapse:collapse; }
 thead { display:table-header-group; }
 tfoot { display:table-footer-group; }
 tr { break-inside:avoid; page-break-inside:avoid; }
 img { max-width:100%; }
 body[data-print-density="compact"] { font-size:11px !important; }
 body[data-print-density="compact"] .document { font-size:11px !important; }
 body[data-print-density="compact"] .header { margin-bottom:12px !important; min-height:65px !important; }
 body[data-print-density="compact"] .header-logo { transform:scale(.8); transform-origin:top left; }
 body[data-print-density="compact"] h1 { font-size:17px !important; margin:3px 0 !important; }
 body[data-print-density="compact"] h2 { font-size:12px !important; margin:3px 0 !important; }
 body[data-print-density="compact"] th,body[data-print-density="compact"] td { padding:5px 6px !important; font-size:10px !important; }
 body[data-print-density="compact"] .section-title { margin:9px 0 5px !important; padding:5px !important; }
 body[data-print-density="extra"] { font-size:10px !important; }
 body[data-print-density="extra"] .document { font-size:10px !important; }
 body[data-print-density="extra"] .header { margin-bottom:8px !important; min-height:55px !important; }
 body[data-print-density="extra"] .header-logo { transform:scale(.7); transform-origin:top left; }
 body[data-print-density="extra"] h1 { font-size:15px !important; margin:2px 0 !important; }
 body[data-print-density="extra"] h2 { font-size:11px !important; margin:2px 0 !important; }
 body[data-print-density="extra"] th,body[data-print-density="extra"] td { padding:3px 5px !important; font-size:9px !important; }
 body[data-print-density="extra"] .section-title { margin:6px 0 4px !important; padding:4px !important; }
 /* Keep signature blocks intact when possible; do not shrink QR images. */
 .signoff, .signature-section, .signature-block { break-inside:avoid; }
}
</style>
</head>

<body>

<div class="document">

    <div class="print-button">

        <button onclick="window.print()">
            Print Clearance Form
        </button>

    </div>


    <div class="header">

        <?php if ($app_logo !== ""): ?>
            <div class="header-logo">
                <img
                    src="<?= htmlspecialchars($app_logo) ?>"
                    alt="<?= htmlspecialchars($app_name) ?> logo"
                >
            </div>
        <?php endif; ?>

        <h1>
            <?= htmlspecialchars($app_name) ?>
        </h1>

        <h2>
            EMPLOYEE CLEARANCE / ASSET ACCOUNTABILITY FORM
        </h2>

    </div>


    <!-- EMPLOYEE INFORMATION -->

    <div class="employee-info">

        <table>

            <tr>

                <td class="label">
                    Employee ID:
                </td>

                <td>
                    <?= htmlspecialchars($employee["employee_id"]) ?>
                </td>

                <td class="label">
                    Status:
                </td>

                <td>
                    <?= htmlspecialchars($employee["status"]) ?>
                </td>

            </tr>

            <tr>

                <td class="label">
                    Employee Name:
                </td>

                <td colspan="3">
                    <?= htmlspecialchars($employee["full_name"]) ?>
                </td>

            </tr>

            <tr>

                <td class="label">
                    Department:
                </td>

                <td>
                    <?= htmlspecialchars($employee["department"] ?? "") ?>
                </td>

                <td class="label">
                    Position:
                </td>

                <td>
                    <?= htmlspecialchars($employee["position"] ?? "") ?>
                </td>

            </tr>

        </table>

    </div>


    <!-- ACCOUNTABILITY -->

    <div class="section-title">
        ASSET ACCOUNTABILITY
    </div>


    <?php if (count($assets) > 0): ?>

        <table class="assets">

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        Asset Code
                    </th>

                    <th>
                        Asset Name
                    </th>

                    <th>
                        Category
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Serial Number
                    </th>

                    <th>
                        Status
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($assets as $index => $asset): ?>

                    <tr>

                        <td style="text-align:center;">
                            <?= $index + 1 ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($asset["asset_code"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($asset["asset_name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($asset["category_name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($asset["asset_type"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($asset["serial_number"] ?? "") ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($asset["status"]) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php else: ?>

        <div class="no-assets">

            No assets are currently assigned to this employee.

        </div>

    <?php endif; ?>


    <!-- CLEARANCE DECLARATION -->

    <div class="clearance-box">

        <div class="clearance-line">

            <strong>Clearance Result:</strong>

            ☐ Cleared

            &nbsp;&nbsp;&nbsp;

            ☐ With Accountable Assets

        </div>


        <div class="clearance-line">

            <strong>Remarks:</strong>

        </div>

        <div class="remarks"></div>

    </div>


    <!-- SIGNATURES -->

    <div class="signatures">

        <div class="signature">

            <div class="signature-line">
                Employee / Accountable Person
            </div>

        </div>


        <div class="signature">

            <div class="signature-line">
                Asset Custodian
            </div>

        </div>


        <div class="signature">

            <div class="signature-line">
                HR / Clearance Officer
            </div>

        </div>

    </div>

</div>

<script>
(function(){var control=document.getElementById('st-density');if(!control)return;document.body.dataset.printDensity=control.value;control.addEventListener('change',function(){document.body.dataset.printDensity=control.value;});})();
</script>
</body>

</html>
