<?php
$page_title = "Asset Assignments";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

$role = $_SESSION["role"] ?? "";

$allowed_roles = [
    "Administrator",
    "Asset Custodian"
];

if (!in_array($role, $allowed_roles, true)) {
    header("Location: /st/index.php");
    exit;
}

$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.status,
        a.current_user_id,
        u.employee_id,
        u.full_name
    FROM assets a
    LEFT JOIN users u
        ON a.current_user_id = u.id
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

$sql .= "
    ORDER BY a.asset_code ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$assignments = $stmt->fetchAll();

?>

<div class="page-header">


<div>
    <p>View assets that are currently assigned to employees.</p>
</div>

<div>
    <a
        href="/st/frontend/pages/assets.php"
        class="btn btn-secondary"
    >
        View All Assets
    </a>

    <a
        href="/st/frontend/pages/assignment_add.php"
        class="btn btn-primary"
    >
        Assign Asset
    </a>
</div>


</div>

<div class="table-card" style="padding:16px;margin-bottom:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
            <strong>Initial batch accountability <span style="font-weight:normal">(optional)</span></strong>
            <p style="margin:6px 0 0">Group first-time assignments from the same PO into one signature form. Individual accountability remains available.</p>
        </div>
        <a class="btn btn-secondary" href="/st/frontend/pages/assignment_batch_select.php">Create / Reprint Batch</a>
    </div>
</div>

<div class="filter-container">

<form
    id="assignment-filter-form"
    method="GET"
    class="filter-form"
>

    <div class="form-group">
        <label for="search">Search</label>
        <input
            type="text"
            id="search"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Asset code, name, employee..."
            autocomplete="off"
        >
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="/st/frontend/pages/assignments.php" class="btn btn-secondary">Reset</a>

        <!--
         | Export options are kept behind a compact popover so the page stays clean.
         | The same options are sent to assignment_export.php and do not affect the data.
         | Checkboxes are touch-friendly and also work on desktop hover/focus.
         -->
        <div class="export-options-wrap">
            <button
                type="button"
                class="btn btn-secondary export-options-trigger"
                aria-expanded="false"
                aria-controls="assignment-export-options"
                title="Choose what information to include in the Excel report"
            >
                Export Excel <span class="export-options-chevron" aria-hidden="true">▾</span>
            </button>

            <div
                id="assignment-export-options"
                class="export-options-popover"
                role="dialog"
                aria-label="Assignment Excel export options"
            >
                <div class="export-options-title">Excel Export Options</div>
                <div class="export-options-subtitle">Choose the extra information to include.</div>

                <label class="export-option">
                    <input type="checkbox" name="include_price" value="1" form="assignment-filter-form">
                    <span><strong>Purchase price</strong><small>Purchase cost and total value</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_purchase" value="1" form="assignment-filter-form">
                    <span><strong>Supplier &amp; purchase details</strong><small>Supplier, purchase number and date</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_condition" value="1" form="assignment-filter-form" checked>
                    <span><strong>Condition &amp; description</strong><small>Asset condition and remarks/description</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_linked_items" value="1" form="assignment-filter-form" checked>
                    <span><strong>Loose tools &amp; linked items</strong><small>Item, type, serial, condition, status and quantity</small></span>
                </label>

                <label class="export-option">
                    <input type="checkbox" name="include_assignment_details" value="1" form="assignment-filter-form" checked>
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

    <table class="primary-info-table assignments-table">

        <thead>

            <tr>
                <th>Asset Code</th>
                <th>Asset Name</th>
                <th>Asset Type</th>
                <th>Employee ID</th>
                <th>Assigned To</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

        </thead>

        <tbody>

            <?php if (count($assignments) > 0): ?>

                <?php foreach ($assignments as $assignment): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars($assignment["asset_code"]) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($assignment["asset_name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($assignment["asset_type"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $assignment["employee_id"] ?? "-"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $assignment["full_name"] ?? "-"
                            ) ?>
                        </td>

                        <td>

                            <span class="status-badge status-assigned">
                                Assigned
                            </span>

                        </td>

                        <td>

                            <a
                                href="/st/frontend/pages/asset_view.php?id=<?= (int)$assignment["id"] ?>"
                                class="btn btn-secondary btn-small"
                            >
                                View
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="7"
                        style="text-align:center;"
                    >
                        No assigned assets found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


</div>

<script>
/* Assignment export popover: responsive on desktop and touch devices. */
(function () {
    const wrap = document.querySelector('.export-options-wrap');
    if (!wrap) return;

    const trigger = wrap.querySelector('.export-options-trigger');
    const popover = wrap.querySelector('.export-options-popover');
    const download = wrap.querySelector('.export-options-download');
    const cancel = wrap.querySelector('.export-options-cancel');
    const form = document.getElementById('assignment-filter-form');

    /* Move the popover to <body> so it cannot be clipped by the collapsible filter container. */
    document.body.appendChild(popover);

    function positionPopover() {
        if (!popover.classList.contains('is-open')) return;
        if (window.matchMedia('(max-width: 700px)').matches) return;

        const rect = trigger.getBoundingClientRect();
        const gap = 10;
        const margin = 12;
        const width = Math.min(390, window.innerWidth - (margin * 2));
        popover.style.width = width + 'px';

        let left = rect.right - width;
        left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));

        const popoverHeight = popover.offsetHeight;
        let top = rect.bottom + gap;
        if (top + popoverHeight > window.innerHeight - margin) {
            top = rect.top - popoverHeight - gap;
        }
        top = Math.max(margin, top);

        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
        popover.style.right = 'auto';
        popover.style.bottom = 'auto';
    }

    function setOpen(open) {
        wrap.classList.toggle('is-open', open);
        popover.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            positionPopover();
            const first = popover.querySelector('input');
            if (first) setTimeout(() => first.focus(), 40);
        }
    }

    trigger.addEventListener('click', function (event) {
        event.preventDefault();
        setOpen(!wrap.classList.contains('is-open'));
    });

    cancel.addEventListener('click', () => setOpen(false));

    download.addEventListener('click', function () {
        const params = new URLSearchParams();
        if (form) {
            new FormData(form).forEach((value, key) => {
                if (String(value).trim() !== '') params.set(key, value);
            });
        }
        params.set('export', '1');
        window.location.href = '/st/frontend/pages/assignment_export.php?' + params.toString();
        setOpen(false);
    });

    document.addEventListener('click', function (event) {
        if (!wrap.contains(event.target) && !popover.contains(event.target)) setOpen(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') setOpen(false);
    });

    window.addEventListener('resize', positionPopover);
    window.addEventListener('scroll', positionPopover, true);
})();
</script>

<?php require_once "../../backend/includes/footer.php"; ?>
