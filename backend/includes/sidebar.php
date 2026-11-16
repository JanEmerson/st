<?php
$role = current_user_role();
$currentPage = basename($_SERVER["PHP_SELF"]);

$canImportExport = user_has_role($role, ["Administrator", "Asset Custodian"]);
$canTransactions = user_has_role($role, ["Administrator", "Asset Custodian"]);
$canClearance = user_has_role($role, ["Administrator", "HR / Clearance Officer"]);
$isAdmin = is_administrator_role($role);

/* QR Scanner is intentionally a mobile-only feature. Desktop users should not
 * see it in the navigation; direct desktop access is also blocked by the
 * scanner page itself. */
$userAgent = strtolower($_SERVER["HTTP_USER_AGENT"] ?? "");
$isMobileDevice = (bool) preg_match(
    '/android|iphone|ipod|ipad|iemobile|opera mini|mobile|webos|blackberry|windows phone/',
    $userAgent
);

$sectionPages = [
    "overview" => ["index.php"],
    "asset-management" => ["assets.php", "qr_scanner.php", "categories.php", "asset_import_export.php"],
    "transactions" => ["assignments.php", "transfers.php", "turnovers.php", "maintenance.php", "maintenance_add.php", "maintenance_edit.php", "maintenance_attachment.php", "history.php"],
    "clearance" => ["clearance.php"],
    "administration" => ["users.php", "settings.php"],
    "help-account" => ["user_guide.php", "profile.php"],
];

/* Purpose: Determine whether a sidebar section contains the active page. */

function sidebar_section_active(string $key, string $currentPage, array $sectionPages): bool
{
    return in_array($currentPage, $sectionPages[$key] ?? [], true);
}

?>

<aside class="sidebar" id="app-sidebar">

    <div class="sidebar-logo">
        <div class="logo-mark<?= $app_logo !== "" ? " logo-mark-image" : "" ?>" aria-hidden="true">
            <?php if ($app_logo !== ""): ?>
                <img src="<?= htmlspecialchars($app_logo) ?>" alt="">
            <?php else: ?>
                <span>ST</span>
            <?php endif; ?>
        </div>

        <div class="sidebar-brand-text">
            <div class="logo-title" title="<?= htmlspecialchars($app_name, ENT_QUOTES) ?>"><?= htmlspecialchars($app_name) ?></div>
            <div class="logo-subtitle">Asset Management</div>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">

        <section class="sidebar-group" data-sidebar-group="overview">
            <button
                type="button"
                class="sidebar-section-title"
                aria-expanded="<?= sidebar_section_active("overview", $currentPage, $sectionPages) ? "true" : "false" ?>"
                aria-controls="sidebar-group-overview"
            >
                <span>Overview</span>
                <span class="sidebar-section-chevron" aria-hidden="true">⌄</span>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-overview">
                <a href="/st/index.php" class="<?= $currentPage === "index.php" ? "active" : "" ?>">Dashboard</a>
            </div>
        </section>

        <section class="sidebar-group" data-sidebar-group="asset-management">
            <button
                type="button"
                class="sidebar-section-title"
                aria-expanded="<?= sidebar_section_active("asset-management", $currentPage, $sectionPages) ? "true" : "false" ?>"
                aria-controls="sidebar-group-asset-management"
            >
                <span>Asset Management</span>
                <span class="sidebar-section-chevron" aria-hidden="true">⌄</span>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-asset-management">
                <a href="/st/frontend/pages/assets.php" class="<?= $currentPage === "assets.php" ? "active" : "" ?>">Assets</a>
                <?php if ($isMobileDevice): ?>
                    <a href="/st/frontend/pages/qr_scanner.php" class="<?= $currentPage === "qr_scanner.php" ? "active" : "" ?>">QR Scanner</a>
                <?php endif; ?>
                <a href="/st/frontend/pages/categories.php" class="<?= $currentPage === "categories.php" ? "active" : "" ?>">Categories</a>

                <?php if ($canImportExport): ?>
                    <a href="/st/frontend/pages/asset_import_export.php" class="<?= $currentPage === "asset_import_export.php" ? "active" : "" ?>">Import / Export</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="sidebar-group" data-sidebar-group="transactions">
            <button
                type="button"
                class="sidebar-section-title"
                aria-expanded="<?= sidebar_section_active("transactions", $currentPage, $sectionPages) ? "true" : "false" ?>"
                aria-controls="sidebar-group-transactions"
            >
                <span>Transactions</span>
                <span class="sidebar-section-chevron" aria-hidden="true">⌄</span>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-transactions">
                <?php if ($canTransactions): ?>
                    <a href="/st/frontend/pages/assignments.php" class="<?= $currentPage === "assignments.php" ? "active" : "" ?>">Assignments</a>
                    <a href="/st/frontend/pages/transfers.php" class="<?= $currentPage === "transfers.php" ? "active" : "" ?>">Transfers</a>
                    <a href="/st/frontend/pages/turnovers.php" class="<?= $currentPage === "turnovers.php" ? "active" : "" ?>">Turnovers</a>
                    <a href="/st/frontend/pages/maintenance.php" class="<?= in_array($currentPage, ["maintenance.php", "maintenance_add.php", "maintenance_edit.php"], true) ? "active" : "" ?>">Maintenance</a>
                <?php endif; ?>
                <a href="/st/frontend/pages/history.php" class="<?= $currentPage === "history.php" ? "active" : "" ?>">History</a>
            </div>
        </section>

        <?php if ($canClearance): ?>
            <section class="sidebar-group" data-sidebar-group="clearance">
                <button
                    type="button"
                    class="sidebar-section-title"
                    aria-expanded="<?= sidebar_section_active("clearance", $currentPage, $sectionPages) ? "true" : "false" ?>"
                    aria-controls="sidebar-group-clearance"
                >
                    <span>Clearance</span>
                    <span class="sidebar-section-chevron" aria-hidden="true">⌄</span>
                </button>
                <div class="sidebar-group-items" id="sidebar-group-clearance">
                    <a href="/st/frontend/pages/clearance.php" class="<?= $currentPage === "clearance.php" ? "active" : "" ?>">Clearance</a>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <section class="sidebar-group" data-sidebar-group="administration">
                <button
                    type="button"
                    class="sidebar-section-title"
                    aria-expanded="<?= sidebar_section_active("administration", $currentPage, $sectionPages) ? "true" : "false" ?>"
                    aria-controls="sidebar-group-administration"
                >
                    <span>Administration</span>
                    <span class="sidebar-section-chevron" aria-hidden="true">⌄</span>
                </button>
                <div class="sidebar-group-items" id="sidebar-group-administration">
                    <a href="/st/frontend/pages/users.php" class="<?= $currentPage === "users.php" ? "active" : "" ?>">Users</a>
                    <a href="/st/frontend/pages/settings.php" class="<?= $currentPage === "settings.php" ? "active" : "" ?>">Settings</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="sidebar-group" data-sidebar-group="help-account">
            <button
                type="button"
                class="sidebar-section-title"
                aria-expanded="<?= sidebar_section_active("help-account", $currentPage, $sectionPages) ? "true" : "false" ?>"
                aria-controls="sidebar-group-help-account"
            >
                <span>Help &amp; Account</span>
                <span class="sidebar-section-chevron" aria-hidden="true">⌄</span>
            </button>
            <div class="sidebar-group-items" id="sidebar-group-help-account">
                <a href="/st/frontend/pages/user_guide.php" class="<?= $currentPage === "user_guide.php" ? "active" : "" ?>">User Guide</a>
                <a href="/st/frontend/pages/profile.php" class="<?= $currentPage === "profile.php" ? "active" : "" ?>">My Profile</a>
            </div>
        </section>

        <div class="sidebar-divider"></div>
        <a href="/st/backend/auth/logout.php" class="logout-link">Logout</a>

    </nav>

</aside>
