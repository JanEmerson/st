<?php
/*
 * ST Asset Management System
 * File: frontend/pages/qr_scanner.php
 * Purpose: Localhost-only QR scanner. This page is intentionally the only
 *          application file changed for the QR scanner correction.
 * Debugging: Start here when QR scanning fails; confirm the browser is opened
 *            at http://localhost/st and that camera permission is allowed.
 */
$page_title = "Scan Asset QR";
require_once "../../backend/includes/header.php";

$scan_base_url = app_base_url($conn);

/* Phones cannot reach the PC's localhost. The app must be opened via the LAN IP
 * when users are scanning with a mobile device. */
$host = strtolower($_SERVER["HTTP_HOST"] ?? "");
$isLocalhostHost = in_array($host, ["localhost", "127.0.0.1", "[::1]", "::1"], true) || preg_match('/^localhost(:\d+)?$/', $host) === 1;

/* QR scanning is intentionally mobile-only. Keep the feature out of the
 * desktop navigation and also protect against a direct desktop URL visit. */
$userAgent = strtolower($_SERVER["HTTP_USER_AGENT"] ?? "");
$isMobileDevice = (bool) preg_match(
    '/android|iphone|ipod|ipad|iemobile|opera mini|mobile|webos|blackberry|windows phone/',
    $userAgent
);
?>

<?php if (!$isMobileDevice): ?>
    <div class="detail-card qr-desktop-hidden-message" role="status">
        <h2>QR Scanner</h2>
        <p class="secondary-text">The QR Scanner is available on mobile devices only.</p>
        <p class="secondary-text">Open ST on your mobile device to access the QR Scanner automatically.</p>
    </div>
<?php else: ?>

<div class="page-header">
    <div>
        <h2>Scan Asset QR Code</h2>
        <p>Use your mobile device camera to scan an ST asset QR label.</p>
    </div>
</div>

<?php if ($isLocalhostHost): ?>
    <div class="detail-card qr-desktop-hidden-message" role="status">
        <h3>Use the LAN IP on your phone</h3>
        <p class="secondary-text">Your phone cannot reach <strong>localhost</strong>. Open the app on your phone with the PC's LAN IP, such as <strong>http://192.168.1.25/st</strong>, then scan again.</p>
    </div>
<?php endif; ?>

<div class="detail-card qr-scanner-card">
    <div class="qr-scanner-wrap">
        <div class="qr-camera-placeholder" id="qrCameraPlaceholder">
            <div class="qr-camera-icon" aria-hidden="true">▣</div>
            <strong>Scan an ST Asset QR Code</strong>
            <span>Tap the button below to open your phone camera.</span>
        </div>

        <div id="qrScannerStatus" class="secondary-text" role="status" aria-live="polite">
            Ready to scan an ST asset QR code.
        </div>

        <video id="qrCameraVideo" playsinline muted autoplay hidden></video>

        <div class="qr-scanner-actions">
            <button id="scanQrCamera" type="button" class="btn btn-primary">Scan QR Code</button>
            <button id="scanLiveCamera" type="button" class="btn btn-secondary">Use Live Camera</button>
            <button id="qrImageButton" type="button" class="btn btn-secondary">Choose QR Photo</button>
        </div>

        <!-- capture=environment is intentional: on mobile this opens the rear camera
             without requiring getUserMedia/HTTPS. This is the reliable approach for
             an ST installation accessed over normal HTTP/LAN. -->
        <input id="qrCameraInput" type="file" accept="image/*" capture="environment" hidden>
        <input id="qrImageInput" type="file" accept="image/*" hidden>
    </div>

    <div class="qr-scanner-help">
        <h3>How to Scan</h3>
        <ol>
            <li>Tap <strong>Scan QR Code</strong>.</li>
            <li>Allow the phone camera if your browser asks.</li>
            <li>Point the rear camera at the ST asset QR label.</li>
            <li>Take the photo. ST will read the QR code automatically.</li>
            <li>The matching asset page will open.</li>
        </ol>
        <p class="secondary-text">
            ST keeps the current application address when opening the scanned asset,
            so the QR code works with the same host/IP you used to open ST on the phone.
        </p>
    </div>
</div>

<?php endif; ?>

<style>
.qr-desktop-hidden-message { max-width: 760px; margin: 40px auto; }
.qr-scanner-card { max-width: 760px; margin: 0 auto; }
.qr-scanner-wrap { display:flex; flex-direction:column; gap:14px; }
.qr-camera-placeholder {
    min-height:260px;
    border-radius:16px;
    border:2px dashed rgba(var(--color-primary-rgb),.35);
    background:rgba(var(--color-primary-rgb),.06);
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-align:center;
    padding:28px;
}
.qr-camera-icon { font-size:52px; line-height:1; opacity:.75; }
.qr-scanner-actions { display:flex; flex-wrap:wrap; gap:10px; }
.qr-scanner-help {
    margin-top:20px;
    padding:18px;
    border-radius:12px;
    border:1px solid rgba(57,65,73,.25);
}
.qr-scanner-help ol { margin:10px 0 12px 20px; padding:0; }
@media (max-width:640px) {
    .qr-scanner-actions { flex-direction:column; }
    .qr-scanner-actions .btn { width:100%; min-height:48px; }
    .qr-camera-placeholder { min-height:220px; }
}
</style>

<!-- The mobile scanner intentionally uses the phone's camera/file picker rather
     than getUserMedia. This works on ordinary HTTP/LAN installations where a
     mobile browser would reject direct camera APIs because HTTPS is unavailable. -->
<script src="/st/frontend/js/jsqr.min.js"></script>
<script>
(() => {
    "use strict";

    if (!window.jsQR) {
        const status = document.getElementById("qrScannerStatus");
        if (status) {
            status.textContent = "The QR scanner could not load. Please refresh the page and try again.";
            status.style.color = "#c0392b";
        }
        return;
    }

    const status = document.getElementById("qrScannerStatus");
    const cameraButton = document.getElementById("scanQrCamera");
    const liveCameraButton = document.getElementById("scanLiveCamera");
    const imageButton = document.getElementById("qrImageButton");
    const cameraInput = document.getElementById("qrCameraInput");
    const imageInput = document.getElementById("qrImageInput");
    const video = document.getElementById("qrCameraVideo");

    if (!status || !cameraButton || !cameraInput) return;

    const canvas = document.createElement("canvas");
    const context = canvas.getContext("2d", { willReadFrequently: true });
    let opening = false;
    let videoStream = null;
    let scanRaf = null;

    function stopVideoScan() {
        if (scanRaf) {
            cancelAnimationFrame(scanRaf);
            scanRaf = null;
        }

        if (videoStream) {
            videoStream.getTracks().forEach((track) => track.stop());
            videoStream = null;
        }

        if (video) {
            video.pause();
            video.srcObject = null;
            video.hidden = true;
        }
    }

    function setStatus(message, error = false) {
        status.textContent = message;
        status.style.color = error ? "#c0392b" : "";
    }

    function extractToken(value) {
        const rawValue = String(value || "").trim();
        if (!rawValue) {
            return "";
        }

        if (/^[a-fA-F0-9]{24,128}$/.test(rawValue)) {
            return rawValue;
        }

        try {
            const url = new URL(rawValue, window.location.href);
            const path = url.pathname.replace(/\\/g, "/");
            const token = url.searchParams.get("token");
            if (token) {
                return token.trim();
            }

            if (path.endsWith("/asset_scan.php") || path.endsWith("asset_scan.php")) {
                const directToken = rawValue.match(/[?&]token=([^&]+)/i);
                if (directToken && directToken[1]) {
                    return decodeURIComponent(directToken[1]).trim();
                }
            }

            return "";
        } catch (e) {
            return rawValue.match(/[?&]token=([^&]+)/i)
                ? decodeURIComponent(rawValue.match(/[?&]token=([^&]+)/i)[1]).trim()
                : "";
        }
    }

    function openAsset(value) {
        const token = extractToken(value);
        if (!token) {
            setStatus("This is not a valid ST asset QR code. Please scan an ST asset label.", true);
            return;
        }
        if (opening) return;
        opening = true;
        setStatus("QR detected. Opening asset…");

        // Never trust the host encoded in an old QR. Keep the same ST host that
        // the phone is currently using, whether it is localhost or a LAN IP.
        const target = new URL("/st/asset_scan.php", window.location.origin);
        target.searchParams.set("token", token);
        window.location.href = target.href;
    }

    function decodeQrFromCanvas() {
        if (!context || !video || video.videoWidth <= 0 || video.videoHeight <= 0) {
            return false;
        }

        const width = video.videoWidth;
        const height = video.videoHeight;
        canvas.width = width;
        canvas.height = height;
        context.drawImage(video, 0, 0, width, height);

        const imageData = context.getImageData(0, 0, width, height);
        const result = window.jsQR(imageData.data, width, height, { inversionAttempts: "attemptBoth" });

        if (result && result.data) {
            stopVideoScan();
            openAsset(result.data);
            return true;
        }

        return false;
    }

    function startLiveCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus("This browser does not support live camera access. Please choose a QR photo instead.", true);
            return;
        }

        const isLocalhost = ["localhost", "127.0.0.1", "::1"].includes(window.location.hostname);
        if (!window.isSecureContext && !isLocalhost) {
            setStatus("The live camera is only available on localhost or HTTPS. Please choose a photo from your phone camera instead.", true);
            return;
        }

        setStatus("Opening live camera…");

        navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: "environment" },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        }).then((stream) => {
            videoStream = stream;
            if (video) {
                video.srcObject = stream;
                video.playsInline = true;
                video.muted = true;
                video.hidden = false;
                video.play().catch(() => {});
            }

            function scanFrame() {
                if (opening) {
                    return;
                }

                if (video && !video.hidden && decodeQrFromCanvas()) {
                    return;
                }

                scanRaf = requestAnimationFrame(scanFrame);
            }

            scanRaf = requestAnimationFrame(scanFrame);
            setStatus("Point your camera at the ST asset QR code.");
        }).catch(() => {
            setStatus("Camera access was blocked. Please use the QR photo option instead.", true);
        });
    }

    async function decodeImage(file) {
        if (!file || !(file.type ? file.type.startsWith("image/") : /\.(png|jpe?g|gif|bmp|webp)$/i.test(file.name || ""))) {
            setStatus("Please take a photo of an ST QR code.", true);
            return;
        }

        setStatus("Reading QR code…");

        const objectUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = async () => {
            try {
                let result = null;

                // Use native BarcodeDetector where the phone browser supports it.
                if ("BarcodeDetector" in window) {
                    try {
                        const supported = await BarcodeDetector.getSupportedFormats();
                        if (supported.includes("qr_code")) {
                            const detector = new BarcodeDetector({ formats: ["qr_code"] });
                            const found = await detector.detect(image);
                            if (found.length && found[0].rawValue) {
                                result = { data: found[0].rawValue };
                            }
                        }
                    } catch (e) {
                        // Fall through to jsQR.
                    }
                }

                if (!result && typeof window.jsQR === "function") {
                    const max = 1800;
                    const scale = Math.min(1, max / Math.max(image.naturalWidth, image.naturalHeight));
                    const width = Math.max(1, Math.round(image.naturalWidth * scale));
                    const height = Math.max(1, Math.round(image.naturalHeight * scale));
                    canvas.width = width;
                    canvas.height = height;
                    context.drawImage(image, 0, 0, width, height);
                    result = window.jsQR(
                        context.getImageData(0, 0, width, height).data,
                        width,
                        height,
                        { inversionAttempts: "attemptBoth" }
                    );

                    // Retry with a center crop for photos where the QR occupies a
                    // small portion of the camera image.
                    if (!result) {
                        const size = Math.floor(Math.min(width, height) * .82);
                        const x = Math.floor((width - size) / 2);
                        const y = Math.floor((height - size) / 2);
                        canvas.width = size;
                        canvas.height = size;
                        context.drawImage(image, x / scale, y / scale, size / scale, size / scale, 0, 0, size, size);
                        result = window.jsQR(
                            context.getImageData(0, 0, size, size).data,
                            size,
                            size,
                            { inversionAttempts: "attemptBoth" }
                        );
                    }
                }

                if (result && result.data) {
                    openAsset(result.data);
                } else {
                    setStatus("QR code not detected. Take a clear photo with the whole QR square visible.", true);
                }
            } catch (error) {
                console.error(error);
                setStatus("The QR photo could not be read. Please try again with better lighting.", true);
            } finally {
                URL.revokeObjectURL(objectUrl);
            }
        };

        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            setStatus("The camera photo could not be opened.", true);
        };
        image.src = objectUrl;
    }

    cameraButton.addEventListener("click", () => {
        opening = false;
        stopVideoScan();
        cameraInput.value = "";
        cameraInput.click();
    });

    if (liveCameraButton) {
        liveCameraButton.addEventListener("click", () => {
            opening = false;
            stopVideoScan();
            startLiveCamera();
        });
    }

    imageButton.addEventListener("click", () => {
        opening = false;
        stopVideoScan();
        imageInput.value = "";
        imageInput.click();
    });

    cameraInput.addEventListener("change", () => decodeImage(cameraInput.files && cameraInput.files[0]));
    imageInput.addEventListener("change", () => decodeImage(imageInput.files && imageInput.files[0]));
    window.addEventListener("beforeunload", stopVideoScan);
})();
</script>

<?php require_once "../../backend/includes/footer.php"; ?>
