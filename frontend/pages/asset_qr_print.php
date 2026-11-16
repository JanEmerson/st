<?php
$page_title = "Print Asset QR Label";
require_once "../../backend/includes/header.php";
require_once "../../backend/config/database.php";
require_once "../../backend/includes/qr_helper.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id) {
    die("Invalid asset.");
}

$stmt = $conn->prepare("SELECT a.*, c.name AS category_name FROM assets a INNER JOIN categories c ON a.category_id = c.id WHERE a.id = :id LIMIT 1");
$stmt->execute([":id" => $id]);
$asset = $stmt->fetch();

if (!$asset || empty($asset["qr_token"])) {
    die("QR code is not available for this asset.");
}

$qr_link  = asset_qr_url($conn, $asset["qr_token"]);
// Request a large source image so the printed QR remains sharp when resized.
$qr_image = asset_qr_image_url($qr_link, 1200);
?>

<style>
/*
 * QR LABEL PRINT PREVIEW
 *
 * The previous version used a fixed 48mm QR inside a 70mm x 90mm page.
 * Once the logo/title/name were included, the total content could exceed
 * the physical page height and the browser clipped the QR during printing.
 */
.qr-print-shell {
    max-width: 760px;
    margin: 20px auto 40px;
    font-family: Arial, sans-serif;
}

.qr-print-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 12px 18px;
    margin: 0 auto 22px;
    padding: 14px 18px;
    border: 1px solid var(--color-border, #d3e2d8);
    border-radius: 10px;
    background: var(--color-surface, #fff);
}

.qr-print-controls label {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--color-text, #1f2937);
    font-size: 14px;
    font-weight: 600;
}

.qr-print-size {
    width: 180px;
    accent-color: var(--color-primary, #E0A800);
}

.qr-print-size-value {
    min-width: 45px;
    font-variant-numeric: tabular-nums;
}

.qr-print-hint {
    width: 100%;
    margin: 0;
    text-align: center;
    color: var(--color-text-muted, #68758a);
    font-size: 12px;
}

/* Physical sticker: 50mm x 50mm square. The browser print page is the sticker itself. */
@page {
    size: 50mm 50mm;
    margin: 0;
}

.qr-print-page {
    --qr-size: 32mm;
    width: 50mm;
    height: 50mm;
    min-height: 50mm;
    box-sizing: border-box;
    margin: 0 auto;
    padding: 1.5mm;
    overflow: hidden;
    text-align: center;
    font-family: Arial, sans-serif;
    color: #111827;
    background: #fff;
}

.qr-print-logo {
    display: block;
    max-width: 30mm;
    max-height: 6mm;
    width: auto;
    height: auto;
    object-fit: contain;
    margin: 0 auto 1.5mm;
}

.qr-print-page h1 {
    margin: 0 0 .7mm;
    font-size: 9px;
    line-height: 1.15;
}

.qr-print-page h2 {
    margin: 0 0 1mm;
    font-size: 6.5px;
    line-height: 1.1;
    letter-spacing: .04em;
}

.qr-print-code {
    display: block;
    width: var(--qr-size);
    height: var(--qr-size);
    max-width: 100%;
    margin: 0 auto;
    object-fit: contain;
    image-rendering: auto;
    background: #fff;
}

.qr-print-page .code {
    margin-top: .8mm;
    font-size: 7px;
    line-height: 1.15;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.qr-print-page .name {
    margin-top: .7mm;
    font-size: 6.5px;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.qr-print-actions {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 18px auto 0;
}

.qr-print-loading {
    display: none;
    margin-top: 8px;
    text-align: center;
    color: #68758a;
    font-size: 12px;
}

.qr-print-page.qr-not-ready .qr-print-code {
    visibility: hidden;
}

@media print {
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .qr-print-controls,
    .qr-print-actions,
    .qr-print-loading,
    .sidebar,
    .topbar {
        display: none !important;
    }

    .app,
    .main-area,
    .content {
        width: auto !important;
        min-height: 0 !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    .qr-print-shell {
        width: auto !important;
        max-width: none !important;
        margin: 0 !important;
    }

    .qr-print-page {
        width: 50mm !important;
        height: 50mm !important;
        min-height: 50mm !important;
        margin: 0 !important;
        padding: 1.5mm !important;
        overflow: visible !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .qr-print-page.qr-not-ready .qr-print-code {
        visibility: visible !important;
    }
}

@media (max-width: 600px) {
    .qr-print-shell {
        margin: 14px 10px 30px;
    }

    .qr-print-controls {
        align-items: stretch;
        flex-direction: column;
    }

    .qr-print-controls label {
        justify-content: space-between;
    }

    .qr-print-size {
        width: min(220px, 55vw);
    }
}
</style>

<div class="qr-print-shell">
    <div class="qr-print-controls" id="qrPrintControls">
        <label for="qrPrintSize">
            QR Code Size
            <input
                id="qrPrintSize"
                class="qr-print-size"
                type="range"
                min="22"
                max="40"
                step="1"
                value="32"
                aria-label="QR Code size in millimeters"
            >
            <span class="qr-print-size-value"><span id="qrPrintSizeValue">32</span> mm</span>
        </label>
        <p class="qr-print-hint">
            Resize the QR before printing. 32 mm is the default and is sized to fit the 50 × 50 mm sticker.
        </p>
    </div>

    <div class="qr-print-page qr-not-ready" id="qrPrintPage">
        <?php if ($app_logo !== ""): ?>
            <img
                class="qr-print-logo"
                src="<?= htmlspecialchars($app_logo) ?>"
                alt="<?= htmlspecialchars($app_name) ?> logo"
            >
        <?php endif; ?>

        <h1><?= htmlspecialchars($app_name) ?></h1>
        <h2>ASSET IDENTIFICATION</h2>

        <img
            id="qrPrintImage"
            class="qr-print-code"
            src="<?= htmlspecialchars($qr_image) ?>"
            alt="QR Code for <?= htmlspecialchars($asset["asset_code"]) ?>"
        >

        <div class="code"><?= htmlspecialchars($asset["asset_code"]) ?></div>
        <div class="name"><?= htmlspecialchars($asset["asset_name"]) ?></div>
    </div>

    <div class="qr-print-actions" id="qrPrintActions">
        <button class="btn btn-primary" id="qrPrintButton" type="button">Print Label</button>
        <button class="btn btn-secondary" type="button" onclick="window.close()">Close</button>
    </div>

    <div class="qr-print-loading" id="qrPrintLoading">Preparing QR code for printing...</div>
</div>

<script>
(function () {
    "use strict";

    const page = document.getElementById("qrPrintPage");
    const image = document.getElementById("qrPrintImage");
    const sizeInput = document.getElementById("qrPrintSize");
    const sizeValue = document.getElementById("qrPrintSizeValue");
    const printButton = document.getElementById("qrPrintButton");
    const loading = document.getElementById("qrPrintLoading");

    /* Purpose: Apply the selected QR print size. */

    function applySize() {
        const size = Number(sizeInput.value) || 32;
        page.style.setProperty("--qr-size", size + "mm");
        sizeValue.textContent = size;
    }

    sizeInput.addEventListener("input", applySize);
    applySize();

    /* Purpose: Mark the QR library as ready. */

    function qrReady() {
        page.classList.remove("qr-not-ready");
        loading.style.display = "none";
        printButton.disabled = false;
    }

    /* Purpose: Handle QR library initialization failure. */

    function qrFailed() {
        loading.textContent = "The QR image could not be loaded. Please refresh this page before printing.";
        loading.style.display = "block";
        printButton.disabled = true;
    }

    printButton.disabled = true;
    loading.style.display = "block";

    if (image.complete && image.naturalWidth > 0) {
        qrReady();
    } else {
        image.addEventListener("load", qrReady, { once: true });
        image.addEventListener("error", qrFailed, { once: true });
    }

    printButton.addEventListener("click", function () {
        if (!image.complete || image.naturalWidth === 0) {
            return;
        }

        // Give the browser one paint cycle after the selected size is applied
        // so the print preview receives the final dimensions and full QR image.
        loading.textContent = "Preparing QR code for printing...";
        loading.style.display = "block";
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                loading.style.display = "none";
                window.print();
            });
        });
    });
})();
</script>

<?php require_once "../../backend/includes/footer.php"; ?>
