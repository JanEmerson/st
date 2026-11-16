<?php
$page_title = "Add Asset";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";


/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/

$allowed_roles = [
    "Administrator",
    "Asset Custodian"
];

if (
    !in_array(
        $_SESSION["role"] ?? "",
        $allowed_roles,
        true
    )
) {
    http_response_code(403);
    die("Access denied.");
}


/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        id,
        name
    FROM categories
    ORDER BY name ASC
");

$categories = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Form Processing
|--------------------------------------------------------------------------
*/

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    require_csrf();

    $asset_code = trim($_POST["asset_code"] ?? "");
    $asset_name = trim($_POST["asset_name"] ?? "");

    $category_id = filter_input(
        INPUT_POST,
        "category_id",
        FILTER_VALIDATE_INT
    );

    $asset_type = trim($_POST["asset_type"] ?? "");
    $serial_number = trim($_POST["serial_number"] ?? "");
    $supplier_name = trim($_POST["supplier_name"] ?? "");

    $asset_source = trim(
        $_POST["asset_source"] ??
        ($_GET["source"] ?? "New Purchase")
    );

    // Only legacy assets retain a manually entered asset code.
    // Ignore any submitted asset_code for other sources (server-side enforcement).
    $manual_asset_code = $asset_source === "Legacy / Existing";
    if (!$manual_asset_code) {
        $asset_code = "ST-" . strtoupper(bin2hex(random_bytes(8)));
    }

    $condition_status = trim(
        $_POST["condition_status"] ?? "New"
    );

    $legacy_reference = trim(
        $_POST["legacy_reference"] ?? ""
    );

    $date_added = trim(
        $_POST["date_added"] ?? date("Y-m-d")
    );
    $purchase_number = trim($_POST["purchase_number"] ?? "");
    $purchase_date = trim($_POST["purchase_date"] ?? "");
    $purchase_cost = trim($_POST["purchase_cost"] ?? "");
    $status = trim($_POST["status"] ?? "Available");
    $description = trim($_POST["description"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $asset_code === "" ||
        $asset_name === "" ||
        !$category_id ||
        $asset_type === ""
    ) {

        $error = "Please fill in all required fields.";

    } elseif (
        !in_array(
            $status,
            [
                "Available",
                "Assigned",
                "Maintenance",
                "Retired"
            ],
            true
        )
    ) {

        $error = "Invalid asset status.";

    }


    if (
        $error === "" &&
        !in_array(
            $asset_source,
            [
                "New Purchase",
                "Legacy / Existing",
                "Donated",
                "Transferred",
                "Other"
            ],
            true
        )
    ) {
        $error = "Invalid asset source.";
    }

    if (
        $error === "" &&
        !in_array(
            $condition_status,
            [
                "New",
                "Good",
                "Fair",
                "Poor",
                "Damaged"
            ],
            true
        )
    ) {
        $error = "Invalid asset condition.";
    }

    if (
        $error === "" &&
        $date_added !== "" &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_added)
    ) {
        $error = "Invalid date added.";
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Images
    |--------------------------------------------------------------------------
    */

    $uploaded_images = [];

    if (
        $error === "" &&
        isset($_FILES["asset_images"]) &&
        is_array($_FILES["asset_images"]["name"])
    ) {

        $file_count = count($_FILES["asset_images"]["name"]);

        if ($file_count > 8) {

            $error = "You can upload a maximum of 8 images.";

        } else {

            $allowed_extensions = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];

            $allowed_mime_types = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            for ($i = 0; $i < $file_count; $i++) {

                if (
                    $_FILES["asset_images"]["error"][$i] ===
                    UPLOAD_ERR_NO_FILE
                ) {
                    continue;
                }

                if (
                    $_FILES["asset_images"]["error"][$i] !==
                    UPLOAD_ERR_OK
                ) {
                    $error = "One of the images could not be uploaded.";
                    break;
                }

                $tmp_name =
                    $_FILES["asset_images"]["tmp_name"][$i];

                $original_name =
                    $_FILES["asset_images"]["name"][$i];

                $file_size =
                    $_FILES["asset_images"]["size"][$i];

                /*
                | Maximum 5 MB per image
                */

                if ($file_size > 5 * 1024 * 1024) {

                    $error =
                        "Each image must be 5 MB or smaller.";

                    break;
                }

                /*
                | Check actual MIME type
                */

                $finfo = finfo_open(FILEINFO_MIME_TYPE);

                $mime_type =
                    finfo_file($finfo, $tmp_name);

                finfo_close($finfo);

                if (
                    !in_array(
                        $mime_type,
                        $allowed_mime_types,
                        true
                    )
                ) {

                    $error =
                        "Only JPG, JPEG, PNG, and WEBP images are allowed.";

                    break;
                }

                /*
                | Check extension
                */

                $extension =
                    strtolower(
                        pathinfo(
                            $original_name,
                            PATHINFO_EXTENSION
                        )
                    );

                if (
                    !in_array(
                        $extension,
                        $allowed_extensions,
                        true
                    )
                ) {

                    $error =
                        "Invalid image file type.";

                    break;
                }

                $uploaded_images[] = [
                    "tmp_name" => $tmp_name,
                    "original_name" => $original_name,
                    "extension" => $extension
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check Asset Code
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmt = $conn->prepare("
            SELECT id
            FROM assets
            WHERE asset_code = :asset_code
            LIMIT 1
        ");

        $stmt->execute([
            ":asset_code" => $asset_code
        ]);

        if ($stmt->fetch()) {

            $error = "Asset Code already exists.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check Serial Number
    |--------------------------------------------------------------------------
    */

    if (
        $error === "" &&
        $serial_number !== ""
    ) {

        $stmt = $conn->prepare("
            SELECT id
            FROM assets
            WHERE serial_number = :serial_number
            LIMIT 1
        ");

        $stmt->execute([
            ":serial_number" => $serial_number
        ]);

        if ($stmt->fetch()) {

            $error = "Serial Number already exists.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Asset
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {

            $conn->beginTransaction();

            $purchase_cost_value = null;

            if ($purchase_cost !== "") {
                $purchase_cost_value =
                    (float) $purchase_cost;
            }

            $serial_number_value = null;

            if ($serial_number !== "") {
                $serial_number_value =
                    $serial_number;
            }

            $purchase_number_value = null;

            if ($purchase_number !== "") {
                $purchase_number_value =
                    $purchase_number;
            }

            $purchase_date_value = null;

            if ($purchase_date !== "") {
                $purchase_date_value =
                    $purchase_date;
            }


            /*
            | Insert Asset
            */

            $stmt = $conn->prepare("
                INSERT INTO assets (
                    asset_code,
                    asset_name,
                    category_id,
                    asset_type,
                    serial_number,
                    asset_source,
                    condition_status,
                    legacy_reference,
                    date_added,
                    supplier_name,
                    purchase_number,
                    purchase_date,
                    purchase_cost,
                    status,
                    current_user_id,
                    description
                )
                VALUES (
                    :asset_code,
                    :asset_name,
                    :category_id,
                    :asset_type,
                    :serial_number,
                    :asset_source,
                    :condition_status,
                    :legacy_reference,
                    :date_added,
                    :supplier_name,
                    :purchase_number,
                    :purchase_date,
                    :purchase_cost,
                    :status,
                    NULL,
                    :description
                )
            ");

            $stmt->execute([
                ":asset_code" => $asset_code,
                ":asset_name" => $asset_name,
                ":category_id" => $category_id,
                ":asset_type" => $asset_type,
                ":serial_number" => $serial_number_value,
                ":asset_source" => $asset_source,
                ":condition_status" => $condition_status,
                ":legacy_reference" => $legacy_reference !== "" ? $legacy_reference : null,
                ":date_added" => $date_added !== "" ? $date_added : date("Y-m-d"),
                ":supplier_name" => $supplier_name,
                ":purchase_number" => $purchase_number_value,
                ":purchase_date" => $purchase_date_value,
                ":purchase_cost" => $purchase_cost_value,
                ":status" => $status,
                ":description" => $description
            ]);


            /*
            |--------------------------------------------------------------------------
            | Get New Asset ID
            |--------------------------------------------------------------------------
            */

            $asset_id = $conn->lastInsertId();

            $qr_token = bin2hex(random_bytes(24));

            $stmt = $conn->prepare("
                UPDATE assets
                SET qr_token = :qr_token, qr_enabled = 1
                WHERE id = :id
            ");
            $stmt->execute([
                ":qr_token" => $qr_token,
                ":id" => $asset_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Create Upload Folder
            |--------------------------------------------------------------------------
            */

            $upload_directory =
                dirname(__DIR__, 2) .
                "/uploads/assets";

            if (
                !is_dir($upload_directory) &&
                !mkdir(
                    $upload_directory,
                    0755,
                    true
                )
            ) {

                throw new Exception(
                    "Unable to create image upload directory."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Save Images
            |--------------------------------------------------------------------------
            */

            foreach (
                $uploaded_images as $image
            ) {

                $unique_filename =
                    uniqid(
                        "asset_" .
                        $asset_id .
                        "_",
                        true
                    ) .
                    "." .
                    $image["extension"];

                $destination =
                    $upload_directory .
                    "/" .
                    $unique_filename;


                if (
                    !move_uploaded_file(
                        $image["tmp_name"],
                        $destination
                    )
                ) {

                    throw new Exception(
                        "Failed to save one of the uploaded images."
                    );
                }


                $stmt = $conn->prepare("
                    INSERT INTO asset_images (
                        asset_id,
                        image_filename,
                        image_original_name
                    )
                    VALUES (
                        :asset_id,
                        :image_filename,
                        :image_original_name
                    )
                ");

                $stmt->execute([
                    ":asset_id" =>
                        $asset_id,

                    ":image_filename" =>
                        $unique_filename,

                    ":image_original_name" =>
                        $image["original_name"]
                ]);
            }


            $conn->commit();


            header(
                "Location: asset_view.php?id=" .
                $asset_id .
                "&success=added"
            );

            exit;

        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error =
                "Unable to save the asset. " .
                $e->getMessage();
        }
    }
}

?>

<div class="page-header">

    <div>
        <p>
            Register a new asset in the system.
        </p>
    </div>

    <div class="page-actions">

        <a
            href="assets.php"
            class="btn btn-secondary"
        >
            Back to Assets
        </a>

    </div>

</div>


<?php if ($error !== ""): ?>

    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<div class="form-container">
<div class="guide-help-box" style="margin-bottom:18px;">
    <h3>How to complete this form</h3>
    <p><strong>Required fields</strong> are marked with *. Asset codes are generated automatically for new assets; only legacy assets require an existing code. Select the correct Category and Asset Type. Record the current Condition and Source accurately. Use Legacy / Existing and the previous reference when importing older assets. Purchase fields are for procurement records; images document the physical asset. Status should normally start as Available unless the asset is legitimately being recorded as Maintenance or Retired.</p>
</div>



    <form
        method="POST"
        action=""
        enctype="multipart/form-data"
    >
            <?= csrf_field() ?>

        <div class="form-grid">


            <!-- Asset Code: only shown for legacy records -->

            <div class="form-group" id="legacy_asset_code_group" <?= (($_POST["asset_source"] ?? (($_GET["source"] ?? "") === "legacy" ? "Legacy / Existing" : "New Purchase")) === "Legacy / Existing") ? "" : "hidden" ?>>

                <label for="asset_code">
                    Asset Code *
                </label>
                <small class="secondary-text">Enter the existing organization property code for this legacy asset.</small>

                <input
                    type="text"
                    id="asset_code"
                    name="asset_code"
                    <?= (($_POST["asset_source"] ?? (($_GET["source"] ?? "") === "legacy" ? "Legacy / Existing" : "New Purchase")) === "Legacy / Existing") ? "required" : "disabled" ?>
                    maxlength="50"
                    value="<?= htmlspecialchars(
                        $_POST["asset_code"] ?? ""
                    ) ?>"
                    placeholder="Example: IT-0001"
                >

            </div>


            <!-- Asset Name -->

            <div class="form-group">

                <label for="asset_name">
                    Asset Name *
                </label>

                <input
                    type="text"
                    id="asset_name"
                    name="asset_name"
                    required
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        $_POST["asset_name"] ?? ""
                    ) ?>"
                    placeholder="Example: Dell Latitude 5440"
                >

            </div>


            <!-- Category -->

            <div class="form-group">

                <label for="category_id">
                    Category *
                </label>

                <select
                    id="category_id"
                    name="category_id"
                    data-searchable-select
                    data-search-placeholder="Search categories..."
                    required
                >

                    <option value="">
                        Select Category
                    </option>

                    <?php foreach (
                        $categories as $category
                    ): ?>

                        <option
                            value="<?= (int) $category["id"] ?>"
                            <?= (
                                isset(
                                    $_POST["category_id"]
                                ) &&
                                (int) $_POST[
                                    "category_id"
                                ] ===
                                (int) $category["id"]
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            <?= htmlspecialchars(
                                $category["name"]
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Asset Source -->

            <div class="form-group">

                <label for="asset_source">
                    Asset Source *
                </label>
                <small class="secondary-text">Choose where the asset came from. Use Legacy / Existing for assets already in the organization before ST.</small>

                <select
                    id="asset_source"
                    name="asset_source"
                    required
                >
                    <?php
                    $selected_source = $_POST["asset_source"]
                        ?? (($_GET["source"] ?? "") === "legacy"
                            ? "Legacy / Existing"
                            : "New Purchase");
                    ?>

                    <?php foreach ([
                        "New Purchase",
                        "Legacy / Existing",
                        "Donated",
                        "Transferred",
                        "Other"
                    ] as $source): ?>
                        <option
                            value="<?= htmlspecialchars($source) ?>"
                            <?= $selected_source === $source ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars($source) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>


            <!-- Condition -->

            <div class="form-group">

                <label for="condition_status">
                    Current Condition *
                </label>
                <small class="secondary-text">Record the physical condition when the asset is registered.</small>

                <select
                    id="condition_status"
                    name="condition_status"
                    required
                >
                    <?php foreach ([
                        "New",
                        "Good",
                        "Fair",
                        "Poor",
                        "Damaged"
                    ] as $condition): ?>
                        <option
                            value="<?= htmlspecialchars($condition) ?>"
                            <?= (($_POST["condition_status"] ?? "New") === $condition) ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars($condition) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>


            <!-- Legacy Reference -->

            <div class="form-group">

                <label for="legacy_reference">
                    Legacy / Previous Reference
                </label>

                <input
                    type="text"
                    id="legacy_reference"
                    name="legacy_reference"
                    maxlength="100"
                    value="<?= htmlspecialchars($_POST["legacy_reference"] ?? "") ?>"
                    placeholder="Old property / SAP reference (if available)"
                >

            </div>


            <!-- Date Added -->

            <div class="form-group">

                <label for="date_added">
                    Date Added to ST *
                </label>

                <input
                    type="date"
                    id="date_added"
                    name="date_added"
                    required
                    value="<?= htmlspecialchars($_POST["date_added"] ?? date("Y-m-d")) ?>"
                >

            </div>


            <!-- Asset Type -->

            <div class="form-group">

                <label for="asset_type">
                    Asset Type *
                </label>

                <input
                    type="text"
                    id="asset_type"
                    name="asset_type"
                    required
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        $_POST["asset_type"] ?? ""
                    ) ?>"
                    placeholder="Example: Laptop"
                >

            </div>

            <!-- Serial Number / Plate Number -->

            <div class="form-group">

                <label for="serial_number" id="serial_number_label">
                    Serial Number
                </label>

                <input
                type="text"
                id="serial_number"
                name="serial_number"
                maxlength="150"
                value="<?= htmlspecialchars(
                    $_POST["serial_number"] ?? ""
                ) ?>"
                placeholder="Example: ABC123456"

                >

            </div>



            <!-- Supplier -->

            <div class="form-group">

                <label for="supplier_name">
                    Supplier
                </label>

                <input
                    type="text"
                    id="supplier_name"
                    name="supplier_name"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        $_POST["supplier_name"] ?? ""
                    ) ?>"
                    placeholder="Supplier / Vendor"
                >

            </div>


            <!-- Purchase Number -->

            <div class="form-group">

                <label for="purchase_number">
                    Purchase / PO Number
                </label>

                <input
                    type="text"
                    id="purchase_number"
                    name="purchase_number"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        $_POST["purchase_number"] ?? ""
                    ) ?>"
                    placeholder="Example: PO-2026-001"
                >

            </div>


            <!-- Purchase Date -->

            <div class="form-group">

                <label for="purchase_date">
                    Purchase Date
                </label>

                <input
                    type="date"
                    id="purchase_date"
                    name="purchase_date"
                    value="<?= htmlspecialchars(
                        $_POST["purchase_date"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- Purchase Cost -->

            <div class="form-group">

                <label for="purchase_cost">
                    Purchase Cost
                </label>

                <input
                    type="number"
                    id="purchase_cost"
                    name="purchase_cost"
                    step="0.01"
                    min="0"
                    value="<?= htmlspecialchars(
                        $_POST["purchase_cost"] ?? ""
                    ) ?>"
                    placeholder="0.00"
                >

            </div>


            <!-- Status -->

            <div class="form-group">

                <label for="status">
                    Status
                </label>
                <small class="secondary-text">Normally leave new assets as Available. Use Maintenance or Retired only when appropriate.</small>

                <select
                    id="status"
                    name="status"
                >

                    <option
                        value="Available"
                        <?= (
                            (
                                $_POST["status"]
                                ?? "Available"
                            ) ===
                            "Available"
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        Available
                    </option>

                    <option
                        value="Maintenance"
                        <?= (
                            (
                                $_POST["status"]
                                ?? ""
                            ) ===
                            "Maintenance"
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        Maintenance
                    </option>

                    <option
                        value="Retired"
                        <?= (
                            (
                                $_POST["status"]
                                ?? ""
                            ) ===
                            "Retired"
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        Retired
                    </option>

                </select>

            </div>


            <!-- Images -->

            <div class="form-group form-group-full">

                <label>
                    Asset Images
                </label>

                <input
                    type="file"
                    id="asset_images"
                    name="asset_images[]"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    hidden
                >

                <div class="asset-file-control">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-file-input="asset_images"
                        data-file-status="asset_image_status"
                        aria-controls="asset_images"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="m21 15-5-5L5 21"></path>
                        </svg>
                        Choose Images
                    </button>
                    <small id="asset_image_status" class="secondary-text" role="status" aria-live="polite">
                        No images selected.
                    </small>
                </div>

                <small class="secondary-text">
                    You can select up to 8 images.
                    JPG, PNG, and WEBP only.
                    Maximum 5 MB per image.
                </small>

                <div class="asset-camera-control">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-camera-input="asset_camera_input"
                        data-camera-status="asset_camera_status"
                        aria-controls="asset_camera_input"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M14 5h-4l-2 2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2-2Z"></path>
                            <circle cx="12" cy="13" r="3.5"></circle>
                        </svg>
                        Take Photo
                    </button>
                    <input
                        type="file"
                        id="asset_camera_input"
                        name="asset_images[]"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                        hidden
                    >
                    <small id="asset_camera_status" class="secondary-text" role="status" aria-live="polite">
                        Use your phone's rear camera to add asset photos.
                    </small>
                </div>

            </div>


            <!-- Description -->

            <div class="form-group form-group-full">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    placeholder="Additional asset information..."
                ><?= htmlspecialchars(
                    $_POST["description"] ?? ""
                ) ?></textarea>

            </div>

        </div>


        <div class="form-actions">

            <a
                href="assets.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Asset
            </button>

        </div>

    </form>

</div>

<script>
(function () {
    const source = document.getElementById('asset_source');
    const group = document.getElementById('legacy_asset_code_group');
    const code = document.getElementById('asset_code');
    if (!source || !group || !code) return;
    function updateCodeField() {
        const legacy = source.value === 'Legacy / Existing';
        group.hidden = !legacy;
        code.disabled = !legacy;
        code.required = legacy;
        if (!legacy) code.value = '';
    }
    source.addEventListener('change', updateCodeField);
    updateCodeField();
})();
</script>

<?php

require_once "../../backend/includes/footer.php";

?>
