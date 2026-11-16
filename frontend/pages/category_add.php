<?php
$page_title = "Add Category";

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
        | Check for duplicate category
        */

        $check = $conn->prepare("
            SELECT id
            FROM categories
            WHERE name = :name
            LIMIT 1
        ");

        $check->execute([
            ":name" => $name
        ]);


        if ($check->fetch()) {

            $error = "A category with this name already exists.";

        } else {

            /*
            | Insert category
            */

            $stmt = $conn->prepare("
                INSERT INTO categories (
                    name,
                    description
                )
                VALUES (
                    :name,
                    :description
                )
            ");

            $stmt->execute([
                ":name" => $name,
                ":description" => $description !== ""
                    ? $description
                    : null
            ]);


            header("Location: categories.php?success=added");

            exit;
        }
    }
}

?>


<div class="page-header">
    <div>
        <p>Create a new asset category.</p>
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
                    $_POST["name"] ?? ""
                ) ?>"
                placeholder="Enter category name"
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
                placeholder="Enter category description"
            ><?= htmlspecialchars(
                $_POST["description"] ?? ""
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
                Save Category
            </button>
        </div>
    </form>
</div>


<?php
require_once "../../backend/includes/footer.php";
?>
