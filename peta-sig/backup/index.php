<?php
/*
|--------------------------------------------------------------------------
| Memulai session hanya jika session belum aktif
|--------------------------------------------------------------------------
*/
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Memuat autentikasi dan koneksi database
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../koneksi.php';

/** @var mysqli $koneksi */

$active_menu = 'peta-sig';

/*
|--------------------------------------------------------------------------
| Mencegah browser menampilkan data peta lama dari cache
|--------------------------------------------------------------------------
| Setiap kali halaman dibuka ulang, PHP akan membaca data terbaru langsung
| dari tabel hasil_prediksi.
|--------------------------------------------------------------------------
*/
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

/*
|--------------------------------------------------------------------------
| Mengaktifkan exception pada error MySQL
|--------------------------------------------------------------------------
*/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Helper untuk mengamankan output HTML
|--------------------------------------------------------------------------
*/
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Helper untuk format angka desimal Indonesia
|--------------------------------------------------------------------------
*/
function angka($value, int $decimal = 0): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, $decimal, ',', '.');
}

/*
|--------------------------------------------------------------------------
| Helper untuk format angka bulat Indonesia
|--------------------------------------------------------------------------
*/
function angkaSingkat($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| Menentukan kategori dan warna berdasarkan nilai prediksi
|--------------------------------------------------------------------------
*/
function kategoriPrediksi($nilai): array
{
    if ($nilai === null || $nilai === '') {
        return [
            'label' => 'Tidak Ada Data',
            'class' => 'tidak-ada-data',
            'warna' => '#f3f4f6'
        ];
    }

    $nilai = (float) $nilai;

    if ($nilai > 5000) {
        return [
            'label' => 'Sangat Tinggi',
            'class' => 'sangat-tinggi',
            'warna' => '#d92d20'
        ];
    }

    if ($nilai >= 3500) {
        return [
            'label' => 'Tinggi',
            'class' => 'tinggi',
            'warna' => '#f97316'
        ];
    }

    if ($nilai >= 2000) {
        return [
            'label' => 'Sedang',
            'class' => 'sedang',
            'warna' => '#facc15'
        ];
    }

    if ($nilai >= 1000) {
        return [
            'label' => 'Rendah',
            'class' => 'rendah',
            'warna' => '#a3d977'
        ];
    }

    return [
        'label' => 'Sangat Rendah',
        'class' => 'sangat-rendah',
        'warna' => '#7cc66a'
    ];
}

/*
|--------------------------------------------------------------------------
| Memvalidasi data GeoJSON dari tabel kecamatan
|--------------------------------------------------------------------------
*/
function geojsonValid($value): ?array
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $decoded = json_decode((string) $value, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }

    return $decoded;
}

/*
|--------------------------------------------------------------------------
| Mengambil data penduduk terakhir yang tersedia
|--------------------------------------------------------------------------
| Data penduduk diambil maksimal sampai tahun prediksi yang dipilih.
|--------------------------------------------------------------------------
*/
function ambilDataPenduduk(
    mysqli $koneksi,
    int $idKecamatan,
    int $tahunReferensi
): ?array {
    $sql = "
        SELECT
            tahun,
            jumlah_penduduk_kecamatan,
            jumlah_penduduk_kabupaten,
            sumber_data
        FROM data_penduduk
        WHERE id_kecamatan = ?
          AND jenis_data = 'kecamatan'
          AND tahun <= ?
        ORDER BY tahun DESC
        LIMIT 1
    ";

    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $idKecamatan, $tahunReferensi);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    return [
        'tahun' => (int) $row['tahun'],
        'jumlah_penduduk_kecamatan' =>
            $row['jumlah_penduduk_kecamatan'] !== null
                ? (float) $row['jumlah_penduduk_kecamatan']
                : null,
        'jumlah_penduduk_kabupaten' =>
            $row['jumlah_penduduk_kabupaten'] !== null
                ? (float) $row['jumlah_penduduk_kabupaten']
                : null,
        'sumber_data' => $row['sumber_data'] ?? '-'
    ];
}

/*
|--------------------------------------------------------------------------
| Menentukan tahun prediksi yang ditampilkan
|--------------------------------------------------------------------------
| Mendukung parameter:
| - ?tahun=2025
| - ?tahun_prediksi=2025
|
| Jika parameter tidak diberikan, halaman menggunakan tahun dari hasil
| prediksi yang terakhir disimpan, bukan sekadar tahun terbesar.
|--------------------------------------------------------------------------
*/
$tahunPrediksi = 0;

if (isset($_GET['tahun'])) {
    $tahunPrediksi = (int) $_GET['tahun'];
} elseif (isset($_GET['tahun_prediksi'])) {
    $tahunPrediksi = (int) $_GET['tahun_prediksi'];
}

if ($tahunPrediksi <= 0) {
    $queryTahunTerbaru = mysqli_query(
        $koneksi,
        "
        SELECT tahun_prediksi
        FROM hasil_prediksi
        ORDER BY id_prediksi DESC
        LIMIT 1
        "
    );

    $rowTahunTerbaru = mysqli_fetch_assoc($queryTahunTerbaru);

    if (!empty($rowTahunTerbaru['tahun_prediksi'])) {
        $tahunPrediksi = (int) $rowTahunTerbaru['tahun_prediksi'];
    } else {
        $queryTahunHistoris = mysqli_query(
            $koneksi,
            "SELECT MAX(tahun) AS tahun_terakhir FROM data_kemiskinan"
        );

        $rowTahunHistoris = mysqli_fetch_assoc($queryTahunHistoris);

        $tahunPrediksi = !empty($rowTahunHistoris['tahun_terakhir'])
            ? ((int) $rowTahunHistoris['tahun_terakhir'] + 1)
            : (int) date('Y');
    }
}

$tahunSebelumnya = $tahunPrediksi - 1;

/*
|--------------------------------------------------------------------------
| Menyiapkan pilihan tahun filter
|--------------------------------------------------------------------------
*/
$tahunOptions = [];

/*
|--------------------------------------------------------------------------
| Tahun yang sudah benar-benar memiliki hasil prediksi
|--------------------------------------------------------------------------
*/
$queryTahunPrediksi = mysqli_query(
    $koneksi,
    "
    SELECT DISTINCT tahun_prediksi
    FROM hasil_prediksi
    ORDER BY tahun_prediksi DESC
    "
);

while ($rowTahun = mysqli_fetch_assoc($queryTahunPrediksi)) {
    if ($rowTahun['tahun_prediksi'] !== null) {
        $tahunOptions[] = (int) $rowTahun['tahun_prediksi'];
    }
}

/*
|--------------------------------------------------------------------------
| Menambahkan tahun historis terakhir sampai lima tahun berikutnya
|--------------------------------------------------------------------------
*/
$queryRentangData = mysqli_query(
    $koneksi,
    "
    SELECT MIN(tahun) AS tahun_awal, MAX(tahun) AS tahun_akhir
    FROM data_kemiskinan
    "
);

$rowRentangData = mysqli_fetch_assoc($queryRentangData);

if (!empty($rowRentangData['tahun_akhir'])) {
    $tahunAkhirData = (int) $rowRentangData['tahun_akhir'];

    for ($tahun = $tahunAkhirData; $tahun <= ($tahunAkhirData + 5); $tahun++) {
        $tahunOptions[] = $tahun;
    }
}

$tahunOptions[] = $tahunPrediksi;
$tahunOptions = array_values(array_unique(array_filter($tahunOptions)));
rsort($tahunOptions);

/*
|--------------------------------------------------------------------------
| Membaca kecamatan yang dipilih dari URL
|--------------------------------------------------------------------------
*/
$selectedKecamatan = isset($_GET['id_kecamatan'])
    ? max(0, (int) $_GET['id_kecamatan'])
    : 0;

/*
|--------------------------------------------------------------------------
| Menyiapkan variabel data peta dan ringkasan
|--------------------------------------------------------------------------
*/
$kecamatanOptions = [];
$mapData = [];
$totalPrediksiPeta = 0.0;
$jumlahDataPrediksi = 0;
$totalGeojsonTersedia = 0;
$idPrediksiTerbaru = 0;
$idKecamatanPrediksiTerbaru = 0;

/*
|--------------------------------------------------------------------------
| Mengambil seluruh kecamatan dan hasil prediksi dalam satu query
|--------------------------------------------------------------------------
| Perbaikan utama:
| - hasil_prediksi tahun aktif dibaca langsung melalui LEFT JOIN;
| - hasil tahun sebelumnya juga dibaca melalui LEFT JOIN;
| - data prediksi baru yang sudah tersimpan akan langsung ikut terbaca;
| - tidak ada perhitungan regresi ulang di halaman peta;
| - menghindari query hasil_prediksi berulang untuk setiap kecamatan.
|--------------------------------------------------------------------------
*/
$sqlPeta = "
    SELECT
        k.id_kecamatan,
        k.kode_kecamatan,
        k.nama_kecamatan,
        k.geojson_wilayah,

        hp.id_prediksi,
        hp.id_prediksi_regresi,
        hp.tahun_prediksi,
        hp.nilai_prediksi,
        hp.intercept,
        hp.slope,
        hp.mae,
        hp.rmse,
        hp.r2,

        hp_sebelumnya.id_prediksi AS id_prediksi_sebelumnya,
        hp_sebelumnya.nilai_prediksi AS nilai_prediksi_sebelumnya

    FROM kecamatan k

    LEFT JOIN hasil_prediksi hp
        ON hp.id_kecamatan = k.id_kecamatan
       AND hp.tahun_prediksi = ?

    LEFT JOIN hasil_prediksi hp_sebelumnya
        ON hp_sebelumnya.id_kecamatan = k.id_kecamatan
       AND hp_sebelumnya.tahun_prediksi = ?

    ORDER BY k.nama_kecamatan ASC
";

$stmtPeta = mysqli_prepare($koneksi, $sqlPeta);
mysqli_stmt_bind_param(
    $stmtPeta,
    'ii',
    $tahunPrediksi,
    $tahunSebelumnya
);
mysqli_stmt_execute($stmtPeta);

$resultPeta = mysqli_stmt_get_result($stmtPeta);

while ($row = mysqli_fetch_assoc($resultPeta)) {
    /*
    |--------------------------------------------------------------------------
    | Identitas kecamatan
    |--------------------------------------------------------------------------
    */
    $idKecamatan = (int) $row['id_kecamatan'];

    /*
    |--------------------------------------------------------------------------
    | Nilai prediksi tahun aktif
    |--------------------------------------------------------------------------
    | Variabel ditetapkan sebelum dipakai agar tidak muncul warning:
    | Undefined variable $nilaiPrediksi.
    |--------------------------------------------------------------------------
    */
    $nilaiPrediksi = $row['nilai_prediksi'] !== null
        ? (float) $row['nilai_prediksi']
        : null;

    /*
    |--------------------------------------------------------------------------
    | Nilai prediksi tahun sebelumnya
    |--------------------------------------------------------------------------
    */
    $nilaiPrediksiSebelumnya = $row['nilai_prediksi_sebelumnya'] !== null
        ? (float) $row['nilai_prediksi_sebelumnya']
        : null;

    /*
    |--------------------------------------------------------------------------
    | Menghitung perubahan terhadap tahun sebelumnya
    |--------------------------------------------------------------------------
    */
    $selisihPrediksi = null;
    $persenPerubahan = null;

    if (
        $nilaiPrediksi !== null
        && $nilaiPrediksiSebelumnya !== null
    ) {
        $selisihPrediksi =
            $nilaiPrediksi - $nilaiPrediksiSebelumnya;

        if ($nilaiPrediksiSebelumnya > 0) {
            $persenPerubahan = (
                $selisihPrediksi
                / $nilaiPrediksiSebelumnya
            ) * 100;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Mengambil data penduduk kecamatan
    |--------------------------------------------------------------------------
    */
    $penduduk = ambilDataPenduduk(
        $koneksi,
        $idKecamatan,
        $tahunPrediksi
    );

    $jumlahPendudukKecamatan =
        $penduduk['jumlah_penduduk_kecamatan'] ?? null;

    $jumlahPendudukKabupaten =
        $penduduk['jumlah_penduduk_kabupaten'] ?? null;

    $tahunDataPenduduk =
        $penduduk['tahun'] ?? null;

    $sumberDataPenduduk =
        $penduduk['sumber_data'] ?? '-';

    /*
    |--------------------------------------------------------------------------
    | Menghitung persentase prediksi terhadap jumlah penduduk
    |--------------------------------------------------------------------------
    */
    $persenTerhadapKecamatan = null;
    $persenTerhadapKabupaten = null;

    if (
        $nilaiPrediksi !== null
        && $jumlahPendudukKecamatan !== null
        && $jumlahPendudukKecamatan > 0
    ) {
        $persenTerhadapKecamatan = (
            $nilaiPrediksi
            / $jumlahPendudukKecamatan
        ) * 100;
    }

    if (
        $nilaiPrediksi !== null
        && $jumlahPendudukKabupaten !== null
        && $jumlahPendudukKabupaten > 0
    ) {
        $persenTerhadapKabupaten = (
            $nilaiPrediksi
            / $jumlahPendudukKabupaten
        ) * 100;
    }

    /*
    |--------------------------------------------------------------------------
    | Menentukan kategori dan GeoJSON
    |--------------------------------------------------------------------------
    */
    $kategori = kategoriPrediksi($nilaiPrediksi);
    $geojson = geojsonValid($row['geojson_wilayah'] ?? null);

    if ($geojson !== null) {
        $totalGeojsonTersedia++;
    }

    /*
    |--------------------------------------------------------------------------
    | Menyusun ringkasan hasil prediksi
    |--------------------------------------------------------------------------
    */
    if ($nilaiPrediksi !== null) {
        $totalPrediksiPeta += $nilaiPrediksi;
        $jumlahDataPrediksi++;

        $idPrediksi = (int) ($row['id_prediksi'] ?? 0);

        /*
        | Menandai hasil prediksi yang paling baru disimpan pada tahun aktif.
        */
        if ($idPrediksi > $idPrediksiTerbaru) {
            $idPrediksiTerbaru = $idPrediksi;
            $idKecamatanPrediksiTerbaru = $idKecamatan;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyusun satu item data peta
    |--------------------------------------------------------------------------
    */
    $item = [
        'id_kecamatan' => $idKecamatan,
        'kode_kecamatan' => $row['kode_kecamatan'],
        'nama_kecamatan' => $row['nama_kecamatan'],

        'id_prediksi' =>
            $row['id_prediksi'] !== null
                ? (int) $row['id_prediksi']
                : null,

        'id_prediksi_regresi' =>
            $row['id_prediksi_regresi'] !== null
                ? (int) $row['id_prediksi_regresi']
                : null,

        'tahun_prediksi' => $tahunPrediksi,
        'nilai_prediksi' => $nilaiPrediksi,

        'intercept' =>
            $row['intercept'] !== null
                ? (float) $row['intercept']
                : null,

        'slope' =>
            $row['slope'] !== null
                ? (float) $row['slope']
                : null,

        'mae' =>
            $row['mae'] !== null
                ? (float) $row['mae']
                : null,

        'rmse' =>
            $row['rmse'] !== null
                ? (float) $row['rmse']
                : null,

        'r2' =>
            $row['r2'] !== null
                ? (float) $row['r2']
                : null,

        'jumlah_data_latih' => null,
        'tahun_awal' => null,
        'tahun_akhir' => null,

        'sumber_prediksi' =>
            $nilaiPrediksi !== null
                ? 'Tabel hasil_prediksi'
                : 'Belum ada hasil prediksi tersimpan',

        'jumlah_penduduk_kecamatan' =>
            $jumlahPendudukKecamatan,

        'jumlah_penduduk_kabupaten' =>
            $jumlahPendudukKabupaten,

        'tahun_data_penduduk' =>
            $tahunDataPenduduk,

        'sumber_data_penduduk' =>
            $sumberDataPenduduk,

        'persen_terhadap_kecamatan' =>
            $persenTerhadapKecamatan,

        'persen_terhadap_kabupaten' =>
            $persenTerhadapKabupaten,

        'kategori' => $kategori['label'],
        'kategori_class' => $kategori['class'],
        'warna' => $kategori['warna'],
        'geojson' => $geojson,

        'tahun_sebelumnya' => $tahunSebelumnya,

        'nilai_prediksi_tahun_sebelumnya' =>
            $nilaiPrediksiSebelumnya,

        'selisih_prediksi' =>
            $selisihPrediksi,

        'persen_perubahan' =>
            $persenPerubahan
    ];

    $mapData[] = $item;

    $kecamatanOptions[] = [
        'id_kecamatan' => $idKecamatan,
        'nama_kecamatan' => $row['nama_kecamatan']
    ];
}

mysqli_stmt_close($stmtPeta);

/*
|--------------------------------------------------------------------------
| Menghitung ringkasan peta
|--------------------------------------------------------------------------
*/
$totalKecamatan = count($mapData);

$rataRataPrediksi = $jumlahDataPrediksi > 0
    ? ($totalPrediksiPeta / $jumlahDataPrediksi)
    : null;

$prediksiTertinggi = null;
$prediksiTerendah = null;

foreach ($mapData as $item) {
    if ($item['nilai_prediksi'] === null) {
        continue;
    }

    if (
        $prediksiTertinggi === null
        || $item['nilai_prediksi'] > $prediksiTertinggi['nilai_prediksi']
    ) {
        $prediksiTertinggi = $item;
    }

    if (
        $prediksiTerendah === null
        || $item['nilai_prediksi'] < $prediksiTerendah['nilai_prediksi']
    ) {
        $prediksiTerendah = $item;
    }
}

/*
|--------------------------------------------------------------------------
| Menentukan wilayah awal yang tampil pada panel kanan
|--------------------------------------------------------------------------
| Prioritas:
| 1. kecamatan dari parameter URL;
| 2. kecamatan yang hasil prediksinya paling baru disimpan;
| 3. kecamatan dengan prediksi tertinggi;
| 4. kecamatan pertama.
|--------------------------------------------------------------------------
*/
$wilayahTerpilih = null;

if ($selectedKecamatan > 0) {
    foreach ($mapData as $item) {
        if ((int) $item['id_kecamatan'] === $selectedKecamatan) {
            $wilayahTerpilih = $item;
            break;
        }
    }
}

if (
    $wilayahTerpilih === null
    && $idKecamatanPrediksiTerbaru > 0
) {
    foreach ($mapData as $item) {
        if (
            (int) $item['id_kecamatan']
            === $idKecamatanPrediksiTerbaru
        ) {
            $wilayahTerpilih = $item;
            break;
        }
    }
}

if (
    $wilayahTerpilih === null
    && $prediksiTertinggi !== null
) {
    $wilayahTerpilih = $prediksiTertinggi;
}

if (
    $wilayahTerpilih === null
    && count($mapData) > 0
) {
    $wilayahTerpilih = $mapData[0];
}

/*
|--------------------------------------------------------------------------
| Menyiapkan data panel kanan
|--------------------------------------------------------------------------
*/
$selectedKecamatanAktif =
    $wilayahTerpilih !== null
        ? (int) $wilayahTerpilih['id_kecamatan']
        : 0;

$prediksiTahunSebelumnya =
    $wilayahTerpilih['nilai_prediksi_tahun_sebelumnya'] ?? null;

$selisihPrediksiPanel =
    $wilayahTerpilih['selisih_prediksi'] ?? null;

$persenPerubahanPanel =
    $wilayahTerpilih['persen_perubahan'] ?? null;

$persenTerhadapKecamatanPanel =
    $wilayahTerpilih['persen_terhadap_kecamatan'] ?? null;

$persenTerhadapKabupatenPanel =
    $wilayahTerpilih['persen_terhadap_kabupaten'] ?? null;

/*
|--------------------------------------------------------------------------
| Mengubah seluruh data peta menjadi JSON
|--------------------------------------------------------------------------
*/
$mapDataJson = json_encode(
    $mapData,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_HEX_AMP
);

if ($mapDataJson === false) {
    $mapDataJson = '[]';
}

/*
|--------------------------------------------------------------------------
| Versi file CSS dan JavaScript untuk mencegah cache
|--------------------------------------------------------------------------
*/
$cssFile = __DIR__ . '/style-peta-sig.css';
$jsFile = __DIR__ . '/script-peta-sig.js';

$cssVersion = file_exists($cssFile)
    ? (string) filemtime($cssFile)
    : (string) time();

$jsVersion = file_exists($jsFile)
    ? (string) filemtime($jsFile)
    : (string) time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Peta SIG | SIPREDIKSI GIS Sumba Timur</title>

    <!-- Leaflet CSS -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <!-- CSS global aplikasi -->
    <link rel="stylesheet" href="../style-sidebar.css">
    <link rel="stylesheet" href="../style-header.css">

    <!-- CSS khusus Peta SIG dengan cache busting -->
    <link
        rel="stylesheet"
        href="style-peta-sig.css?v=<?= e($cssVersion); ?>"
    >
</head>
<body>

<?php include __DIR__ . '/../sidebar.php'; ?>

<main class="main-content">
    <?php
    $pageTitle = 'Peta SIG / Visualisasi Kemiskinan';
    $pageSubtitle = 'Menampilkan sebaran prediksi kemiskinan per kecamatan berbasis peta wilayah.';
    $pageIcon = '🗺';

    require_once __DIR__ . '/../template-header.php';
    ?>

    <!-- Informasi jika GeoJSON belum tersedia -->
    <?php if ($totalGeojsonTersedia === 0): ?>
        <div class="notice-card">
            Data GeoJSON wilayah belum tersedia pada tabel kecamatan.
            Import GeoJSON kecamatan Sumba Timur agar bentuk wilayah dapat
            ditampilkan pada peta.
        </div>

    <!-- Informasi jika tahun aktif belum memiliki hasil prediksi -->
    <?php elseif ($jumlahDataPrediksi === 0): ?>
        <div class="notice-card">
            Bentuk wilayah tersedia, tetapi hasil prediksi tahun
            <?= e($tahunPrediksi); ?> belum tersimpan pada tabel
            <strong>hasil_prediksi</strong>. Wilayah akan ditampilkan
            dengan warna abu-abu.
        </div>
    <?php endif; ?>

    <!-- Ringkasan peta -->
    <section class="map-summary-row">
        <div class="summary-card">
            <div class="summary-icon blue">🗺</div>

            <div class="summary-content">
                <span>Jumlah Kecamatan</span>

                <strong>
                    <?= angkaSingkat($totalKecamatan); ?>
                </strong>

                <small>
                    <?= angkaSingkat($totalGeojsonTersedia); ?>
                    wilayah memiliki GeoJSON
                </small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon red">↗</div>

            <div class="summary-content">
                <span>Prediksi Tertinggi</span>

                <strong>
                    <?= $prediksiTertinggi
                        ? angkaSingkat(
                            $prediksiTertinggi['nilai_prediksi']
                        )
                        : '-'; ?>

                    <em>Jiwa</em>
                </strong>

                <small>
                    <?= $prediksiTertinggi
                        ? 'Kec. '
                            . e($prediksiTertinggi['nama_kecamatan'])
                        : 'Belum ada data'; ?>
                </small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon green">↘</div>

            <div class="summary-content">
                <span>Prediksi Terendah</span>

                <strong>
                    <?= $prediksiTerendah
                        ? angkaSingkat(
                            $prediksiTerendah['nilai_prediksi']
                        )
                        : '-'; ?>

                    <em>Jiwa</em>
                </strong>

                <small>
                    <?= $prediksiTerendah
                        ? 'Kec. '
                            . e($prediksiTerendah['nama_kecamatan'])
                        : 'Belum ada data'; ?>
                </small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon yellow">▮</div>

            <div class="summary-content">
                <span>Rata-rata Prediksi</span>

                <strong>
                    <?= $rataRataPrediksi !== null
                        ? angkaSingkat($rataRataPrediksi)
                        : '-'; ?>

                    <em>Jiwa</em>
                </strong>

                <small>
                    <?= angkaSingkat($jumlahDataPrediksi); ?>
                    kecamatan memiliki prediksi
                </small>
            </div>
        </div>
    </section>

    <!-- Filter, peta, dan panel wilayah -->
    <section class="map-content-row">
        <aside class="map-left-column">
            <!-- Filter peta -->
            <div class="map-filter-card">
                <h3>Filter Peta</h3>

                <form
                    action="index.php"
                    method="GET"
                    class="map-filter-form"
                >
                    <div class="form-group">
                        <label for="tahun">Tahun</label>

                        <select
                            name="tahun"
                            id="tahun"
                            required
                        >
                            <?php foreach ($tahunOptions as $tahun): ?>
                                <option
                                    value="<?= e($tahun); ?>"
                                    <?= (int) $tahun === $tahunPrediksi
                                        ? 'selected'
                                        : ''; ?>
                                >
                                    <?= e($tahun); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_kecamatan">Kecamatan</label>

                        <select
                            name="id_kecamatan"
                            id="id_kecamatan"
                        >
                            <option
                                value="0"
                                <?= $selectedKecamatanAktif === 0
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Semua Kecamatan
                            </option>

                            <?php foreach ($kecamatanOptions as $kecamatan): ?>
                                <option
                                    value="<?= e(
                                        $kecamatan['id_kecamatan']
                                    ); ?>"
                                    <?= (int) $kecamatan['id_kecamatan']
                                        === $selectedKecamatanAktif
                                            ? 'selected'
                                            : ''; ?>
                                >
                                    <?= e(
                                        $kecamatan['nama_kecamatan']
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="btn-show-map"
                    >
                        <span>▽</span>
                        Tampilkan
                    </button>
                </form>
            </div>

            <!-- Legenda peta -->
            <div class="legend-card">
                <h3>Legenda</h3>

                <p>
                    Prediksi jumlah penduduk miskin dalam satuan jiwa.
                </p>

                <div class="legend-list">
                    <div class="legend-item">
                        <span class="legend-color sangat-tinggi"></span>
                        <strong>&gt; 5.000</strong>
                        <em>Sangat Tinggi</em>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color tinggi"></span>
                        <strong>3.500–5.000</strong>
                        <em>Tinggi</em>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color sedang"></span>
                        <strong>2.000–3.499</strong>
                        <em>Sedang</em>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color rendah"></span>
                        <strong>1.000–1.999</strong>
                        <em>Rendah</em>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color sangat-rendah"></span>
                        <strong>&lt; 1.000</strong>
                        <em>Sangat Rendah</em>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color tidak-ada-data"></span>
                        <strong>Tidak Ada Data</strong>
                        <em>Belum tersedia</em>
                    </div>
                </div>

                <div class="legend-source">
                    Sumber: tabel hasil_prediksi tahun
                    <?= e($tahunPrediksi); ?>.
                </div>
            </div>
        </aside>

        <!-- Peta Leaflet -->
        <section class="map-card">
            <div class="map-card-header">
                <h3>
                    Peta Prediksi Kemiskinan Kabupaten Sumba Timur
                </h3>

                <span class="map-status-chip">
                    <?= angkaSingkat($totalGeojsonTersedia); ?>
                    GeoJSON aktif
                </span>
            </div>

            <div class="leaflet-map-wrapper">
                <div id="mapSig"></div>

                <div
                    id="mapEmptyMessage"
                    class="map-empty-message"
                >
                    Data GeoJSON wilayah belum tersedia. Isi kolom
                    <strong>geojson_wilayah</strong> pada tabel kecamatan
                    agar bentuk kecamatan dapat tampil.
                </div>
            </div>
        </section>

        <!-- Panel ringkasan wilayah -->
        <aside class="selected-region-card">
            <h3>Ringkasan Wilayah Terpilih</h3>

            <div id="selectedRegionContent">
                <?php if ($wilayahTerpilih !== null): ?>
                    <h2>
                        <?= e($wilayahTerpilih['nama_kecamatan']); ?>
                    </h2>

                    <div class="region-detail-list">
                        <div class="region-detail">
                            <span>Tahun Prediksi</span>
                            <strong><?= e($tahunPrediksi); ?></strong>
                        </div>

                        <div class="region-detail">
                            <span>Prediksi Penduduk Miskin</span>

                            <strong class="text-red">
                                <?= $wilayahTerpilih['nilai_prediksi'] !== null
                                    ? angkaSingkat(
                                        $wilayahTerpilih['nilai_prediksi']
                                    ) . ' Jiwa'
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="region-detail">
                            <span>
                                Total Penduduk Kecamatan
                                <?= $wilayahTerpilih['tahun_data_penduduk']
                                    !== null
                                        ? '('
                                            . e(
                                                $wilayahTerpilih[
                                                    'tahun_data_penduduk'
                                                ]
                                            )
                                            . ')'
                                        : ''; ?>
                            </span>

                            <strong>
                                <?= $wilayahTerpilih[
                                    'jumlah_penduduk_kecamatan'
                                ] !== null
                                    ? angkaSingkat(
                                        $wilayahTerpilih[
                                            'jumlah_penduduk_kecamatan'
                                        ]
                                    ) . ' Jiwa'
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="region-detail">
                            <span>
                                Total Penduduk Kabupaten Sumba Timur
                            </span>

                            <strong>
                                <?= $wilayahTerpilih[
                                    'jumlah_penduduk_kabupaten'
                                ] !== null
                                    ? angkaSingkat(
                                        $wilayahTerpilih[
                                            'jumlah_penduduk_kabupaten'
                                        ]
                                    ) . ' Jiwa'
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="region-detail">
                            <span>
                                Persentase terhadap Penduduk Kecamatan
                            </span>

                            <strong>
                                <?= $persenTerhadapKecamatanPanel !== null
                                    ? angka(
                                        $persenTerhadapKecamatanPanel,
                                        2
                                    ) . '%'
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="region-detail">
                            <span>
                                Persentase terhadap Penduduk Kabupaten
                            </span>

                            <strong>
                                <?= $persenTerhadapKabupatenPanel !== null
                                    ? angka(
                                        $persenTerhadapKabupatenPanel,
                                        2
                                    ) . '%'
                                    : '-'; ?>
                            </strong>
                        </div>

                        <div class="region-detail">
                            <span>Kategori</span>

                            <strong>
                                <span
                                    class="category-badge <?= e(
                                        $wilayahTerpilih['kategori_class']
                                    ); ?>"
                                    style="white-space: nowrap;"
                                >
                                    <?= e(
                                        $wilayahTerpilih['kategori']
                                    ); ?>
                                </span>
                            </strong>
                        </div>
                    </div>

                    <div class="region-section">
                        <h4>
                            Perbandingan dengan Tahun Lalu
                            (<?= e($tahunSebelumnya); ?>)
                        </h4>

                        <div class="region-detail-list">
                            <div class="region-detail">
                                <span>
                                    Prediksi <?= e($tahunSebelumnya); ?>
                                </span>

                                <strong>
                                    <?= $prediksiTahunSebelumnya !== null
                                        ? angkaSingkat(
                                            $prediksiTahunSebelumnya
                                        ) . ' Jiwa'
                                        : '-'; ?>
                                </strong>
                            </div>

                            <div class="region-detail">
                                <span>Selisih</span>

                                <strong
                                    class="<?= $selisihPrediksiPanel !== null
                                        && $selisihPrediksiPanel >= 0
                                            ? 'text-red'
                                            : 'text-green'; ?>"
                                >
                                    <?= $selisihPrediksiPanel !== null
                                        ? (
                                            $selisihPrediksiPanel >= 0
                                                ? '+'
                                                : '-'
                                        )
                                        . angkaSingkat(
                                            abs($selisihPrediksiPanel)
                                        )
                                        . ' Jiwa'
                                        : '-'; ?>
                                </strong>
                            </div>

                            <div class="region-detail">
                                <span>Perubahan</span>

                                <strong
                                    class="<?= $persenPerubahanPanel !== null
                                        && $persenPerubahanPanel >= 0
                                            ? 'text-red'
                                            : 'text-green'; ?>"
                                >
                                    <?= $persenPerubahanPanel !== null
                                        ? (
                                            $persenPerubahanPanel >= 0
                                                ? '+'
                                                : '-'
                                        )
                                        . angka(
                                            abs($persenPerubahanPanel),
                                            2
                                        )
                                        . '%'
                                        : '-'; ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="region-section">
                        <h4>Informasi Tambahan</h4>

                        <div class="region-detail-list">
                            <div class="region-detail">
                                <span>Kode Kecamatan</span>

                                <strong>
                                    <?= e(
                                        $wilayahTerpilih[
                                            'kode_kecamatan'
                                        ] ?? '-'
                                    ); ?>
                                </strong>
                            </div>

                            <div class="region-detail">
                                <span>Status Data Peta</span>

                                <strong>
                                    <?= $wilayahTerpilih['geojson'] !== null
                                        ? 'GeoJSON tersedia'
                                        : 'GeoJSON belum tersedia'; ?>
                                </strong>
                            </div>

                            <div class="region-detail">
                                <span>Sumber Prediksi</span>

                                <strong>
                                    <?= e(
                                        $wilayahTerpilih[
                                            'sumber_prediksi'
                                        ] ?? '-'
                                    ); ?>
                                </strong>
                            </div>

                            <div class="region-detail">
                                <span>Tahun Data Penduduk</span>

                                <strong>
                                    <?= $wilayahTerpilih[
                                        'tahun_data_penduduk'
                                    ] !== null
                                        ? e(
                                            $wilayahTerpilih[
                                                'tahun_data_penduduk'
                                            ]
                                        )
                                        : '-'; ?>
                                </strong>
                            </div>

                            <div class="region-detail">
                                <span>Sumber Data Penduduk</span>

                                <strong>
                                    <?= e(
                                        $wilayahTerpilih[
                                            'sumber_data_penduduk'
                                        ] ?? '-'
                                    ); ?>
                                </strong>
                            </div>

                            <div class="region-detail">
                                <span>R² Model</span>

                                <strong>
                                    <?= $wilayahTerpilih['r2'] !== null
                                        ? angka(
                                            $wilayahTerpilih['r2'],
                                            3
                                        )
                                        : '-'; ?>
                                </strong>
                            </div>
                        </div>

                        <div class="region-status">
                            <div class="status-icon">
                                <?= $wilayahTerpilih['nilai_prediksi']
                                    !== null
                                        ? '✓'
                                        : '!'; ?>
                            </div>

                            <div>
                                <strong>
                                    <?php if (
                                        $wilayahTerpilih[
                                            'nilai_prediksi'
                                        ] === null
                                    ): ?>
                                        Hasil prediksi belum tersimpan
                                    <?php elseif (
                                        $wilayahTerpilih['r2'] !== null
                                        && $wilayahTerpilih['r2'] >= 0.8
                                    ): ?>
                                        Model memiliki performa sangat baik
                                    <?php else: ?>
                                        Evaluasi model perlu diperhatikan
                                    <?php endif; ?>
                                </strong>

                                <p>
                                    <?php if (
                                        $wilayahTerpilih[
                                            'nilai_prediksi'
                                        ] === null
                                    ): ?>
                                        Simpan hasil prediksi kecamatan ini
                                        melalui menu Prediksi agar datanya
                                        muncul pada peta.
                                    <?php elseif (
                                        $wilayahTerpilih['r2'] !== null
                                    ): ?>
                                        Nilai R² model sebesar
                                        <?= angka(
                                            $wilayahTerpilih['r2'],
                                            3
                                        ); ?>
                                        untuk tahun
                                        <?= e($tahunPrediksi); ?>.
                                    <?php else: ?>
                                        Nilai R² belum tersedia untuk
                                        wilayah ini.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-region">
                        Belum ada data wilayah yang dapat ditampilkan.
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </section>
</main>

<!-- Data peta dari PHP untuk JavaScript -->
<script type="application/json" id="mapDataJson"><?= $mapDataJson; ?></script>

<!-- Konfigurasi awal JavaScript peta -->
<div
    id="mapConfig"
    data-selected-kecamatan="<?= e($selectedKecamatanAktif); ?>"
    data-tahun-prediksi="<?= e($tahunPrediksi); ?>"
></div>

<!-- Leaflet JavaScript hanya dipanggil satu kali -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- JavaScript global sidebar -->
<script src="../script-sidebar.js"></script>

<!-- JavaScript khusus Peta SIG dengan cache busting -->
<script src="script-peta-sig.js?v=<?= e($jsVersion); ?>"></script>

</body>
</html>
