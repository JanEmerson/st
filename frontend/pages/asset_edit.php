<?php
$page_title = "Edit Asset";

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
| Get Existing Asset
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
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
        description,
        qr_token,
        qr_enabled
    FROM assets
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $id
]);

$asset = $stmt->fetch();

if (!$asset) {
    header("Location: assets.php?error=notfound");
    exit;
}

$stmt = $conn->prepare("
    SELECT id
    FROM maintenance_records
    WHERE asset_id = :asset_id
      AND status IN ('Open', 'In Progress', 'Waiting for Parts')
    LIMIT 1
");
$stmt->execute([
    ":asset_id" => $id
]);
$has_active_maintenance = (bool)$stmt->fetch();
$maintenance_locked_status = $asset["status"] === "Retired"
    ? "Retired"
    : "Maintenance";


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
}


/*
|--------------------------------------------------------------------------
| Image Delete
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_image"])
) {

    $image_id = filter_input(
        INPUT_POST,
        "image_id",
        FILTER_VALIDATE_INT
    );

    if ($image_id) {

        $stmt = $conn->prepare("
            SELECT
                id,
                image_filename
            FROM asset_images
            WHERE id = :image_id
            AND asset_id = :asset_id
            LIMIT 1
        ");

        $stmt->execute([
            ":image_id" => $image_id,
            ":asset_id" => $id
        ]);

        $image = $stmt->fetch();

        if ($image) {

            $file_path =
                dirname(__DIR__, 2) .
                "/uploads/assets/" .
                $image["image_filename"];

            if (is_file($file_path)) {
                unlink($file_path);
            }

            $stmt = $conn->prepare("
                DELETE FROM asset_images
                WHERE id = :image_id
                AND asset_id = :asset_id
            ");

            $stmt->execute([
                ":image_id" => $image_id,
                ":asset_id" => $id
            ]);
        }
    }

    header(
        "Location: asset_edit.php?id=" .
        $id .
        "&success=image_deleted"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Existing Images
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        image_filename,
        image_original_name
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

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !isset($_POST["delete_image"])
) {

    $asset_code = trim(
        $_POST["asset_code"] ?? ""
    );

    $asset_name = trim(
        $_POST["asset_name"] ?? ""
    );

    $category_id = filter_input(
        INPUT_POST,
        "category_id",
        FILTER_VALIDATE_INT
    );

    $asset_type = trim(
        $_POST["asset_type"] ?? ""
    );

    $serial_number = trim(
        $_POST["serial_number"] ?? ""
    );

    $supplier_name = trim(
        $_POST["supplier_name"] ?? ""
    );

    $asset_source = trim(
        $_POST["asset_source"] ?? ($asset["asset_source"] ?? "New Purchase")
    );

    $condition_status = trim(
        $_POST["condition_status"] ?? ($asset["condition_status"] ?? "New")
    );

    $legacy_reference = trim(
        $_POST["legacy_reference"] ?? ($asset["legacy_reference"] ?? "")
    );

    $date_added = trim(
        $_POST["date_added"] ?? ($asset["date_added"] ?? date("Y-m-d"))
    );

    $purchase_number = trim(
        $_POST["purchase_number"] ?? ""
    );

    $purchase_date = trim(
        $_POST["purchase_date"] ?? ""
    );

    $purchase_cost = trim(
        $_POST["purchase_cost"] ?? ""
    );

    $status = trim(
        $_POST["status"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );


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

        $error =
            "Please fill in all required fields.";

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

        $error =
            "Invalid asset status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Assigned Asset Status Protection
    |--------------------------------------------------------------------------
    */

    if (
        $error === "" &&
        $asset["current_user_id"] !== null
    ) {

        if ($status !== "Assigned") {

            $error =
                "This asset is currently assigned. " .
                "Its status must remain Assigned. " .
                "Use the Assignment, Transfer, or Turnover module " .
                "to change accountability.";
        }
    }

    if (
        $error === "" &&
        $has_active_maintenance &&
        $status !== $maintenance_locked_status
    ) {
        $error =
            "This asset has an active maintenance work order. " .
            "Its status cannot be changed until that work order is closed.";
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Available Asset From Being Marked Assigned
    |--------------------------------------------------------------------------
    */

    if (
        $error === "" &&
        $asset["current_user_id"] === null &&
        $status === "Assigned"
    ) {

        $error =
            "An unassigned asset cannot be manually set to Assigned. " .
            "Use the Assignment module instead.";
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
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_added)
    ) {
        $error = "Invalid date added.";
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
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            ":asset_code" => $asset_code,
            ":id" => $id
        ]);

        if ($stmt->fetch()) {

            $error =
                "Asset Code already exists.";
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
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            ":serial_number" => $serial_number,
            ":id" => $id
        ]);

        if ($stmt->fetch()) {

            $error =
                "Serial Number already exists.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate New Images
    |--------------------------------------------------------------------------
    */

    $new_images = [];

    if (
        $error === "" &&
        isset($_FILES["asset_images"]) &&
        is_array($_FILES["asset_images"]["name"])
    ) {

        $file_count =
            count($_FILES["asset_images"]["name"]);

        $valid_upload_count = 0;

        foreach (
            $_FILES["asset_images"]["error"]
            as $upload_error
        ) {

            if (
                $upload_error !==
                UPLOAD_ERR_NO_FILE
            ) {

                $valid_upload_count++;
            }
        }


        if (
            $image_count +
            $valid_upload_count > 8
        ) {

            $error =
                "An asset can have a maximum of 8 images. " .
                "This asset currently has " .
                $image_count .
                " image(s).";
        }


        if ($error === "") {

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


            for (
                $i = 0;
                $i < $file_count;
                $i++
            ) {

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

                    $error =
                        "One of the images could not be uploaded.";

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

                if (
                    $file_size >
                    5 * 1024 * 1024
                ) {

                    $error =
                        "Each image must be 5 MB or smaller.";

                    break;
                }


                /*
                | Check MIME type
                */

                $finfo =
                    finfo_open(
                        FILEINFO_MIME_TYPE
                    );

                $mime_type =
                    finfo_file(
                        $finfo,
                        $tmp_name
                    );

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
                | Check Extension
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


                $new_images[] = [
                    "tmp_name" =>
                        $tmp_name,

                    "original_name" =>
                        $original_name,

                    "extension" =>
                        $extension
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Asset
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {

            $conn->beginTransaction();

            $stmt = $conn->prepare("
                SELECT status, current_user_id
                FROM assets
                WHERE id = :id
                FOR UPDATE
            ");
            $stmt->execute([
                ":id" => $id
            ]);
            $locked_asset = $stmt->fetch();

            if (!$locked_asset) {
                throw new Exception("Asset no longer exists.");
            }

            $stmt = $conn->prepare("
                SELECT id
                FROM maintenance_records
                WHERE asset_id = :asset_id
                  AND status IN ('Open', 'In Progress', 'Waiting for Parts')
                LIMIT 1
            ");
            $stmt->execute([
                ":asset_id" => $id
            ]);
            $locked_has_active_maintenance = (bool)$stmt->fetch();

            if ($locked_has_active_maintenance) {
                $locked_status = $locked_asset["status"] === "Retired"
                    ? "Retired"
                    : "Maintenance";
                if ($status !== $locked_status) {
                    throw new Exception(
                        "This asset has an active maintenance work order. " .
                        "Its status cannot be changed until that work order is closed."
                    );
                }
                $status = $locked_status;
            }


            /*
            | Purchase Cost
            */

            $purchase_cost_value = null;

            if ($purchase_cost !== "") {

                $purchase_cost_value =
                    (float) $purchase_cost;
            }


            /*
            | Serial Number
            */

            $serial_number_value = null;

            if ($serial_number !== "") {

                $serial_number_value =
                    $serial_number;
            }


            /*
            | Purchase Number
            */

            $purchase_number_value = null;

            if ($purchase_number !== "") {

                $purchase_number_value =
                    $purchase_number;
            }


            /*
            | Purchase Date
            */

            $purchase_date_value = null;

            if ($purchase_date !== "") {

                $purchase_date_value =
                    $purchase_date;
            }


            /*
            | Update Asset
            */

            if (empty($asset["qr_token"])) {
                $asset["qr_token"] = bin2hex(random_bytes(24));
            }

            $stmt = $conn->prepare("
                UPDATE assets
                SET
                    asset_code = :asset_code,
                    asset_name = :asset_name,
                    category_id = :category_id,
                    asset_type = :asset_type,
                    serial_number = :serial_number,
                    asset_source = :asset_source,
                    condition_status = :condition_status,
                    legacy_reference = :legacy_reference,
                    date_added = :date_added,
                    supplier_name = :supplier_name,
                    purchase_number = :purchase_number,
                    purchase_date = :purchase_date,
                    purchase_cost = :purchase_cost,
                    status = :status,
                    description = :description,
                    qr_token = :qr_token,
                    qr_enabled = :qr_enabled
                WHERE id = :id
            ");


            $stmt->execute([
                ":asset_code" =>
                    $asset_code,

                ":asset_name" =>
                    $asset_name,

                ":category_id" =>
                    $category_id,

                ":asset_type" =>
                    $asset_type,

                ":serial_number" =>
                    $serial_number_value,

                ":asset_source" =>
                    $asset_source,

                ":condition_status" =>
                    $condition_status,

                ":legacy_reference" =>
                    $legacy_reference !== "" ? $legacy_reference : null,

                ":date_added" =>
                    $date_added,

                ":supplier_name" =>
                    $supplier_name,

                ":purchase_number" =>
                    $purchase_number_value,

                ":purchase_date" =>
                    $purchase_date_value,

                ":purchase_cost" =>
                    $purchase_cost_value,

                ":status" =>
                    $status,

                ":description" =>
                    $description,

                ":qr_token" => $asset["qr_token"],
                ":qr_enabled" => (int)($asset["qr_enabled"] ?? 1),

                ":id" =>
                    $id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Upload Directory
            |--------------------------------------------------------------------------
            */

            if (count($new_images) > 0) {

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
                | Save New Images
                |--------------------------------------------------------------------------
                */

                foreach (
                    $new_images as $image
                ) {

                    $unique_filename =
                        uniqid(
                            "asset_" .
                            $id .
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
                            $id,

                        ":image_filename" =>
                            $unique_filename,

                        ":image_original_name" =>
                            $image["original_name"]
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            header(
                "Location: asset_view.php?id=" .
                $id .
                "&success=updated"
            );

            exit;

        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error =
                "Unable to update the asset. " .
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Refresh Images
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        image_filename,
        image_original_name
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
| Values Shown In Form
|--------------------------------------------------------------------------
*/

$form_asset_code =
    $_POST["asset_code"]
    ?? $asset["asset_code"];

$form_asset_name =
    $_POST["asset_name"]
    ?? $asset["asset_name"];

$form_category_id =
    $_POST["category_id"]
    ?? $asset["category_id"];

$form_asset_type =
    $_POST["asset_type"]
    ?? $asset["asset_type"];

$form_serial_number =
    $_POST["serial_number"]
    ?? ($asset["serial_number"] ?? "");

$form_asset_source =
    $_POST["asset_source"]
    ?? ($asset["asset_source"] ?? "New Purchase");

$form_condition_status =
    $_POST["condition_status"]
    ?? ($asset["condition_status"] ?? "New");

$form_legacy_reference =
    $_POST["legacy_reference"]
    ?? ($asset["legacy_reference"] ?? "");

$form_date_added =
    $_POST["date_added"]
    ?? ($asset["date_added"] ?? date("Y-m-d"));

$form_supplier_name =
    $_POST["supplier_name"]
    ?? ($asset["supplier_name"] ?? "");

$form_purchase_number =
    $_POST["purchase_number"]
    ?? ($asset["purchase_number"] ?? "");

$form_purchase_date =
    $_POST["purchase_date"]
    ?? ($asset["purchase_date"] ?? "");

$form_purchase_cost =
    $_POST["purchase_cost"]
    ?? ($asset["purchase_cost"] ?? "");

$form_status =
    $_POST["status"]
    ?? $asset["status"];

$form_description =
    $_POST["description"]
    ?? ($asset["description"] ?? "");

?>

<div class="page-header">

    <div>

        <p>
            Update asset information.
        </p>

    </div>

    <div class="page-actions">

        <a
            href="asset_view.php?id=<?= (int) $asset["id"] ?>"
            class="btn btn-secondary"
        >
            Cancel
        </a>

    </div>

</div>


<?php if ($error !== ""): ?>

    <div class="alert alert-error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<?php if ($asset["current_user_id"] !== null): ?>

    <div class="alert alert-info">

        This asset is currently assigned.
        Its status is controlled by the
        Assignment, Transfer, and Turnover modules.

    </div>

<?php elseif ($has_active_maintenance): ?>

    <div class="alert alert-info">
        This asset has an active maintenance work order. Its status is locked
        to <?= htmlspecialchars($maintenance_locked_status) ?> until the order
        is completed or cancelled. Other asset details can still be edited.
    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| Existing Images
|--------------------------------------------------------------------------
|
| IMPORTANT:
| This section is outside the main edit form.
|
-->

<div class="form-container">

    <div class="form-group form-group-full">

        <label>
            Existing Asset Images
        </label>


        <?php if ($image_count > 0): ?>

            <div
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:15px;
                    margin-top:10px;
                "
            >

                <?php foreach (
                    $asset_images as $image
                ): ?>

                    <div
                        style="
                            width:150px;
                            border:1px solid #ddd;
                            border-radius:8px;
                            padding:8px;
                        "
                    >

                        <img
                            src="/st/uploads/assets/<?= htmlspecialchars(
                                $image["image_filename"]
                            ) ?>"
                            alt="<?= htmlspecialchars(
                                $image["image_original_name"]
                                ?? "Asset Image"
                            ) ?>"
                            style="
                                width:100%;
                                height:110px;
                                object-fit:cover;
                                border-radius:5px;
                                display:block;
                                margin-bottom:8px;
                            "
                        >


                        <!-- Delete Image Form -->

                        <form
                            method="POST"
                            action=""
                            onsubmit="
                                return confirm(
                                    'Delete this image?'
                                );
                            "
                        >
            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="image_id"
                                value="<?= (int) $image["id"] ?>"
                            >

                            <input
                                type="hidden"
                                name="delete_image"
                                value="1"
                            >

                            <button
                                type="submit"
                                class="btn btn-danger"
                                style="width:100%;"
                            >
                                Delete
                            </button>

                        </form>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="secondary-text">
                No images have been uploaded for this asset.
            </p>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| MAIN EDIT FORM
|--------------------------------------------------------------------------
-->

<div class="form-container">

    <form
        method="POST"
        action=""
        enctype="multipart/form-data"
    >
            <?= csrf_field() ?>

        <div class="form-grid">


            <!-- Asset Code -->

            <div class="form-group">

                <label for="asset_code">
                    Asset Code *
                </label>

                <input
                    type="text"
                    id="asset_code"
                    name="asset_code"
                    required
                    maxlength="50"
                    value="<?= htmlspecialchars(
                        $form_asset_code
                    ) ?>"
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
                        $form_asset_name
                    ) ?>"
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
                                (int) $form_category_id ===
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
                <label for="asset_source">Asset Source *</label>
                <select id="asset_source" name="asset_source" required>
                    <?php foreach ([
                        "New Purchase",
                        "Legacy / Existing",
                        "Donated",
                        "Transferred",
                        "Other"
                    ] as $source): ?>
                        <option
                            value="<?= htmlspecialchars($source) ?>"
                            <?= $form_asset_source === $source ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars($source) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="condition_status">Current Condition *</label>
                <select id="condition_status" name="condition_status" required>
                    <?php foreach ([
                        "New",
                        "Good",
                        "Fair",
                        "Poor",
                        "Damaged"
                    ] as $condition): ?>
                        <option
                            value="<?= htmlspecialchars($condition) ?>"
                            <?= $form_condition_status === $condition ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars($condition) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="legacy_reference">Legacy / Previous Reference</label>
                <input
                    type="text"
                    id="legacy_reference"
                    name="legacy_reference"
                    maxlength="100"
                    value="<?= htmlspecialchars($form_legacy_reference) ?>"
                    placeholder="Old property / SAP reference (if available)"
                >
            </div>

            <div class="form-group">
                <label for="date_added">Date Added to ST *</label>
                <input
                    type="date"
                    id="date_added"
                    name="date_added"
                    required
                    value="<?= htmlspecialchars($form_date_added) ?>"
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
                        $form_asset_type
                    ) ?>"
                >

            </div>


            <!-- Serial Number -->

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
                        $form_serial_number
                    ) ?>"
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
                        $form_supplier_name
                    ) ?>"
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
                        $form_purchase_number
                    ) ?>"
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
                        $form_purchase_date
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
                        $form_purchase_cost
                    ) ?>"
                >

            </div>


            <!-- Status -->

            <div class="form-group">

                <label for="status">
                    Status
                </label>


                <?php if (
                    $asset["current_user_id"] !== null
                ): ?>

                    <input
                        type="hidden"
                        name="status"
                        value="Assigned"
                    >

                    <input
                        type="text"
                        value="Assigned"
                        disabled
                    >

                    <small class="secondary-text">
                        Controlled by asset transactions.
                    </small>

                <?php elseif ($has_active_maintenance): ?>

                    <input
                        type="hidden"
                        name="status"
                        value="<?= htmlspecialchars($maintenance_locked_status) ?>"
                    >

                    <input
                        type="text"
                        value="<?= htmlspecialchars($maintenance_locked_status) ?>"
                        disabled
                    >

                    <small class="secondary-text">
                        Controlled by the active maintenance work order.
                    </small>

                <?php else: ?>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="Available"
                            <?= $form_status === "Available"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Available
                        </option>

                        <option
                            value="Maintenance"
                            <?= $form_status === "Maintenance"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Maintenance
                        </option>

                        <option
                            value="Retired"
                            <?= $form_status === "Retired"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Retired
                        </option>

                    </select>

                <?php endif; ?>

            </div>


            <!-- Add More Images -->

            <div class="form-group form-group-full">

                <label>
                    Add More Images
                </label>


                <?php if ($image_count < 8): ?>

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

                        <?= 8 - $image_count ?>

                        image slot(s) remaining.

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

                <?php else: ?>

                    <div class="alert alert-info">

                        This asset already has the maximum
                        of 8 images.

                        Delete an existing image if you
                        want to add a replacement.

                    </div>

                <?php endif; ?>

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
                    $form_description
                ) ?></textarea>

            </div>

        </div>


        <!-- Form Actions -->

        <div class="form-actions">

            <a
                href="asset_view.php?id=<?= (int) $asset["id"] ?>"
                class="btn btn-secondary"
            >
                Cancel
            </a>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Changes
            </button>

        </div>

    </form>

</div>


<?php

require_once "../../backend/includes/footer.php";

?>
