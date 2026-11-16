<?php
$page_title = "Asset History";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

$role = $_SESSION["role"] ?? "";

$allowed_roles = [
    "Administrator",
    "Asset Custodian",
    "HR / Clearance Officer",
    "Viewer"
];

if (!in_array($role, $allowed_roles, true)) {
    header("Location: /st/index.php");
    exit;
}

$search = trim($_GET["search"] ?? "");
$action = $_GET["action"] ?? "";
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");

foreach (["dateFrom", "dateTo"] as $dateField) {
    $dateValue = $$dateField;
    $parsedDate = DateTime::createFromFormat("!Y-m-d", $dateValue);
    if ($dateValue !== "" && (!$parsedDate || $parsedDate->format("Y-m-d") !== $dateValue)) {
        $$dateField = "";
    }
}

$allowed_actions = [
    "",
    "ASSIGNED",
    "TRANSFERRED",
    "RETURNED"
];

if (!in_array($action, $allowed_actions, true)) {
    $action = "";
}


/*
|--------------------------------------------------------------------------
| History Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        h.id,
        h.asset_id,
        h.transaction_date,
        h.action,
        h.condition_status,
        h.remarks,

        a.asset_code,
        a.asset_name,

        from_user.employee_id AS from_employee_id,
        from_user.full_name AS from_full_name,

        to_user.employee_id AS to_employee_id,
        to_user.full_name AS to_full_name,

        performer.full_name AS performed_by_name

    FROM asset_history h

    INNER JOIN assets a
        ON h.asset_id = a.id

    LEFT JOIN users from_user
        ON h.from_user_id = from_user.id

    LEFT JOIN users to_user
        ON h.to_user_id = to_user.id

    INNER JOIN users performer
        ON h.performed_by = performer.id

    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Action Filter
|--------------------------------------------------------------------------
*/

if ($action !== "") {

    $sql .= "
        AND h.action = :action
    ";

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


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY h.transaction_date DESC, h.id DESC
";


$stmt = $conn->prepare($sql);
$stmt->execute($params);

$history = $stmt->fetchAll();

?>

<div class="page-header">


<div>
    <p>Complete record of asset assignments, transfers and returns.</p>
</div>


</div>

<div class="filter-container">


<form
    id="history-filter-form"
    method="GET"
    class="filter-form"
>

    <div class="form-group">

        <label for="search">
            Search
        </label>

        <input
            type="text"
            id="search"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Asset, serial, employee, action, remarks..."
            autocomplete="off"
        >

    </div>


    <div class="form-group">

        <label for="action">
            Action
        </label>

        <select
            id="action"
            name="action"
        >

            <option value="">
                All Actions
            </option>

            <option
                value="ASSIGNED"
                <?= $action === "ASSIGNED" ? "selected" : "" ?>
            >
                Assigned
            </option>

            <option
                value="TRANSFERRED"
                <?= $action === "TRANSFERRED" ? "selected" : "" ?>
            >
                Transferred
            </option>

            <option
                value="RETURNED"
                <?= $action === "RETURNED" ? "selected" : "" ?>
            >
                Returned
            </option>

        </select>

    </div>


    <div class="form-group">
        <label for="date_from">From date</label>
        <input
            type="date"
            id="date_from"
            name="date_from"
            value="<?= htmlspecialchars($dateFrom) ?>"
        >
    </div>


    <div class="form-group">
        <label for="date_to">To date</label>
        <input
            type="date"
            id="date_to"
            name="date_to"
            value="<?= htmlspecialchars($dateTo) ?>"
        >
    </div>


    <div class="filter-actions">

        <button
            type="submit"
            class="btn btn-primary"
        >
            Search
        </button>

        <a
            href="/st/frontend/pages/history.php"
            class="btn btn-secondary"
        >
            Reset
        </a>

        <div class="export-options-wrap">
            <button
                type="button"
                class="btn btn-secondary export-options-trigger"
                aria-expanded="false"
                aria-controls="history-export-options"
                title="Choose what information to include in the Excel report"
            >
                Export Excel <span class="export-options-chevron" aria-hidden="true">▾</span>
            </button>

            <div
                id="history-export-options"
                class="export-options-popover"
                role="dialog"
                aria-label="History Excel export options"
            >
                <div class="export-options-title">Excel Export Options</div>
                <div class="export-options-subtitle">Choose the extra information to include.</div>

                <label class="export-option">
                    <input type="checkbox" name="include_price" value="1" form="history-filter-form">
                    <span><strong>Purchase price</strong><small>Purchase cost and total value</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_purchase" value="1" form="history-filter-form">
                    <span><strong>Supplier &amp; purchase details</strong><small>Supplier, purchase number and date</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_condition" value="1" form="history-filter-form" checked>
                    <span><strong>Condition &amp; description</strong><small>Recorded condition and asset description</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_linked_items" value="1" form="history-filter-form" checked>
                    <span><strong>Loose tools &amp; linked items</strong><small>Linked items for the assets in this report</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_assignment_details" value="1" form="history-filter-form" checked>
                    <span><strong>Assignment details</strong><small>Assignment date, assigned by and transaction remarks</small></span>
                </label>

                <div class="export-options-actions">
                    <button type="button" class="btn btn-secondary btn-small export-options-cancel">Cancel</button>
                    <button type="button" class="btn btn-primary btn-small export-options-download">Download Excel</button>
                </div>
            </div>
        </div>

    </div>

</form>


</div>

<div class="table-card">


<div class="table-responsive">

    <table class="primary-info-table history-table">

        <thead>

            <tr>

                <th>Date / Time</th>
                <th>Asset</th>
                <th>Action</th>
                <th>From</th>
                <th>To</th>
                <th>Performed By</th>
                <th>Condition</th>
                <th>Remarks</th>

            </tr>

        </thead>

        <tbody>

            <?php if (count($history) > 0): ?>

                <?php foreach ($history as $record): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                date(
                                    "M d, Y h:i A",
                                    strtotime($record["transaction_date"])
                                )
                            ) ?>
                        </td>


                        <td>

                            <a
                                href="/st/frontend/pages/asset_view.php?id=<?= (int)$record["asset_id"] ?>"
                            >
                                <strong>
                                    <?= htmlspecialchars($record["asset_code"]) ?>
                                </strong>
                            </a>

                            <br>

                            <small>
                                <?= htmlspecialchars($record["asset_name"]) ?>
                            </small>

                        </td>


                        <td>

                            <?php
                            $action_class = "";

                            if ($record["action"] === "ASSIGNED") {
                                $action_class = "status-assigned";
                            } elseif ($record["action"] === "TRANSFERRED") {
                                $action_class = "status-available";
                            } elseif ($record["action"] === "RETURNED") {
                                $action_class = "status-maintenance";
                            }
                            ?>

                            <span class="status-badge <?= $action_class ?>">
                                <?= htmlspecialchars($record["action"]) ?>
                            </span>

                        </td>


                        <td>

                            <?php if ($record["from_full_name"]): ?>

                                <?= htmlspecialchars($record["from_full_name"]) ?>

                                <?php if ($record["from_employee_id"]): ?>

                                    <br>

                                    <small>
                                        <?= htmlspecialchars($record["from_employee_id"]) ?>
                                    </small>

                                <?php endif; ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if ($record["to_full_name"]): ?>

                                <?= htmlspecialchars($record["to_full_name"]) ?>

                                <?php if ($record["to_employee_id"]): ?>

                                    <br>

                                    <small>
                                        <?= htmlspecialchars($record["to_employee_id"]) ?>
                                    </small>

                                <?php endif; ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $record["performed_by_name"]
                            ) ?>
                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $record["condition_status"] ?? "-"
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $record["remarks"] ?? "-"
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="8"
                        style="text-align:center;"
                    >
                        No asset history found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


</div>

<script>
(function () {
    const wrap = document.querySelector('.export-options-wrap');
    if (!wrap) return;

    const trigger = wrap.querySelector('.export-options-trigger');
    const popover = wrap.querySelector('.export-options-popover');
    const download = wrap.querySelector('.export-options-download');
    const cancel = wrap.querySelector('.export-options-cancel');
    const form = document.getElementById('history-filter-form');

    document.body.appendChild(popover);

    function positionPopover() {
        if (!popover.classList.contains('is-open')) return;
        if (window.matchMedia('(max-width: 700px)').matches) return;

        const rect = trigger.getBoundingClientRect();
        const margin = 12;
        const width = Math.min(390, window.innerWidth - (margin * 2));
        popover.style.width = width + 'px';

        let left = rect.right - width;
        left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));

        const height = popover.offsetHeight;
        let top = rect.bottom + 10;
        if (top + height > window.innerHeight - margin) top = rect.top - height - 10;
        top = Math.max(margin, top);

        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
        popover.style.right = 'auto';
        popover.style.bottom = 'auto';
    }

    function setOpen(open) {
        wrap.classList.toggle('is-open', open);
        popover.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', String(open));
        if (open) {
            positionPopover();
            const first = popover.querySelector('input');
            if (first) setTimeout(() => first.focus(), 40);
        }
    }

    trigger.addEventListener('click', () => setOpen(!wrap.classList.contains('is-open')));
    cancel.addEventListener('click', () => setOpen(false));

    download.addEventListener('click', () => {
        const params = new URLSearchParams();
        if (form) {
            new FormData(form).forEach((value, key) => {
                if (String(value).trim() !== '') params.set(key, value);
            });
        }
        window.location.href = '/st/frontend/pages/history_export.php?' + params.toString();
        setOpen(false);
    });

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target) && !popover.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
    window.addEventListener('resize', positionPopover);
    window.addEventListener('scroll', positionPopover, true);
})();
</script>

<?php require_once "../../backend/includes/footer.php"; ?>
