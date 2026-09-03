<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../koneksi.php";

/** @var mysqli $koneksi */

$active_menu = "dashboard";
$base_url = "../";
?>

<?php
require_once __DIR__ . "/../koneksi.php";

/** @var mysqli $koneksi */

$active_menu = "dashboard";
$base_url = "../";

$total_kecamatan = 0;
$total_data = 0;
$total_prediksi = 0;

/*
|--------------------------------------------------------------------------
| Variabel ringkasan prediksi berdasarkan tahun
|--------------------------------------------------------------------------
*/
$daftar_tahun_prediksi = [];
$selected_tahun_ringkasan = 0;

$total_ringkasan_tahun = 0;
$rata_prediksi = 0;
$rata_mae = 0;
$rata_rmse = 0;
$rata_r2 = 0;
$median_r2 = null;

$prediksi_tertinggi = "-";
$prediksi_terendah = "-";
$kenaikan_terbesar = "-";
$penurunan_terbesar = "-";

/*
|--------------------------------------------------------------------------
| Fungsi format angka dashboard
|--------------------------------------------------------------------------
*/
if (!function_exists('dashAngka')) {
    function dashAngka($value, $decimal = 0)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimal, ',', '.');
    }
}

/*
|--------------------------------------------------------------------------
| Total kecamatan
|--------------------------------------------------------------------------
*/
$queryKecamatan = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM kecamatan");
if ($queryKecamatan) {
    $row = mysqli_fetch_assoc($queryKecamatan);
    $total_kecamatan = $row["total"] ?? 0;
}

/*
|--------------------------------------------------------------------------
| Total data historis
|--------------------------------------------------------------------------
*/
$queryData = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM data_kemiskinan");
if ($queryData) {
    $row = mysqli_fetch_assoc($queryData);
    $total_data = $row["total"] ?? 0;
}

/*
|--------------------------------------------------------------------------
| Total seluruh hasil prediksi
|--------------------------------------------------------------------------
| Ini tetap menghitung seluruh hasil prediksi yang tersimpan.
|--------------------------------------------------------------------------
*/
$queryPrediksi = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM hasil_prediksi"
);

if ($queryPrediksi) {
    $row = mysqli_fetch_assoc($queryPrediksi);
    $total_prediksi = $row["total"] ?? 0;
}

/*
|--------------------------------------------------------------------------
| Ambil daftar tahun prediksi yang tersedia
|--------------------------------------------------------------------------
*/
$queryTahunPrediksi = mysqli_query(
    $koneksi,
    "SELECT DISTINCT tahun_prediksi 
     FROM hasil_prediksi 
     ORDER BY tahun_prediksi DESC"
);

if ($queryTahunPrediksi) {
    while ($row = mysqli_fetch_assoc($queryTahunPrediksi)) {
        $daftar_tahun_prediksi[] = (int) $row["tahun_prediksi"];
    }
}

/*
|--------------------------------------------------------------------------
| Tahun ringkasan yang dipilih
|--------------------------------------------------------------------------
| Jika user belum memilih tahun, default memakai tahun prediksi terbaru.
|--------------------------------------------------------------------------
*/
$request_tahun_ringkasan = isset($_GET["tahun_ringkasan"])
    ? (int) $_GET["tahun_ringkasan"]
    : 0;

if (
    $request_tahun_ringkasan > 0
    && in_array($request_tahun_ringkasan, $daftar_tahun_prediksi, true)
) {
    $selected_tahun_ringkasan = $request_tahun_ringkasan;
} elseif (count($daftar_tahun_prediksi) > 0) {
    $selected_tahun_ringkasan = $daftar_tahun_prediksi[0];
}

/*
|--------------------------------------------------------------------------
| Ringkasan statistik prediksi berdasarkan tahun terpilih
|--------------------------------------------------------------------------
*/
if ($selected_tahun_ringkasan > 0) {
    $stmtRingkasan = mysqli_prepare(
        $koneksi,
        "SELECT 
            COUNT(*) AS total,
            AVG(nilai_prediksi) AS rata_prediksi,
            AVG(mae) AS rata_mae,
            AVG(rmse) AS rata_rmse,
            AVG(r2) AS rata_r2
         FROM hasil_prediksi
         WHERE tahun_prediksi = ?"
    );

    mysqli_stmt_bind_param($stmtRingkasan, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtRingkasan);
    $resultRingkasan = mysqli_stmt_get_result($stmtRingkasan);

    if ($resultRingkasan) {
        $row = mysqli_fetch_assoc($resultRingkasan);

        $total_ringkasan_tahun = (int) ($row["total"] ?? 0);
        $rata_prediksi = (float) ($row["rata_prediksi"] ?? 0);
        $rata_mae = (float) ($row["rata_mae"] ?? 0);
        $rata_rmse = (float) ($row["rata_rmse"] ?? 0);
        $rata_r2 = (float) ($row["rata_r2"] ?? 0);
    }

    mysqli_stmt_close($stmtRingkasan);
}

/*
|--------------------------------------------------------------------------
| Median R² berdasarkan tahun terpilih
|--------------------------------------------------------------------------
*/
if ($selected_tahun_ringkasan > 0) {
    $r2Values = [];

    $stmtR2 = mysqli_prepare(
        $koneksi,
        "SELECT r2 
         FROM hasil_prediksi 
         WHERE tahun_prediksi = ?
           AND r2 IS NOT NULL
         ORDER BY r2 ASC"
    );

    mysqli_stmt_bind_param($stmtR2, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtR2);
    $resultR2 = mysqli_stmt_get_result($stmtR2);

    while ($row = mysqli_fetch_assoc($resultR2)) {
        $r2Values[] = (float) $row["r2"];
    }

    mysqli_stmt_close($stmtR2);

    $jumlahR2 = count($r2Values);

    if ($jumlahR2 > 0) {
        $tengah = (int) floor($jumlahR2 / 2);

        if ($jumlahR2 % 2 === 1) {
            $median_r2 = $r2Values[$tengah];
        } else {
            $median_r2 = ($r2Values[$tengah - 1] + $r2Values[$tengah]) / 2;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Prediksi tertinggi berdasarkan tahun terpilih
|--------------------------------------------------------------------------
*/
if ($selected_tahun_ringkasan > 0) {
    $stmtTertinggi = mysqli_prepare(
        $koneksi,
        "SELECT 
            k.nama_kecamatan, 
            h.nilai_prediksi
         FROM hasil_prediksi h
         INNER JOIN kecamatan k ON k.id_kecamatan = h.id_kecamatan
         WHERE h.tahun_prediksi = ?
         ORDER BY h.nilai_prediksi DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmtTertinggi, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtTertinggi);
    $resultTertinggi = mysqli_stmt_get_result($stmtTertinggi);

    if ($resultTertinggi && mysqli_num_rows($resultTertinggi) > 0) {
        $row = mysqli_fetch_assoc($resultTertinggi);

        $prediksi_tertinggi =
            $row["nama_kecamatan"]
            . " ("
            . dashAngka($row["nilai_prediksi"])
            . " jiwa)";
    }

    mysqli_stmt_close($stmtTertinggi);
}

/*
|--------------------------------------------------------------------------
| Prediksi terendah berdasarkan tahun terpilih
|--------------------------------------------------------------------------
*/
if ($selected_tahun_ringkasan > 0) {
    $stmtTerendah = mysqli_prepare(
        $koneksi,
        "SELECT 
            k.nama_kecamatan, 
            h.nilai_prediksi
         FROM hasil_prediksi h
         INNER JOIN kecamatan k ON k.id_kecamatan = h.id_kecamatan
         WHERE h.tahun_prediksi = ?
         ORDER BY h.nilai_prediksi ASC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmtTerendah, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtTerendah);
    $resultTerendah = mysqli_stmt_get_result($stmtTerendah);

    if ($resultTerendah && mysqli_num_rows($resultTerendah) > 0) {
        $row = mysqli_fetch_assoc($resultTerendah);

        $prediksi_terendah =
            $row["nama_kecamatan"]
            . " ("
            . dashAngka($row["nilai_prediksi"])
            . " jiwa)";
    }

    mysqli_stmt_close($stmtTerendah);
}

/*
|--------------------------------------------------------------------------
| Kenaikan terbesar berdasarkan tahun terpilih
|--------------------------------------------------------------------------
| Dibandingkan dengan data kemiskinan tahun sebelumnya.
| Contoh: prediksi 2026 dibandingkan dengan data aktual 2025.
|--------------------------------------------------------------------------
*/
if ($selected_tahun_ringkasan > 0) {
    $stmtNaik = mysqli_prepare(
        $koneksi,
        "SELECT 
            k.nama_kecamatan,
            h.nilai_prediksi,
            dk.jumlah_penduduk_miskin AS nilai_sebelumnya,
            (h.nilai_prediksi - dk.jumlah_penduduk_miskin) AS selisih,
            (
                (h.nilai_prediksi - dk.jumlah_penduduk_miskin)
                / NULLIF(dk.jumlah_penduduk_miskin, 0)
            ) * 100 AS persen
         FROM hasil_prediksi h
         INNER JOIN kecamatan k ON k.id_kecamatan = h.id_kecamatan
         INNER JOIN data_kemiskinan dk 
            ON dk.id_kecamatan = h.id_kecamatan
           AND dk.tahun = h.tahun_prediksi - 1
         WHERE h.tahun_prediksi = ?
           AND h.nilai_prediksi > dk.jumlah_penduduk_miskin
         ORDER BY selisih DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmtNaik, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtNaik);
    $resultNaik = mysqli_stmt_get_result($stmtNaik);

    if ($resultNaik && mysqli_num_rows($resultNaik) > 0) {
        $row = mysqli_fetch_assoc($resultNaik);

        $kenaikan_terbesar =
            $row["nama_kecamatan"]
            . " (+"
            . dashAngka($row["selisih"])
            . " jiwa / "
            . dashAngka($row["persen"], 2)
            . "%)";
    }

    mysqli_stmt_close($stmtNaik);
}

/*
|--------------------------------------------------------------------------
| Penurunan terbesar berdasarkan tahun terpilih
|--------------------------------------------------------------------------
| Dibandingkan dengan data kemiskinan tahun sebelumnya.
|--------------------------------------------------------------------------
*/
if ($selected_tahun_ringkasan > 0) {
    $stmtTurun = mysqli_prepare(
        $koneksi,
        "SELECT 
            k.nama_kecamatan,
            h.nilai_prediksi,
            dk.jumlah_penduduk_miskin AS nilai_sebelumnya,
            (h.nilai_prediksi - dk.jumlah_penduduk_miskin) AS selisih,
            (
                (h.nilai_prediksi - dk.jumlah_penduduk_miskin)
                / NULLIF(dk.jumlah_penduduk_miskin, 0)
            ) * 100 AS persen
         FROM hasil_prediksi h
         INNER JOIN kecamatan k ON k.id_kecamatan = h.id_kecamatan
         INNER JOIN data_kemiskinan dk 
            ON dk.id_kecamatan = h.id_kecamatan
           AND dk.tahun = h.tahun_prediksi - 1
         WHERE h.tahun_prediksi = ?
           AND h.nilai_prediksi < dk.jumlah_penduduk_miskin
         ORDER BY selisih ASC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmtTurun, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtTurun);
    $resultTurun = mysqli_stmt_get_result($stmtTurun);

    if ($resultTurun && mysqli_num_rows($resultTurun) > 0) {
        $row = mysqli_fetch_assoc($resultTurun);

        $penurunan_terbesar =
            $row["nama_kecamatan"]
            . " (-"
            . dashAngka(abs($row["selisih"]))
            . " jiwa / "
            . dashAngka(abs($row["persen"]), 2)
            . "%)";
    }

    mysqli_stmt_close($stmtTurun);
}

$queryKecamatan = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM kecamatan");
if ($queryKecamatan) {
    $row = mysqli_fetch_assoc($queryKecamatan);
    $total_kecamatan = $row["total"] ?? 0;
}

$queryData = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM data_kemiskinan");
if ($queryData) {
    $row = mysqli_fetch_assoc($queryData);
    $total_data = $row["total"] ?? 0;
}

$queryPrediksi = mysqli_query($koneksi, "SELECT COUNT(*) AS total, AVG(nilai_prediksi) AS rata FROM hasil_prediksi");
if ($queryPrediksi) {
    $row = mysqli_fetch_assoc($queryPrediksi);
    $total_prediksi = $row["total"] ?? 0;
    $rata_prediksi = $row["rata"] ?? 0;
}

/*
|--------------------------------------------------------------------------
| Prediksi tertinggi dan terendah berdasarkan tahun ringkasan
|--------------------------------------------------------------------------
| Jika tahun 2027 hanya memiliki 1 data, maka prediksi tertinggi dan
| prediksi terendah akan menampilkan kecamatan yang sama.
|--------------------------------------------------------------------------
*/

$prediksi_tertinggi = "-";
$prediksi_terendah = "-";

if ($selected_tahun_ringkasan > 0) {

    /*
    |--------------------------------------------------------------------------
    | Prediksi tertinggi berdasarkan tahun terpilih
    |--------------------------------------------------------------------------
    */
    $stmtTertinggi = mysqli_prepare(
        $koneksi,
        "SELECT 
            k.nama_kecamatan,
            h.nilai_prediksi
         FROM hasil_prediksi h
         INNER JOIN kecamatan k ON k.id_kecamatan = h.id_kecamatan
         WHERE h.tahun_prediksi = ?
         ORDER BY h.nilai_prediksi DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmtTertinggi, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtTertinggi);
    $resultTertinggi = mysqli_stmt_get_result($stmtTertinggi);

    if ($resultTertinggi && mysqli_num_rows($resultTertinggi) > 0) {
        $row = mysqli_fetch_assoc($resultTertinggi);

        $prediksi_tertinggi =
            $row["nama_kecamatan"]
            . " ("
            . number_format((float) $row["nilai_prediksi"], 0, ',', '.')
            . ")";
    }

    mysqli_stmt_close($stmtTertinggi);


    /*
    |--------------------------------------------------------------------------
    | Prediksi terendah berdasarkan tahun terpilih
    |--------------------------------------------------------------------------
    */
    $stmtTerendah = mysqli_prepare(
        $koneksi,
        "SELECT 
            k.nama_kecamatan,
            h.nilai_prediksi
         FROM hasil_prediksi h
         INNER JOIN kecamatan k ON k.id_kecamatan = h.id_kecamatan
         WHERE h.tahun_prediksi = ?
         ORDER BY h.nilai_prediksi ASC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmtTerendah, "i", $selected_tahun_ringkasan);
    mysqli_stmt_execute($stmtTerendah);
    $resultTerendah = mysqli_stmt_get_result($stmtTerendah);

    if ($resultTerendah && mysqli_num_rows($resultTerendah) > 0) {
        $row = mysqli_fetch_assoc($resultTerendah);

        $prediksi_terendah =
            $row["nama_kecamatan"]
            . " ("
            . number_format((float) $row["nilai_prediksi"], 0, ',', '.')
            . ")";
    }

    mysqli_stmt_close($stmtTerendah);
}

$queryTabel = mysqli_query($koneksi, "
    SELECT 
        k.nama_kecamatan,
        h.tahun_prediksi,
        h.nilai_prediksi,
        h.mae,
        h.rmse,
        h.r2
    FROM hasil_prediksi h
    INNER JOIN kecamatan k ON k.id_kecamatan = h.id_kecamatan
    ORDER BY h.tahun_prediksi DESC, h.nilai_prediksi DESC
    LIMIT 25
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SIPREDIKSI GIS</title>

    <link rel="stylesheet" href="../style-sidebar.css">
    <link rel="stylesheet" href="../style-header.css">
    <link rel="stylesheet" href="style-dashboard.css">
</head>
<body>

<?php include __DIR__ . "/../sidebar.php"; ?>

<main class="main-content">
    <?php
    $pageTitle = 'Dashboard';
    $pageSubtitle = 'Ringkasan data kemiskinan, prediksi, dan visualisasi sistem.';
    $pageIcon = '▦';
    require_once __DIR__ . '/../template-header.php';
    ?>

    <section class="dashboard-hero">
        <div>
            <h2>Sistem Prediksi Kemiskinan Berbasis GIS</h2>
            <p>
                Dashboard ini menampilkan ringkasan data kecamatan, data kemiskinan,
                hasil prediksi, serta arah navigasi menuju fitur utama sistem.
            </p>
        </div>

        <a href="../prediksi/index.php" class="hero-button">
            Jalankan Prediksi
        </a>
    </section>

<section class="summary-grid">
    <div class="summary-card blue">
        <div class="summary-icon">🗺️</div>
        <div>
            <p>Jumlah Kecamatan</p>
            <h3><?= number_format($total_kecamatan, 0, ',', '.'); ?></h3>
            <span>Total wilayah terdaftar</span>
        </div>
    </div>

    <div class="summary-card green">
        <div class="summary-icon">🧾</div>
        <div>
            <p>Total Data Historis</p>
            <h3><?= number_format($total_data, 0, ',', '.'); ?></h3>
            <span>Data kemiskinan tersimpan</span>
        </div>
    </div>

    <div class="summary-card orange">
        <div class="summary-icon">📈</div>
        <div>
            <p>Total Hasil Prediksi</p>
            <h3><?= number_format($total_prediksi, 0, ',', '.'); ?></h3>
            <span>Hasil prediksi tersimpan</span>
        </div>
    </div>
</section>

    <section class="content-grid">
        <div class="panel wide">
            <div class="panel-header">
                <div>
                    <h3>Hasil Prediksi Terbaru</h3>
                    <p>Daftar ringkas hasil prediksi yang sudah tersimpan</p>
                </div>
                <a href="../laporan/index.php">Lihat laporan</a>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kecamatan</th>
                            <th>Tahun Prediksi</th>
                            <th>Nilai Prediksi</th>
                            <th>MAE</th>
                            <th>RMSE</th>
                            <th>R²</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($queryTabel && mysqli_num_rows($queryTabel) > 0) : ?>
                            <?php $no = 1; ?>
                            <?php while ($data = mysqli_fetch_assoc($queryTabel)) : ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($data["nama_kecamatan"]); ?></td>
                                    <td><?= htmlspecialchars($data["tahun_prediksi"]); ?></td>
                                    <td><?= number_format($data["nilai_prediksi"], 0, ',', '.'); ?></td>
                                    <td><?= $data["mae"] !== null ? number_format($data["mae"], 2, ',', '.') : "-"; ?></td>
                                    <td><?= $data["rmse"] !== null ? number_format($data["rmse"], 2, ',', '.') : "-"; ?></td>
                                    <td><?= $data["r2"] !== null ? number_format($data["r2"], 3, ',', '.') : "-"; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7" class="empty-table">
                                    Belum ada hasil prediksi yang tersimpan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<div class="panel">
    <div class="panel-header simple">
        <h3>Ringkasan Prediksi</h3>
    </div>

<div class="panel">
    <div class="panel-header simple ringkasan-panel-header">
        <div>
            <h3>Ringkasan Prediksi</h3>
            <p>Ringkasan hasil prediksi berdasarkan tahun yang dipilih</p>
        </div>
    </div>

    <form method="GET" action="" class="summary-filter-form">
        <label for="tahun_ringkasan">Filter Tahun Prediksi</label>

        <select
            name="tahun_ringkasan"
            id="tahun_ringkasan"
            onchange="this.form.submit()"
            <?= count($daftar_tahun_prediksi) === 0 ? 'disabled' : ''; ?>
        >
            <?php if (count($daftar_tahun_prediksi) > 0): ?>
                <?php foreach ($daftar_tahun_prediksi as $tahun): ?>
                    <option
                        value="<?= (int) $tahun; ?>"
                        <?= (int) $tahun === (int) $selected_tahun_ringkasan ? 'selected' : ''; ?>
                    >
                        <?= (int) $tahun; ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="">Belum ada prediksi</option>
            <?php endif; ?>
        </select>
    </form>

    <div class="info-list">
        <div class="info-item highlight-blue">
            <span>Jumlah Kecamatan Diprediksi</span>
            <strong>
                <?= $selected_tahun_ringkasan > 0 ? dashAngka($total_ringkasan_tahun) . ' kecamatan' : '-'; ?>
            </strong>
        </div>

        <div class="info-item highlight-purple">
            <span>Rata-rata Prediksi <?= $selected_tahun_ringkasan > 0 ? (int) $selected_tahun_ringkasan : ''; ?></span>
            <strong>
                <?= $selected_tahun_ringkasan > 0 ? dashAngka($rata_prediksi) . ' jiwa' : '-'; ?>
            </strong>
        </div>

        <div class="info-item">
            <span>Prediksi Tertinggi</span>
            <strong><?= htmlspecialchars($prediksi_tertinggi); ?></strong>
        </div>

        <div class="info-item">
            <span>Prediksi Terendah</span>
            <strong><?= htmlspecialchars($prediksi_terendah); ?></strong>
        </div>

        <div class="info-item">
            <span>Rata-rata MAE</span>
            <strong>
                <?= $selected_tahun_ringkasan > 0 ? dashAngka($rata_mae, 2) . ' jiwa' : '-'; ?>
            </strong>
        </div>

        <div class="info-item">
            <span>Rata-rata RMSE</span>
            <strong>
                <?= $selected_tahun_ringkasan > 0 ? dashAngka($rata_rmse, 2) . ' jiwa' : '-'; ?>
            </strong>
        </div>

        <div class="info-item">
            <span>Rata-rata R²</span>
            <strong>
                <?= $selected_tahun_ringkasan > 0 ? dashAngka($rata_r2, 3) : '-'; ?>
            </strong>
        </div>

        <div class="info-item">
            <span>Median R²</span>
            <strong>
                <?= $median_r2 !== null ? dashAngka($median_r2, 3) : '-'; ?>
            </strong>
        </div>

        <div class="info-item highlight-green">
            <span>Kenaikan Terbesar</span>
            <strong><?= htmlspecialchars($kenaikan_terbesar); ?></strong>
        </div>

        <div class="info-item highlight-red">
            <span>Penurunan Terbesar</span>
            <strong><?= htmlspecialchars($penurunan_terbesar); ?></strong>
        </div>

        <div class="info-item">
            <span>Status Sistem</span>
            <strong class="status-good">Aktif</strong>
        </div>
    </div>
</div>
</div>
    </section>

    <section class="quick-menu">
        <a href="../data-kemiskinan/index.php" class="quick-card">
            <span>🧾</span>
            <div>
                <h4>Data Kemiskinan</h4>
                <p>Kelola data historis per kecamatan</p>
            </div>
        </a>

        <a href="../prediksi/index.php" class="quick-card">
            <span>📈</span>
            <div>
                <h4>Prediksi</h4>
                <p>Jalankan perhitungan regresi linier</p>
            </div>
        </a>

        <a href="../peta-sig/index.php" class="quick-card">
            <span>🗺️</span>
            <div>
                <h4>Peta SIG</h4>
                <p>Lihat visualisasi peta prediksi</p>
            </div>
        </a>

        <a href="../laporan/index.php" class="quick-card">
            <span>📄</span>
            <div>
                <h4>Laporan</h4>
                <p>Cetak atau unduh hasil prediksi</p>
            </div>
        </a>
    </section>
</main>

<script src="../script-sidebar.js"></script>
<script src="script-dashboard.js"></script>
</body>
</html>