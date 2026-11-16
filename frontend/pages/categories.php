<?php
$page_title = "Categories";

require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";

$success = $_GET["success"] ?? "";
$error = $_GET["error"] ?? "";

$stmt = $conn->query("
    SELECT
        c.id,
        c.name,
        c.description,
        c.created_at,
        COUNT(a.id) AS asset_count
    FROM categories c
    LEFT JOIN assets a
        ON a.category_id = c.id
    GROUP BY
        c.id,
        c.name,
        c.description,
        c.created_at
    ORDER BY c.name ASC
");

$categories = $stmt->fetchAll();

?>

<div class="page-header">

    <div>
        <p>
            Manage asset categories used by the system.
        </p>
    </div>

    <?php if (
        in_array(
            $_SESSION["role"],
            [
                "Administrator",
                "Asset Custodian"
            ]
        )
    ): ?>

        <a
            href="category_add.php"
            class="btn-primary"
        >
            + Add Category
        </a>

    <?php endif; ?>

</div>


<?php if ($success === "added"): ?>

    <div class="alert alert-success">
        Category added successfully.
    </div>

<?php elseif ($success === "updated"): ?>

    <div class="alert alert-success">
        Category updated successfully.
    </div>

<?php elseif ($success === "deleted"): ?>

    <div class="alert alert-success">
        Category deleted successfully.
    </div>

<?php endif; ?>


<?php if ($error === "used"): ?>

    <div class="alert alert-error">
        This category cannot be deleted because it is being used by one or more assets.
    </div>

<?php elseif ($error === "invalid"): ?>

    <div class="alert alert-error">
        Invalid category.
    </div>

<?php endif; ?>


<div class="table-container">

    <table class="primary-info-table categories-table">

        <thead>

            <tr>

                <th>ID</th>

                <th>Category</th>

                <th>Description</th>

                <th>Assets</th>

                <th>Created</th>

                <?php if (
                    in_array(
                        $_SESSION["role"],
                        [
                            "Administrator",
                            "Asset Custodian"
                        ]
                    )
                ): ?>

                    <th>Actions</th>

                <?php endif; ?>

            </tr>

        </thead>

        <tbody>

            <?php if (count($categories) > 0): ?>

                <?php foreach ($categories as $category): ?>

                    <tr>

                        <td>
                            <?= (int) $category["id"] ?>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $category["name"]
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $category["description"] ?? "-"
                            ) ?>
                        </td>

                        <td>
                            <?= (int) $category["asset_count"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                date(
                                    "M d, Y",
                                    strtotime(
                                        $category["created_at"]
                                    )
                                )
                            ) ?>
                        </td>

                        <?php if (
                            in_array(
                                $_SESSION["role"],
                                [
                                    "Administrator",
                                    "Asset Custodian"
                                ]
                            )
                        ): ?>

                            <td>

                                <a
                                    href="category_edit.php?id=<?= (int) $category["id"] ?>"
                                    class="btn-small"
                                >
                                    Edit
                                </a>

                                <form method="POST" action="category_delete.php" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $category["id"] ?>">
                                    <button type="submit" class="btn-small btn-danger" onclick="return confirm('Delete this category?');">
                                        Delete
                                    </button>
                                </form>

                            </td>

                        <?php endif; ?>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="empty-state"
                    >
                        No categories found.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<?php

require_once "../../backend/includes/footer.php";

?>
