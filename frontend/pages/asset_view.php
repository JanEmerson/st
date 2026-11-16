<?php
$page_title = "Asset Details";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/qr_helper.php";


/*
|--------------------------------------------------------------------------
| Get Asset ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: assets.php?error=notfound");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Asset Details
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.serial_number,
        a.asset_source,
        a.condition_status AS asset_condition,
        a.legacy_reference,
        a.date_added,
        a.supplier_name,
        a.purchase_number,
        a.purchase_date,
        a.purchase_cost,
        a.status,
        a.current_user_id,
        a.description,
        a.created_at,
        a.updated_at,
        a.qr_token,
        a.qr_enabled,

        c.name AS category_name,

        u.full_name AS current_user_name,
        u.employee_id AS current_employee_id,
        u.department AS current_department,
        u.position AS current_position

    FROM assets a

    INNER JOIN categories c
        ON a.category_id = c.id

    LEFT JOIN users u
        ON a.current_user_id = u.id

    WHERE a.id = :id

    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ":id" => $id
]);

$asset = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Asset Not Found
|--------------------------------------------------------------------------
*/

if (!$asset) {

    header(
        "Location: assets.php?error=notfound"
    );

    exit;
}


$identifier_label =
    strtolower(trim($asset["category_name"] ?? "")) === "vehicles"
        ? "Plate Number"
        : "Serial Number";


/*
|--------------------------------------------------------------------------
| Get Asset Images
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        image_filename,
        image_original_name,
        created_at
    FROM asset_images
    WHERE asset_id = :asset_id
    ORDER BY id ASC
");

$stmt->execute([
    ":asset_id" => $id
]);

$asset_images = $stmt->fetchAll();

$image_count = count($asset_images);


/*
|--------------------------------------------------------------------------
| Get Linked Items / Loose Tools
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id, item_code, item_name, item_type, serial_number, quantity,
        condition_status, status, remarks
    FROM asset_linked_items
    WHERE asset_id = :asset_id
    ORDER BY status = 'With Asset' DESC, id ASC
");

$stmt->execute([
    ":asset_id" => $id
]);

$linked_items = $stmt->fetchAll();
$active_linked_items = array_values(array_filter($linked_items, static function ($item) {
    return ($item["status"] ?? "") === "With Asset";
}));


/*
|--------------------------------------------------------------------------
| Get Asset History
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        h.id,
        h.asset_id,
        h.action,
        h.transaction_date,
        h.condition_status,
        h.remarks,

        from_user.full_name AS from_user_name,
        to_user.full_name AS to_user_name,
        performer.full_name AS performed_by_name

    FROM asset_history h

    LEFT JOIN users from_user
        ON h.from_user_id = from_user.id

    LEFT JOIN users to_user
        ON h.to_user_id = to_user.id

    INNER JOIN users performer
        ON h.performed_by = performer.id

    WHERE h.asset_id = :asset_id

    ORDER BY
        h.transaction_date DESC,
        h.id DESC
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ":asset_id" => $id
]);

$history = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Current Role
|--------------------------------------------------------------------------
*/

$role = $_SESSION["role"] ?? "";


/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/

$status_class = strtolower(
    str_replace(
        " ",
        "-",
        $asset["status"]
    )
);

?>

<!-- =========================================================
     PAGE HEADER
     ========================================================= -->

<div class="page-header">

    <div>

        <h2>
            <?= htmlspecialchars(
                $asset["asset_name"]
            ) ?>
        </h2>

        <p>

            Asset Code:

            <strong>
                <?= htmlspecialchars(
                    $asset["asset_code"]
                ) ?>
            </strong>

        </p>

    </div>


    <div class="page-actions">

        <a
            href="assets.php"
            class="btn btn-secondary"
        >
            Back to Assets
        </a>


        <?php if (
            in_array(
                $role,
                [
                    "Administrator",
                    "Asset Custodian"
                ],
                true
            )
        ): ?>

            <a
                href="asset_edit.php?id=<?= (int) $asset["id"] ?>"
                class="btn btn-primary"
            >
                Edit Asset
            </a>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     ASSET QR CODE
     ========================================================= -->

<div class="detail-card asset-qr-card">

    <div class="detail-card-header">
        <h3>Asset QR Code</h3>
    </div>

    <div class="asset-qr-layout">
        <div class="asset-qr-preview">
            <?php if (!empty($asset["qr_token"]) && (int)($asset["qr_enabled"] ?? 1) === 1): ?>
                <?php $asset_qr_link = asset_qr_url($conn, $asset["qr_token"]); ?>
                <img
                    src="<?= htmlspecialchars(asset_qr_image_url($asset_qr_link, 320)) ?>"
                    alt="QR code for <?= htmlspecialchars($asset["asset_code"]) ?>"
                    class="asset-qr-image"
                >
            <?php else: ?>
                <div class="empty-state">QR code disabled.</div>
            <?php endif; ?>
        </div>

        <div class="asset-qr-info">
            <p><strong>Scan to verify this asset.</strong></p>
            <p class="secondary-text">
                Public scans show limited view-only information. Logged-in users are sent to the normal Asset Details page and your existing role permissions still apply.
            </p>

            <?php if (!empty($asset["qr_token"]) && (int)($asset["qr_enabled"] ?? 1) === 1): ?>
                <div class="form-actions">
                    <a href="asset_qr_print.php?id=<?= (int)$asset["id"] ?>" target="_blank" class="btn btn-primary">Print QR Label</a>
                    <a href="<?= htmlspecialchars($asset_qr_link) ?>" target="_blank" class="btn btn-secondary">Test Scan Link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- =========================================================
     ASSET LIFECYCLE TIMELINE
     ========================================================= -->

<div class="detail-card asset-lifecycle-card">
    <div class="detail-card-header">
        <div>
            <h3>Asset Lifecycle</h3>
            <p class="secondary-text">A quick accountability timeline for this asset.</p>
        </div>
    </div>

    <div class="asset-lifecycle-summary">
        <div class="asset-lifecycle-step <?= $asset["status"] === "Available" ? "is-current" : "" ?>">
            <span class="asset-lifecycle-dot"></span>
            <strong>Available</strong>
            <small>Ready for assignment</small>
        </div>
        <div class="asset-lifecycle-line"></div>
        <div class="asset-lifecycle-step <?= $asset["status"] === "Assigned" ? "is-current" : "" ?>">
            <span class="asset-lifecycle-dot"></span>
            <strong>Assigned</strong>
            <small><?= $asset["current_user_name"] ? htmlspecialchars($asset["current_user_name"]) : "No current user" ?></small>
        </div>
        <div class="asset-lifecycle-line"></div>
        <div class="asset-lifecycle-step <?= $asset["status"] === "Maintenance" ? "is-current" : "" ?>">
            <span class="asset-lifecycle-dot"></span>
            <strong>Maintenance</strong>
            <small>Repair / service workflow</small>
        </div>
        <div class="asset-lifecycle-line"></div>
        <div class="asset-lifecycle-step <?= $asset["status"] === "Retired" ? "is-current" : "" ?>">
            <span class="asset-lifecycle-dot"></span>
            <strong>Retired</strong>
            <small>Removed from active inventory</small>
        </div>
    </div>

    <?php if (count($history) > 0): ?>
        <div class="asset-lifecycle-events">
            <?php foreach (array_slice($history, 0, 8) as $event): ?>
                <div class="asset-lifecycle-event">
                    <span class="asset-lifecycle-event-dot"></span>
                    <div>
                        <strong><?= htmlspecialchars($event["action"]) ?></strong>
                        <span class="secondary-text">
                            <?= htmlspecialchars(date("M d, Y h:i A", strtotime($event["transaction_date"]))) ?>
                            · <?= htmlspecialchars($event["performed_by_name"] ?? "System") ?>
                        </span>
                        <?php if (!empty($event["remarks"])): ?>
                            <p><?= htmlspecialchars($event["remarks"]) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">No accountability transactions have been recorded yet.</div>
    <?php endif; ?>
</div>


<!-- =========================================================
     LINKED ITEMS / LOOSE TOOLS
     ========================================================= -->

<div class="detail-card linked-items-card">
    <div class="detail-card-header">
        <div>
            <h3>Linked Items / Loose Tools</h3>
            <p class="secondary-text">Items that belong with or are normally kept with this asset.</p>
        </div>
        <?php if (in_array($role, ["Administrator", "Asset Custodian"], true)): ?>
            <a href="asset_linked_items.php?asset_id=<?= (int)$asset["id"] ?>" class="btn btn-primary">Manage Linked Items</a>
        <?php endif; ?>
    </div>

    <?php if (count($active_linked_items) > 0): ?>
        <div class="table-responsive">
            <table class="data-table linked-items-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Condition</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_linked_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item["item_code"] ?: "-") ?></td>
                            <td><strong><?= htmlspecialchars($item["item_name"]) ?></strong></td>
                            <td><?= htmlspecialchars($item["item_type"]) ?></td>
                            <td><?= (int)$item["quantity"] ?></td>
                            <td><?= htmlspecialchars($item["condition_status"]) ?></td>
                            <td><?= htmlspecialchars($item["remarks"] ?: "-") ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            No linked items or loose tools have been recorded for this asset.
            <?php if (in_array($role, ["Administrator", "Asset Custodian"], true)): ?>
                <div style="margin-top:12px;">
                    <a href="asset_linked_items.php?asset_id=<?= (int)$asset["id"] ?>" class="btn btn-primary">+ Add Loose Tool / Linked Item</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>


<!-- =========================================================
     ASSET IMAGES
     ========================================================= -->

<div class="detail-card">

    <div class="detail-card-header">

        <h3>
            Asset Images
        </h3>

    </div>


    <div>

        <?php if ($image_count > 0): ?>

            <div class="asset-image-gallery">

                <?php foreach (
                    $asset_images as $index => $image
                ): ?>

                    <button
                        type="button"
                        class="asset-image-thumbnail"
                        onclick="openImageViewer(<?= $index ?>)"
                        aria-label="View asset image <?= $index + 1 ?>"
                    >

                        <img
                            src="/st/uploads/assets/<?= htmlspecialchars(
                                $image["image_filename"]
                            ) ?>"
                            alt="<?= htmlspecialchars(
                                $image["image_original_name"]
                                ?? "Asset Image"
                            ) ?>"
                        >

                    </button>

                <?php endforeach; ?>

            </div>


            <p class="secondary-text">

                <?= $image_count ?> of 8 images uploaded.

                Click an image to view it larger.

            </p>


        <?php else: ?>

            <div class="asset-image-empty">

                No images have been uploaded
                for this asset.

                <?php if (
                    in_array(
                        $role,
                        [
                            "Administrator",
                            "Asset Custodian"
                        ],
                        true
                    )
                ): ?>

                    <div style="margin-top:15px;">

                        <a
                            href="asset_edit.php?id=<?= (int) $asset["id"] ?>"
                            class="btn btn-primary"
                        >
                            Add Images
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     IMAGE VIEWER
     ========================================================= -->

<?php if ($image_count > 0): ?>

    <div
        id="assetImageViewer"
        class="asset-image-viewer"
        onclick="closeImageViewer(event)"
    >

        <div
            class="asset-image-viewer-content"
            onclick="event.stopPropagation()"
        >

            <!-- Close -->

            <button
                type="button"
                class="asset-viewer-close"
                onclick="closeImageViewer()"
                aria-label="Close image viewer"
            >
                &times;
            </button>


            <?php if ($image_count > 1): ?>

                <!-- Previous -->

                <button
                    type="button"
                    class="asset-viewer-prev"
                    onclick="previousImage()"
                    aria-label="Previous image"
                >
                    &#10094;
                </button>


                <!-- Next -->

                <button
                    type="button"
                    class="asset-viewer-next"
                    onclick="nextImage()"
                    aria-label="Next image"
                >
                    &#10095;
                </button>

            <?php endif; ?>


            <!-- Main Image -->

            <img
                id="assetViewerImage"
                class="asset-viewer-image"
                src=""
                alt="Asset Image"
            >


            <!-- Counter -->

            <div
                id="assetImageCounter"
                class="asset-image-counter"
            >
                1 / <?= $image_count ?>
            </div>

        </div>

    </div>


    <script>

        /*
        |--------------------------------------------------------------------------
        | Asset Images
        |--------------------------------------------------------------------------
        */

        const assetImages = [

            <?php foreach (
                $asset_images as $image
            ): ?>

                {
                    src:
                        <?= json_encode(
                            "/st/uploads/assets/" .
                            $image["image_filename"]
                        ) ?>,

                    name:
                        <?= json_encode(
                            $image["image_original_name"]
                            ?? "Asset Image"
                        ) ?>
                },

            <?php endforeach; ?>

        ];


        let currentImageIndex = 0;


        /*
        |--------------------------------------------------------------------------
        | Open Image Viewer
        |--------------------------------------------------------------------------
        */

        /* Purpose: Open the selected asset image viewer. */

        function openImageViewer(index) {

            currentImageIndex = index;

            updateImageViewer();

            const viewer =
                document.getElementById(
                    "assetImageViewer"
                );

            viewer.classList.add("active");

            document.body.style.overflow =
                "hidden";

        }


        /*
        |--------------------------------------------------------------------------
        | Close Image Viewer
        |--------------------------------------------------------------------------
        */

        /* Purpose: Close the asset image viewer. */

        function closeImageViewer(event) {

            if (
                event &&
                event.target &&
                event.target.id !==
                "assetImageViewer"
            ) {
                return;
            }


            const viewer =
                document.getElementById(
                    "assetImageViewer"
                );

            viewer.classList.remove("active");

            document.body.style.overflow =
                "";

        }


        /*
        |--------------------------------------------------------------------------
        | Update Image Viewer
        |--------------------------------------------------------------------------
        */

        /* Purpose: Update the large image viewer and navigation state. */

        function updateImageViewer() {

            if (
                assetImages.length === 0
            ) {
                return;
            }


            const image =
                assetImages[currentImageIndex];


            const viewerImage =
                document.getElementById(
                    "assetViewerImage"
                );


            const counter =
                document.getElementById(
                    "assetImageCounter"
                );


            viewerImage.src =
                image.src;


            viewerImage.alt =
                image.name;


            counter.textContent =
                (
                    currentImageIndex + 1
                ) +
                " / " +
                assetImages.length;

        }


        /*
        |--------------------------------------------------------------------------
        | Next Image
        |--------------------------------------------------------------------------
        */

        /* Purpose: Show the next asset image. */

        function nextImage() {

            if (
                assetImages.length <= 1
            ) {
                return;
            }


            currentImageIndex++;


            if (
                currentImageIndex >=
                assetImages.length
            ) {

                currentImageIndex = 0;

            }


            updateImageViewer();

        }


        /*
        |--------------------------------------------------------------------------
        | Previous Image
        |--------------------------------------------------------------------------
        */

        /* Purpose: Show the previous asset image. */

        function previousImage() {

            if (
                assetImages.length <= 1
            ) {
                return;
            }


            currentImageIndex--;


            if (
                currentImageIndex < 0
            ) {

                currentImageIndex =
                    assetImages.length - 1;

            }


            updateImageViewer();

        }


        /*
        |--------------------------------------------------------------------------
        | Keyboard Controls
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "keydown",
            function(event) {

                const viewer =
                    document.getElementById(
                        "assetImageViewer"
                    );


                if (
                    !viewer.classList.contains(
                        "active"
                    )
                ) {
                    return;
                }


                /*
                | Escape
                */

                if (
                    event.key ===
                    "Escape"
                ) {

                    closeImageViewer();

                    return;

                }


                /*
                | Right Arrow
                */

                if (
                    event.key ===
                    "ArrowRight"
                ) {

                    nextImage();

                    return;

                }


                /*
                | Left Arrow
                */

                if (
                    event.key ===
                    "ArrowLeft"
                ) {

                    previousImage();

                    return;

                }

            }
        );

    </script>

<?php endif; ?>


<!-- =========================================================
     ASSET INFORMATION
     ========================================================= -->

<div class="detail-card">

    <div class="detail-card-header">

        <h3>
            Asset Information
        </h3>

    </div>


    <div class="detail-grid">

        <div class="detail-item">

            <span class="detail-label">
                Asset Code
            </span>

            <strong>
                <?= htmlspecialchars(
                    $asset["asset_code"]
                ) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Asset Name
            </span>

            <strong>
                <?= htmlspecialchars(
                    $asset["asset_name"]
                ) ?>
            </strong>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Category
            </span>

            <span>
                <?= htmlspecialchars(
                    $asset["category_name"]
                ) ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Asset Source
            </span>

            <span>
                <?= htmlspecialchars($asset["asset_source"] ?? "New Purchase") ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Current Condition
            </span>

            <span>
                <?= htmlspecialchars($asset["asset_condition"] ?? "-") ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Legacy / Previous Reference
            </span>

            <span>
                <?= htmlspecialchars($asset["legacy_reference"] ?? "-") ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Date Added to ST
            </span>

            <span>
                <?= htmlspecialchars($asset["date_added"] ?? "-") ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Asset Type
            </span>

            <span>
                <?= htmlspecialchars(
                    $asset["asset_type"]
                ) ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                <?= htmlspecialchars($identifier_label) ?>
            </span>

            <span>
                <?= htmlspecialchars(
                    $asset["serial_number"]
                    ?? "-"
                ) ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Status
            </span>

            <span
                class="status-badge status-<?= htmlspecialchars(
                    $status_class
                ) ?>"
            >

                <?= htmlspecialchars(
                    $asset["status"]
                ) ?>

            </span>

        </div>

    </div>

</div>


<!-- =========================================================
     PURCHASE INFORMATION
     ========================================================= -->

<div class="detail-card">

    <div class="detail-card-header">

        <h3>
            Purchase Information
        </h3>

    </div>


    <div class="detail-grid">

        <div class="detail-item">

            <span class="detail-label">
                Supplier
            </span>

            <span>
                <?= htmlspecialchars(
                    $asset["supplier_name"]
                    ?? "-"
                ) ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Purchase / PO Number
            </span>

            <span>
                <?= htmlspecialchars(
                    $asset["purchase_number"]
                    ?? "-"
                ) ?>
            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Purchase Date
            </span>

            <span>

                <?php if (
                    !empty(
                        $asset["purchase_date"]
                    )
                ): ?>

                    <?= htmlspecialchars(
                        date(
                            "M d, Y",
                            strtotime(
                                $asset["purchase_date"]
                            )
                        )
                    ) ?>

                <?php else: ?>

                    -

                <?php endif; ?>

            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Purchase Cost
            </span>

            <span>

                <?php if (
                    $asset["purchase_cost"]
                    !== null
                ): ?>

                    ₱<?= number_format(
                        (float)
                        $asset["purchase_cost"],
                        2
                    ) ?>

                <?php else: ?>

                    -

                <?php endif; ?>

            </span>

        </div>

    </div>

</div>


<!-- =========================================================
     CURRENT ACCOUNTABILITY
     ========================================================= -->

<div class="detail-card">

    <div class="detail-card-header">

        <h3>
            Current Accountability
        </h3>

    </div>


    <?php if (
        !empty(
            $asset["current_user_id"]
        )
    ): ?>

        <div class="detail-grid">

            <div class="detail-item">

                <span class="detail-label">
                    Employee ID
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $asset[
                            "current_employee_id"
                        ] ?? "-"
                    ) ?>
                </strong>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Employee Name
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $asset[
                            "current_user_name"
                        ] ?? "-"
                    ) ?>
                </strong>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Department
                </span>

                <span>
                    <?= htmlspecialchars(
                        $asset[
                            "current_department"
                        ] ?? "-"
                    ) ?>
                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Position
                </span>

                <span>
                    <?= htmlspecialchars(
                        $asset[
                            "current_position"
                        ] ?? "-"
                    ) ?>
                </span>

            </div>

        </div>

    <?php else: ?>

        <div>

            <div class="asset-image-empty">

                This asset is currently
                unassigned.

            </div>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     DESCRIPTION
     ========================================================= -->

<div class="detail-card">

    <div class="detail-card-header">

        <h3>
            Description
        </h3>

    </div>


    <div class="description-box">

        <?php if (
            !empty(
                $asset["description"]
            )
        ): ?>

            <?= nl2br(
                htmlspecialchars(
                    $asset["description"]
                )
            ) ?>

        <?php else: ?>

            <span class="secondary-text">
                No description provided.
            </span>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     ASSET HISTORY
     ========================================================= -->

<div class="dashboard-section">

    <div class="section-header">

        <h2>
            Asset History
        </h2>

        <p>
            All assignment, transfer,
            and return transactions.
        </p>

    </div>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>
                        Date
                    </th>

                    <th>
                        Action
                    </th>

                    <th>
                        From
                    </th>

                    <th>
                        To
                    </th>

                    <th>
                        Condition
                    </th>

                    <th>
                        Performed By
                    </th>

                    <th>
                        Remarks
                    </th>

                    <th>
                        Print
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (
                    count($history) > 0
                ): ?>

                    <?php foreach (
                        $history as $item
                    ): ?>

                        <tr>

                            <!-- Date -->

                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $item[
                                                "transaction_date"
                                            ]
                                        )
                                    )
                                ) ?>

                            </td>


                            <!-- Action -->

                            <td>

                                <span class="action-badge">

                                    <?= htmlspecialchars(
                                        $item["action"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- From -->

                            <td>

                                <?= htmlspecialchars(
                                    $item[
                                        "from_user_name"
                                    ] ?? "-"
                                ) ?>

                            </td>


                            <!-- To -->

                            <td>

                                <?= htmlspecialchars(
                                    $item[
                                        "to_user_name"
                                    ] ?? "-"
                                ) ?>

                            </td>


                            <!-- Condition -->

                            <td>

                                <?= htmlspecialchars(
                                    $item[
                                        "condition_status"
                                    ] ?? "-"
                                ) ?>

                            </td>


                            <!-- Performed By -->

                            <td>

                                <?= htmlspecialchars(
                                    $item[
                                        "performed_by_name"
                                    ]
                                ) ?>

                            </td>


                            <!-- Remarks -->

                            <td>

                                <?= htmlspecialchars(
                                    $item[
                                        "remarks"
                                    ] ?? "-"
                                ) ?>

                            </td>


                            <!-- Print -->

                            <td>

                                <?php if (
                                    $item["action"] ===
                                    "ASSIGNED"
                                ): ?>

                                    <a
                                        href="assignment_print.php?asset_id=<?= (int) $item["asset_id"] ?>&history_id=<?= (int) $item["id"] ?>"
                                        target="_blank"
                                        class="btn btn-small"
                                    >
                                        Print Assignment
                                    </a>


                                <?php elseif (
                                    $item["action"] ===
                                    "TRANSFERRED"
                                ): ?>

                                    <a
                                        href="transfer_print.php?asset_id=<?= (int) $item["asset_id"] ?>&history_id=<?= (int) $item["id"] ?>"
                                        target="_blank"
                                        class="btn btn-small"
                                    >
                                        Print Transfer
                                    </a>


                                <?php elseif (
                                    $item["action"] ===
                                    "RETURNED"
                                ): ?>

                                    <a
                                        href="turnover_print.php?asset_id=<?= (int) $item["asset_id"] ?>&history_id=<?= (int) $item["id"] ?>"
                                        target="_blank"
                                        class="btn btn-small"
                                    >
                                        Print Turnover
                                    </a>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty-state"
                        >
                            No transaction history
                            for this asset yet.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================================================
     RECORD INFORMATION
     ========================================================= -->

<div class="detail-card">

    <div class="detail-card-header">

        <h3>
            Record Information
        </h3>

    </div>


    <div class="detail-grid">

        <div class="detail-item">

            <span class="detail-label">
                Created
            </span>

            <span>

                <?= htmlspecialchars(
                    date(
                        "M d, Y h:i A",
                        strtotime(
                            $asset["created_at"]
                        )
                    )
                ) ?>

            </span>

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Last Updated
            </span>

            <span>

                <?= htmlspecialchars(
                    date(
                        "M d, Y h:i A",
                        strtotime(
                            $asset["updated_at"]
                        )
                    )
                ) ?>

            </span>

        </div>

    </div>

</div>


<?php

require_once "../../backend/includes/footer.php";

?>
