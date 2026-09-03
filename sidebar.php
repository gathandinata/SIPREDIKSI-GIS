<?php
if (!isset($active_menu)) {
    $active_menu = "";
}

if (!isset($base_url)) {
    $base_url = "/siprediksi-gis/";
}

$nama_admin = $_SESSION["nama_admin"] ?? "Admin";

function activeMenu($menu, $active_menu)
{
    return $menu === $active_menu ? "active" : "";
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <span>GIS</span>
        </div>

        <div class="brand-text">
            <h2>SIPREDIKSI GIS</h2>
            <p>SUMBA TIMUR</p>
        </div>
    </div>

    <div class="sidebar-line"></div>

    <nav class="sidebar-menu">
        <a href="<?= $base_url; ?>dashboard/index.php" class="menu-item <?= activeMenu("dashboard", $active_menu); ?>">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M3 10.5L12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5H15v-6H9v6H4.5A1.5 1.5 0 0 1 3 19.5v-9z"/>
                </svg>
            </span>
            <span>Dashboard</span>
        </a>

        <a href="<?= $base_url; ?>data-kemiskinan/index.php" class="menu-item <?= activeMenu("data-kemiskinan", $active_menu); ?>">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <ellipse cx="12" cy="5" rx="7" ry="3"/>
                    <path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5"/>
                    <path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>
                </svg>
            </span>
            <span>Data Kemiskinan</span>
        </a>

        <a href="<?= $base_url; ?>prediksi/index.php" class="menu-item <?= activeMenu("prediksi", $active_menu); ?>">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M4 19V5"/>
                    <path d="M4 19h16"/>
                    <path d="M7 15l4-4 3 3 5-7"/>
                    <path d="M17 7h2v2"/>
                </svg>
            </span>
            <span>Prediksi</span>
        </a>

        <a href="<?= $base_url; ?>peta-sig/index.php" class="menu-item <?= activeMenu("peta-sig", $active_menu); ?>">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"/>
                    <path d="M9 3v15"/>
                    <path d="M15 6v15"/>
                </svg>
            </span>
            <span>Peta SIG</span>
        </a>

        <a href="<?= $base_url; ?>laporan/index.php" class="menu-item <?= activeMenu("laporan", $active_menu); ?>">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M7 3h7l5 5v13H7z"/>
                    <path d="M14 3v5h5"/>
                    <path d="M9 13h6"/>
                    <path d="M9 17h6"/>
                </svg>
            </span>
            <span>Laporan</span>
        </a>
    </nav>

    <div class="sidebar-line bottom-line"></div>

    <div class="sidebar-footer">

        <a href="<?= $base_url; ?>about/index.php" class="menu-item <?= activeMenu("about", $active_menu); ?>">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M7 3h7l5 5v13H7z"/>
                    <path d="M14 3v5h5"/>
                    <path d="M9 13h6"/>
                    <path d="M9 17h6"/>
                </svg>
            </span>
            <span>About</span>
        </a>

        <a href="/siprediksi-gis/logout.php" class="menu-item logout-item">            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5"/>
                    <path d="M15 12H3"/>
                    <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                </svg>
            </span>
            <span>Logout</span>
        </a>

        <div class="copyright">
            <p>© 2024 SIPREDIKSI GIS</p>
            <p>Kab. Sumba Timur</p>
        </div>
    </div>
</aside>