<?php
require_once "../../backend/includes/auth_check.php";
require_once "../../backend/config/database.php";

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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: categories.php?error=invalid");
    exit;
}

require_csrf();

$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: categories.php?error=invalid");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        name
    FROM categories
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $id
]);

$category = $stmt->fetch();

if (!$category) {
    header("Location: categories.php?error=notfound");
    exit;
}


/*
|--------------------------------------------------------------------------
| Check if category is being used by assets
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM assets
    WHERE category_id = :category_id
");

$stmt->execute([
    ":category_id" => $id
]);

$asset_count = (int) $stmt->fetchColumn();

if ($asset_count > 0) {

    header(
        "Location: categories.php?error=inuse"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Delete category
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM categories
    WHERE id = :id
");

$stmt->execute([
    ":id" => $id
]);


/*
|--------------------------------------------------------------------------
| Redirect after successful deletion
|--------------------------------------------------------------------------
*/

header(
    "Location: categories.php?success=deleted"
);

exit;

?>
