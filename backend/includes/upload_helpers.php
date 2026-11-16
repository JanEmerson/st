<?php
if (!function_exists("ensure_directory")) {
    /* Purpose: Create a runtime directory when it does not exist. */
    function ensure_directory(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        return @mkdir($directory, 0755, true) || is_dir($directory);
    }
}

if (!function_exists("upload_image_file")) {
    /* Purpose: Validate and store an uploaded image file. */
    function upload_image_file(
        array $file,
        string $directory,
        string $prefix,
        int $maxBytes = 2097152
    ): array {
        if (!isset($file["error"]) || is_array($file["error"])) {
            return ["success" => false, "error" => "Invalid upload."];
        }

        if ($file["error"] === UPLOAD_ERR_NO_FILE) {
            return ["success" => false, "empty" => true, "error" => "No file selected."];
        }

        if ($file["error"] !== UPLOAD_ERR_OK) {
            return ["success" => false, "error" => "The image upload failed."];
        }

        if ((int)$file["size"] <= 0 || (int)$file["size"] > $maxBytes) {
            return ["success" => false, "error" => "Image exceeds the allowed upload size."];
        }

        if (!is_uploaded_file($file["tmp_name"])) {
            return ["success" => false, "error" => "Invalid uploaded file."];
        }

        $imageInfo = @getimagesize($file["tmp_name"]);

        if ($imageInfo === false) {
            return ["success" => false, "error" => "Please upload a valid image file."];
        }

        $mime = $imageInfo["mime"] ?? "";

        $allowed = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowed[$mime])) {
            return ["success" => false, "error" => "Only JPG, JPEG, PNG, and WEBP images are allowed."];
        }

        if (!ensure_directory($directory)) {
            return ["success" => false, "error" => "The image upload directory could not be created."];
        }

        try {
            $token = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            $token = uniqid("img", true);
        }

        $filename = $prefix . "_" . $token . "." . $allowed[$mime];
        $destination = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file["tmp_name"], $destination)) {
            return ["success" => false, "error" => "The image could not be saved."];
        }

        return [
            "success" => true,
            "filename" => $filename,
            "mime" => $mime
        ];
    }
}

if (!function_exists("delete_uploaded_file")) {
    /* Purpose: Safely delete a previously stored upload. */
    function delete_uploaded_file(string $directory, string $filename): void
    {
        if ($filename === "") {
            return;
        }

        $safeFilename = basename($filename);
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFilename;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
