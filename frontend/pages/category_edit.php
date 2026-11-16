<?php
$page_title = "Edit Category";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";


/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $_SESSION["role"],
        [
            "Administrator",
            "Asset Custodian"
        ]
    )
) {
    header("Location: categories.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Category ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: categories.php?error=invalid");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Category
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description
    FROM categories
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $id
]);

$category = $stmt->fetch();

if (!$category) {
    header("Location: categories.php?error=invalid");
    exit;
}


$error = "";


/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    require_csrf();

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");


    if ($name === "") {

        $error = "Category name is required.";

    } else {

        /*
        | Check duplicate category name
        */

        $check = $conn->prepare("
            SELECT id
            FROM categories
            WHERE name = :name
            AND id != :id
            LIMIT 1
        ");

        $check->execute([
            ":name" => $name,
            ":id" => $id
        ]);


        if ($check->fetch()) {

            $error = "Another category already uses this name.";

        } else {

            /*
            | Update category
            */

            $update = $conn->prepare("
                UPDATE categories
                SET
                    name = :name,
                    description = :description
                WHERE id = :id
            ");

            $update->execute([
                ":name" => $name,
                ":description" => $description !== ""
                    ? $description
                    : null,
                ":id" => $id
            ]);


            header(
                "Location: categories.php?success=updated"
            );

            exit;
        }
    }
}

?>


<div class="page-header">

    <div>


        <p>
            Update the category information.
        </p>

    </div>

</div>


<?php if ($error !== ""): ?>

    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<div class="form-card">

    <form method="POST">
            <?= csrf_field() ?>

        <div class="form-group">

            <label for="name">
                Category Name
            </label>

            <input
                type="text"
                id="name"
                name="name"
                value="<?= htmlspecialchars(
                    $_POST["name"] ?? $category["name"]
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="description">
                Description
            </label>

            <textarea
                id="description"
                name="description"
                rows="4"
            ><?= htmlspecialchars(
                $_POST["description"]
                ?? $category["description"]
                ?? ""
            ) ?></textarea>

        </div>


        <div class="form-actions">

            <a
                href="categories.php"
                class="btn-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn-primary"
            >
                Update Category
            </button>

        </div>

    </form>

</div>


<?php

require_once "../../backend/includes/footer.php";

?>