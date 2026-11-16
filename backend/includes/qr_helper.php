<?php
if (!function_exists("asset_qr_url")) {
    /* Purpose: Build the public verification URL for an asset QR token. */
    function asset_qr_url(PDO $conn, string $token): string
    {
        return app_base_url($conn) . "/asset_scan.php?token=" . rawurlencode($token);
    }
}

if (!function_exists("asset_qr_image_url")) {
    /* Purpose: Build the QR image URL used by the asset print view. */
    function asset_qr_image_url(string $data, int $size = 320): string
    {
        $size = max(120, min(1200, $size));
        return "https://api.qrserver.com/v1/create-qr-code/?size=" . $size . "x" . $size . "&margin=10&data=" . rawurlencode($data);
    }
}
