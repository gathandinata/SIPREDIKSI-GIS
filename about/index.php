<?php
session_start();

require_once __DIR__ . "/../auth.php";

$active_menu = "about";

$nama_admin = $_SESSION['nama_admin'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | SIPREDIKSI GIS</title>

    <link rel="stylesheet" href="../style-sidebar.css">
    <link rel="stylesheet" href="../style-header.css">
    <link rel="stylesheet" href="style-about.css">
</head>
<body>

<?php include __DIR__ . "/../sidebar.php"; ?>

<main class="main-content">
    <header class="topbar">
        <div>
            <h1>About</h1>
            <p>Informasi pembuat dan tujuan pengembangan sistem</p>
        </div>

        <div class="topbar-right">
            <span class="date">📅 01 Jun 2026</span>
            <div class="admin-profile">
                <div class="avatar">A</div>
                <div>
                    <strong><?= htmlspecialchars($nama_admin); ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </div>
    </header>

    <section class="hero-card">
        <div>
            <h2>SIPREDIKSI GIS Sumba Timur</h2>
            <p>
                Sistem prediksi jumlah penduduk miskin berbasis web yang menggunakan
                algoritma regresi linier dan visualisasi Sistem Informasi Geografis.
            </p>
        </div>
        <div class="hero-badge">GIS</div>
    </section>

    <section class="about-grid">
        <div class="card profile-card">
            <div class="profile-avatar">G</div>
            <h3>Gathan Syahputra Dinata</h3>
            <p class="role">Pembuat Sistem</p>

            <div class="profile-info">
                <div>
                    <span>NIM</span>
                    <strong>2122081</strong>
                </div>
                <div>
                    <span>Program Studi</span>
                    <strong>Teknik Informatika</strong>
                </div>
                <div>
                    <span>Universitas</span>
                    <strong>Universitas Kristen Wira Wacana Sumba</strong>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Tentang Sistem</h3>
            <p>
                SIPREDIKSI GIS Sumba Timur dikembangkan untuk membantu pengelolaan
                data historis jumlah penduduk miskin per kecamatan, menjalankan proses
                prediksi menggunakan regresi linier, serta menampilkan hasil prediksi
                dalam bentuk angka, grafik, peta, dan laporan.
            </p>

            <div class="info-list">
                <div>
                    <span>Nama Sistem</span>
                    <strong>SIPREDIKSI GIS Sumba Timur</strong>
                </div>
                <div>
                    <span>Metode Prediksi</span>
                    <strong>Regresi Linier</strong>
                </div>
                <div>
                    <span>Basis Sistem</span>
                    <strong>Website dan Sistem Informasi Geografis</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="card wide-card">
        <h3>Tujuan Pengembangan Sistem</h3>

        <div class="purpose-grid">
            <div class="purpose-item">
                <div class="icon">📊</div>
                <div>
                    <h4>Mengelola Data Historis</h4>
                    <p>Menyimpan dan menampilkan data jumlah penduduk miskin berdasarkan kecamatan dan tahun.</p>
                </div>
            </div>

            <div class="purpose-item">
                <div class="icon">📈</div>
                <div>
                    <h4>Melakukan Prediksi</h4>
                    <p>Menggunakan data historis untuk menghasilkan estimasi jumlah penduduk miskin pada tahun tertentu.</p>
                </div>
            </div>

            <div class="purpose-item">
                <div class="icon">🗺️</div>
                <div>
                    <h4>Menampilkan Peta SIG</h4>
                    <p>Menyajikan hasil prediksi dalam bentuk visualisasi wilayah agar lebih mudah dipahami.</p>
                </div>
            </div>

            <div class="purpose-item">
                <div class="icon">📄</div>
                <div>
                    <h4>Menyediakan Laporan</h4>
                    <p>Mendukung penyajian informasi hasil prediksi dalam bentuk laporan sistem.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="card wide-card">
        <h3>Teknologi yang Digunakan</h3>

        <div class="tech-list">
            <span>PHP</span>
            <span>MySQL</span>
            <span>HTML</span>
            <span>CSS</span>
            <span>JavaScript</span>
            <span>Leaflet JS</span>
            <span>Regresi Linier</span>
        </div>
    </section>
</main>

</body>
</html>