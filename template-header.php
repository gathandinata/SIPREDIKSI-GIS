<?php
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = $pageTitle ?? 'SIPREDIKSI GIS';
$pageSubtitle = $pageSubtitle ?? '';
$pageIcon = $pageIcon ?? '▦';
$namaAdminHeader = $_SESSION['nama_admin'] ?? 'Admin';
$roleAdminHeader = $roleAdminHeader ?? 'Administrator';

$avatarText = strtoupper(substr($namaAdminHeader ?: 'A', 0, 1));
?>

<header class="app-header">
    <div class="app-header-left">
        <button 
            class="sidebar-toggle app-sidebar-toggle" 
            id="sidebarToggle" 
            type="button" 
            aria-label="Buka atau tutup sidebar"
        >
            ☰
        </button>

        <div class="app-page-icon">
            <?= e($pageIcon); ?>
        </div>

        <div class="app-title-block">
            <h1><?= e($pageTitle); ?></h1>

            <?php if ($pageSubtitle !== ''): ?>
                <p><?= e($pageSubtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="app-header-right">
        <div class="app-date-box">
            <span>📅</span>
            <strong><?= date('d M Y'); ?></strong>
        </div>

        <div class="app-admin-box">
            <div class="app-admin-avatar">
                <?= e($avatarText); ?>
            </div>

            <div class="app-admin-info">
                <strong><?= e($namaAdminHeader); ?></strong>
                <span><?= e($roleAdminHeader); ?></span>
            </div>
        </div>
    </div>
</header>