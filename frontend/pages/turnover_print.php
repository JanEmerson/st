<?php
require_once "../../backend/config/database.php";
require_once "../../backend/includes/app_config.php";

$app_name = app_name($conn);
$app_logo = app_logo_url($conn);

$asset_id = (int)($_GET["asset_id"] ?? 0);
$history_id = (int)($_GET["history_id"] ?? 0);

if ($asset_id <= 0) {
    die("Invalid asset.");
}

/*
|--------------------------------------------------------------------------
| Get Return Transaction
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        h.id AS history_id,
        h.asset_id,
        h.action,
        h.transaction_date,
        h.condition_status,
        h.remarks,

        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,
        a.supplier_name,
        a.purchase_number,
        a.purchase_date,
        a.purchase_cost,

        from_user.employee_id AS employee_id,
        from_user.full_name AS employee_name,
        from_user.department AS employee_department,
        from_user.position AS employee_position,

        performer.employee_id AS performer_employee_id,
        performer.full_name AS performer_name,
        performer.department AS performer_department,
        performer.position AS performer_position

    FROM asset_history h

    INNER JOIN assets a
        ON h.asset_id = a.id

    LEFT JOIN users from_user
        ON h.from_user_id = from_user.id

    INNER JOIN users performer
        ON h.performed_by = performer.id

    WHERE h.asset_id = :asset_id
      AND h.action = 'RETURNED'
";

$params = [
    ":asset_id" => $asset_id
];

/*
|--------------------------------------------------------------------------
| If Specific History ID Was Supplied
|--------------------------------------------------------------------------
*/

if ($history_id > 0) {

    $sql .= "
        AND h.id = :history_id
    ";

    $params[":history_id"] = $history_id;

}

/*
|--------------------------------------------------------------------------
| Get Latest Return If No History ID
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY h.transaction_date DESC, h.id DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$record = $stmt->fetch();

if (!$record) {
    die("Return transaction not found.");
}



/* Linked items / loose tools attached to the asset */
$stmt = $conn->prepare("
    SELECT item_code, item_name, item_type, serial_number, quantity, condition_status, remarks
    FROM asset_linked_items
    WHERE asset_id = :asset_id AND status = 'With Asset'
    ORDER BY id ASC
");
$stmt->execute([":asset_id" => $asset_id]);
$linked_items = $stmt->fetchAll();
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <link rel="icon" type="image/svg+xml" href="/st/frontend/assets/icons/favicon-industrial.svg?v=20261002">
    <link rel="apple-touch-icon" sizes="180x180" href="/st/frontend/assets/icons/apple-touch-industrial.png?v=20261002">



<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Asset Turnover Form -
    <?= htmlspecialchars($record["asset_code"]) ?>
</title>


<style>

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 30px;
        background: #ffffff;
        color: #111827;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 13px;
    }

    .document {
        width: 100%;
        max-width: 900px;
        margin: 0 auto;
    }

    .header {
        position: relative;
        min-height: 82px;
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
        height: 75px;
        align-items: center;
        justify-content: flex-start;
    }

    .header-logo img {
        display: block;
        max-width: 90px;
        max-height: 70px;
        object-fit: contain;
    }

    .header h1 {
        margin: 0 0 5px;
        font-size: 24px;
        text-transform: uppercase;
    }

    .header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .header p {
        margin: 8px 0 0;
        color: #4b5563;
    }

    .document-number {
        text-align: right;
        margin-bottom: 15px;
        font-size: 12px;
    }

    .section {
        margin-top: 20px;
    }

    .section-title {
        padding: 9px 12px;
        border: 1px solid #111827;
        border-bottom: none;
        font-weight: bold;
        text-transform: uppercase;
        background: #f3f4f6;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        border: 1px solid #111827;
        padding: 9px 10px;
        vertical-align: top;
    }

    th {
        width: 25%;
        text-align: left;
        background: #f9fafb;
    }

    .remarks {
        min-height: 80px;
    }

    .acknowledgment {
        margin-top: 25px;
        line-height: 1.6;
        text-align: justify;
    }

    .signature-table {
        margin-top: 55px;
    }

    .signature-table td {
        width: 50%;
        border: none;
        padding: 10px 30px;
        text-align: center;
    }

    .signature-line {
        border-bottom: 1px solid #111827;
        height: 45px;
        margin-bottom: 8px;
    }

    .signature-name {
        font-weight: bold;
    }

    .footer {
        margin-top: 35px;
        text-align: center;
        font-size: 11px;
        color: #6b7280;
    }

    .print-buttons {
        max-width: 900px;
        margin: 0 auto 20px;
        display: flex;
        gap: 10px;
    }

    .print-buttons button,
    .print-buttons a {
        padding: 9px 15px;
        border: 1px solid #9ca3af;
        background: #f3f4f6;
        color: #111827;
        text-decoration: none;
        cursor: pointer;
        font-size: 13px;
    }

    @media print {

        body {
            padding: 0;
        }

        .print-buttons {
            display: none;
        }

        .document {
            max-width: none;
        }

        .section-title {
            background: #f3f4f6 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        th {
            background: #f9fafb !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
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

<div class="print-buttons"><label class="st-print-density-control" for="st-density">A4 layout <select id="st-density" aria-label="Print layout density"><option value="standard">Standard</option><option value="compact" selected>Compact</option><option value="extra">Extra compact</option></select></label>

<button onclick="window.print()">
    Print Form
</button>

<button onclick="window.close()">
    Close
</button>

</div>

<div class="document">

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
        Asset Turnover / Return Form
    </h2>

    <p>
        Asset Return and Accountability Document
    </p>

</div>


<div class="document-number">

    Return Transaction #:
    <strong>
        <?= (int)$record["history_id"] ?>
    </strong>

</div>


<!-- Asset Information -->

<div class="section">

    <div class="section-title">
        Asset Information
    </div>


    <table>

        <tr>

            <th>
                Asset Code
            </th>

            <td>
                <?= htmlspecialchars($record["asset_code"]) ?>
            </td>

            <th>
                Asset Name
            </th>

            <td>
                <?= htmlspecialchars($record["asset_name"]) ?>
            </td>

        </tr>


        <tr>

            <th>
                Asset Type
            </th>

            <td>
                <?= htmlspecialchars($record["asset_type"]) ?>
            </td>

            <th>
                Serial Number
            </th>

            <td>
                <?= htmlspecialchars(
                    $record["serial_number"] ?? "-"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Supplier
            </th>

            <td>
                <?= htmlspecialchars(
                    $record["supplier_name"] ?? "-"
                ) ?>
            </td>

            <th>
                Purchase Number
            </th>

            <td>
                <?= htmlspecialchars(
                    $record["purchase_number"] ?? "-"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Purchase Date
            </th>

            <td>

                <?php if (!empty($record["purchase_date"])): ?>

                    <?= htmlspecialchars(
                        date(
                            "F d, Y",
                            strtotime($record["purchase_date"])
                        )
                    ) ?>

                <?php else: ?>

                    -

                <?php endif; ?>

            </td>


            <th>
                Purchase Cost
            </th>

            <td>

                <?php if ($record["purchase_cost"] !== null): ?>

                    <?= number_format(
                        (float)$record["purchase_cost"],
                        2
                    ) ?>

                <?php else: ?>

                    -

                <?php endif; ?>

            </td>

        </tr>

    </table>

</div>


<!-- Employee Information -->

<div class="section">

    <div class="section-title">
        Returning Employee
    </div>


    <table>

        <tr>

            <th>
                Employee ID
            </th>

            <td>
                <?= htmlspecialchars(
                    $record["employee_id"] ?? "-"
                ) ?>
            </td>

            <th>
                Employee Name
            </th>

            <td>
                <?= htmlspecialchars(
                    $record["employee_name"] ?? "-"
                ) ?>
            </td>

        </tr>


        <tr>

            <th>
                Department
            </th>

            <td>
                <?= htmlspecialchars(
                    $record["employee_department"] ?? "-"
                ) ?>
            </td>

            <th>
                Position
            </th>

            <td>
                <?= htmlspecialchars(
                    $record["employee_position"] ?? "-"
                ) ?>
            </td>

        </tr>

    </table>

</div>


<!-- Return Details -->

<div class="section">

    <div class="section-title">
        Return Details
    </div>


    <table>

        <tr>

            <th>
                Return Date
            </th>

            <td>

                <?= htmlspecialchars(
                    date(
                        "F d, Y h:i A",
                        strtotime($record["transaction_date"])
                    )
                ) ?>

            </td>


            <th>
                Returned Condition
            </th>

            <td>

                <?= htmlspecialchars(
                    $record["condition_status"] ?? "-"
                ) ?>

            </td>

        </tr>


        <tr>

            <th>
                Processed By
            </th>

            <td colspan="3">

                <?= htmlspecialchars(
                    $record["performer_name"]
                ) ?>

                <?php if (!empty($record["performer_employee_id"])): ?>

                    -
                    <?= htmlspecialchars(
                        $record["performer_employee_id"]
                    ) ?>

                <?php endif; ?>

            </td>

        </tr>


        <tr>

            <th>
                Remarks
            </th>

            <td
                colspan="3"
                class="remarks"
            >

                <?php if (!empty($record["remarks"])): ?>

                    <?= nl2br(
                        htmlspecialchars(
                            $record["remarks"]
                        )
                    ) ?>

                <?php else: ?>

                    -

                <?php endif; ?>

            </td>

        </tr>

    </table>

</div>


<!-- Acknowledgment -->

<div class="acknowledgment">

    The undersigned acknowledge the return of the asset identified
    above. The returning employee confirms that the asset has been
    surrendered, while the authorized personnel acknowledges receipt
    and records the condition of the asset at the time of return.

</div>


<!-- Linked Items / Loose Tools -->

<?php if (!empty($linked_items)): ?>
<div class="section linked-items-print-section">
    <div class="section-title">Linked Items / Loose Tools</div>
    <table>
        <thead>
            <tr><th>Item Code</th><th>Item Name</th><th>Type</th><th>Qty</th><th>Condition</th><th>Remarks</th></tr>
        </thead>
        <tbody>
        <?php foreach ($linked_items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item["item_code"] ?: "-") ?></td>
                <td><?= htmlspecialchars($item["item_name"]) ?></td>
                <td><?= htmlspecialchars($item["item_type"]) ?></td>
                <td><?= (int)$item["quantity"] ?></td>
                <td><?= htmlspecialchars($item["condition_status"]) ?></td>
                <td><?= htmlspecialchars($item["remarks"] ?: "-") ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>


<!-- Signatures -->

<table class="signature-table">

    <tr>

        <td>

            <div class="signature-line"></div>

            <div class="signature-name">

                <?= htmlspecialchars(
                    $record["employee_name"] ?? "Employee"
                ) ?>

            </div>

            Returning Employee

        </td>


        <td>

            <div class="signature-line"></div>

            <div class="signature-name">

                <?= htmlspecialchars(
                    $record["performer_name"]
                ) ?>

            </div>

            Asset Custodian / Authorized Personnel

        </td>

    </tr>

</table>


<div class="footer">

    Generated from <?= htmlspecialchars($app_name) ?>

</div>

</div>

<script>
(function(){var control=document.getElementById('st-density');if(!control)return;document.body.dataset.printDensity=control.value;control.addEventListener('change',function(){document.body.dataset.printDensity=control.value;});})();
</script>
</body>

</html>
