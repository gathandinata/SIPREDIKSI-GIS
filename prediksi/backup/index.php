<?php

/*
|--------------------------------------------------------------------------
| Memulai session
|--------------------------------------------------------------------------
| Session digunakan untuk membaca identitas admin, menyimpan pesan sementara,
| dan menyimpan token keamanan form.
|--------------------------------------------------------------------------
*/
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Memanggil proteksi halaman dan koneksi database
|--------------------------------------------------------------------------
| auth.php     : memastikan pengguna sudah login.
| koneksi.php  : menyediakan variabel $koneksi untuk akses database.
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../koneksi.php';

/** @var mysqli $koneksi */

/*
|--------------------------------------------------------------------------
| Menampilkan error database saat tahap pengembangan
|--------------------------------------------------------------------------
| Dengan MYSQLI_REPORT_STRICT, kesalahan query akan masuk ke blok catch.
| Pada sistem produksi, detail error tetap dicatat melalui error_log().
|--------------------------------------------------------------------------
*/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$active_menu = 'prediksi';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function angka($value, $decimal = 0)
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, $decimal, ',', '.');
}

function angkaSingkat($value)
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| Menyimpan pesan sementara ke dalam session
|--------------------------------------------------------------------------
| Pesan akan ditampilkan setelah proses penyimpanan selesai dan halaman
| diarahkan kembali menggunakan redirect.
|--------------------------------------------------------------------------
*/
function setPredictionFlash(string $type, string $message): void
{
    $_SESSION['prediction_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/*
|--------------------------------------------------------------------------
| Mengambil pesan sementara dari session
|--------------------------------------------------------------------------
| Setelah pesan diambil, data pesan langsung dihapus agar tidak muncul
| berulang kali ketika halaman di-refresh.
|--------------------------------------------------------------------------
*/
function getPredictionFlash(): ?array
{
    if (!isset($_SESSION['prediction_flash'])) {
        return null;
    }

    $flash = $_SESSION['prediction_flash'];
    unset($_SESSION['prediction_flash']);

    return $flash;
}

/*
|--------------------------------------------------------------------------
| Mengarahkan kembali ke halaman prediksi
|--------------------------------------------------------------------------
| Kecamatan dan tahun tetap dimasukkan dalam URL agar setelah penyimpanan,
| halaman kembali menampilkan hasil prediksi yang sama.
|--------------------------------------------------------------------------
*/
function redirectPrediction(int $idKecamatan, int $tahunPrediksi): void
{
    $query = http_build_query([
        'id_kecamatan' => $idKecamatan,
        'tahun_prediksi' => $tahunPrediksi
    ]);

    header('Location: index.php?' . $query);
    exit;
}

/*
|--------------------------------------------------------------------------
| Membuat token CSRF
|--------------------------------------------------------------------------
| Token ini melindungi form penyimpanan agar tidak dapat dikirim dari situs
| lain tanpa izin pengguna yang sedang login.
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Ambil daftar kecamatan
|--------------------------------------------------------------------------
*/
$kecamatanList = [];

$queryKecamatan = mysqli_query(
    $koneksi,
    "SELECT id_kecamatan, nama_kecamatan 
     FROM kecamatan 
     ORDER BY nama_kecamatan ASC"
);

if ($queryKecamatan) {
    while ($row = mysqli_fetch_assoc($queryKecamatan)) {
        $kecamatanList[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Ambil kecamatan terpilih
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Mendeteksi permintaan penyimpanan hasil prediksi
|--------------------------------------------------------------------------
| GET  digunakan untuk menampilkan atau menghitung preview prediksi.
| POST digunakan untuk menyimpan hasil prediksi ke database.
|--------------------------------------------------------------------------
*/
$isSaveRequest =
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save_prediction';

/*
|--------------------------------------------------------------------------
| Mengambil kecamatan dari GET atau POST
|--------------------------------------------------------------------------
| Saat preview, kecamatan dibaca dari URL.
| Saat menyimpan, kecamatan dibaca dari form POST.
|--------------------------------------------------------------------------
*/
$selectedKecamatan = $isSaveRequest
    ? (int) ($_POST['id_kecamatan'] ?? 0)
    : (int) ($_GET['id_kecamatan'] ?? 0);

if ($selectedKecamatan === 0 && count($kecamatanList) > 0) {
    $selectedKecamatan = (int) $kecamatanList[0]['id_kecamatan'];
}

if ($selectedKecamatan === 0 && count($kecamatanList) > 0) {
    $selectedKecamatan = (int) $kecamatanList[0]['id_kecamatan'];
}

/*
|--------------------------------------------------------------------------
| Ambil tahun maksimal dari data kemiskinan kecamatan terpilih
|--------------------------------------------------------------------------
*/
$maxTahun = null;

if ($selectedKecamatan > 0) {
    $stmtMax = mysqli_prepare(
        $koneksi,
        "SELECT MAX(tahun) AS max_tahun 
         FROM data_kemiskinan 
         WHERE id_kecamatan = ?"
    );

    mysqli_stmt_bind_param($stmtMax, 'i', $selectedKecamatan);
    mysqli_stmt_execute($stmtMax);
    $resultMax = mysqli_stmt_get_result($stmtMax);
    $rowMax = mysqli_fetch_assoc($resultMax);

    if (!empty($rowMax['max_tahun'])) {
        $maxTahun = (int) $rowMax['max_tahun'];
    }
}

/*
|--------------------------------------------------------------------------
| Tahun prediksi default:
| Jika ada data historis, default = tahun terakhir + 1.
| Jika belum ada data, default = tahun sekarang.
|--------------------------------------------------------------------------
*/
$tahunUji = 2025;

/*
|--------------------------------------------------------------------------
| Mengambil tahun prediksi dari GET atau POST
|--------------------------------------------------------------------------
| Ketika tombol simpan ditekan, tahun harus dibaca dari form POST agar
| backend menghitung dan menyimpan kombinasi kecamatan-tahun yang benar.
|--------------------------------------------------------------------------
*/
$requestTahun = $isSaveRequest
    ? (int) ($_POST['tahun_prediksi'] ?? 0)
    : (int) ($_GET['tahun_prediksi'] ?? 0);

$selectedTahun = $requestTahun > 0
    ? $requestTahun
    : (
        ($maxTahun && $maxTahun >= $tahunUji)
            ? $tahunUji
            : ($maxTahun ? $maxTahun + 1 : (int) date('Y'))
    );

/*
|--------------------------------------------------------------------------
| Ambil nama kecamatan terpilih
|--------------------------------------------------------------------------
*/
$namaKecamatan = '-';

foreach ($kecamatanList as $kecamatan) {
    if ((int) $kecamatan['id_kecamatan'] === $selectedKecamatan) {
        $namaKecamatan = $kecamatan['nama_kecamatan'];
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data historis sebelum tahun prediksi
|--------------------------------------------------------------------------
*/
$dataHistoris = [];

if ($selectedKecamatan > 0) {
    $stmtData = mysqli_prepare(
        $koneksi,
        "SELECT tahun, jumlah_penduduk_miskin 
         FROM data_kemiskinan 
         WHERE id_kecamatan = ?
           AND tahun < ?
         ORDER BY tahun ASC"
    );

    mysqli_stmt_bind_param($stmtData, 'ii', $selectedKecamatan, $selectedTahun);
    mysqli_stmt_execute($stmtData);
    $resultData = mysqli_stmt_get_result($stmtData);

    while ($row = mysqli_fetch_assoc($resultData)) {
        $dataHistoris[] = [
            'tahun' => (int) $row['tahun'],
            'jumlah' => (float) $row['jumlah_penduduk_miskin']
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Hitung regresi linier sederhana
| X = urutan tahun
| Y = jumlah penduduk miskin
|--------------------------------------------------------------------------
*/
$n = count($dataHistoris);

$intercept = null;
$slope = null;
$nilaiPrediksi = null;
$mae = null;
$rmse = null;
$r2 = null;
$delta = null;
$deltaPersen = null;
$tahunAwal = null;
$tahunAkhir = null;
$regressionMessage = null;

if ($n >= 2) {
    $sumX = 0;
    $sumY = 0;
    $sumX2 = 0;
    $sumXY = 0;

    foreach ($dataHistoris as $index => $data) {
        $x = $index + 1;
        $y = $data['jumlah'];

        $sumX += $x;
        $sumY += $y;
        $sumX2 += $x * $x;
        $sumXY += $x * $y;
    }

    $denominator = ($n * $sumX2) - ($sumX * $sumX);

    if ($denominator != 0) {
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        $tahunAwal = $dataHistoris[0]['tahun'];
        $tahunAkhir = $dataHistoris[$n - 1]['tahun'];

        /*
        |--------------------------------------------------------------------------
        | X prediksi:
        | Jika tahun awal 2015, maka:
        | 2015 = 1
        | 2016 = 2
        | 2025 = 11
        |--------------------------------------------------------------------------
        */
        $xPrediksi = ($selectedTahun - $tahunAwal) + 1;
        $nilaiPrediksi = $intercept + ($slope * $xPrediksi);

        /*
        |--------------------------------------------------------------------------
        | Evaluasi model pada data historis
        |--------------------------------------------------------------------------
        */
        $sumAbsoluteError = 0;
        $sumSquaredError = 0;
        $meanY = $sumY / $n;
        $sst = 0;

        foreach ($dataHistoris as $index => $data) {
            $x = $index + 1;
            $y = $data['jumlah'];
            $yPred = $intercept + ($slope * $x);

            $error = $y - $yPred;

            $sumAbsoluteError += abs($error);
            $sumSquaredError += pow($error, 2);
            $sst += pow($y - $meanY, 2);
        }

        $mae = $sumAbsoluteError / $n;
        $rmse = sqrt($sumSquaredError / $n);
        $r2 = $sst == 0 ? 1 : 1 - ($sumSquaredError / $sst);

        if ($r2 < 0) {
            $r2 = 0;
        }

        if ($r2 > 1) {
            $r2 = 1;
        }

        $dataTerakhir = $dataHistoris[$n - 1];
        $nilaiTerakhir = $dataTerakhir['jumlah'];

        $delta = $nilaiPrediksi - $nilaiTerakhir;
        $deltaPersen = $nilaiTerakhir > 0 ? ($delta / $nilaiTerakhir) * 100 : null;
    } else {
        $regressionMessage = 'Perhitungan regresi tidak dapat dilakukan karena pembagi bernilai nol.';
    }
} else {
    $regressionMessage = 'Minimal diperlukan 2 data historis sebelum tahun prediksi.';
}

/*
|--------------------------------------------------------------------------
| Menyimpan hasil prediksi ke database
|--------------------------------------------------------------------------
| Proses ini hanya dijalankan ketika pengguna menekan tombol:
| "Simpan Hasil Prediksi".
|
| Data akan disimpan ke:
| 1. tabel prediksi_regresi
| 2. tabel hasil_prediksi
|--------------------------------------------------------------------------
*/
if ($isSaveRequest) {
    /*
    |--------------------------------------------------------------------------
    | Memeriksa token keamanan form
    |--------------------------------------------------------------------------
    */
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

    if (
        $postedToken === ''
        || $sessionToken === ''
        || !hash_equals($sessionToken, $postedToken)
    ) {
        setPredictionFlash(
            'error',
            'Permintaan penyimpanan tidak valid. Silakan ulangi proses prediksi.'
        );

        redirectPrediction($selectedKecamatan, $selectedTahun);
    }

    /*
    |--------------------------------------------------------------------------
    | Memvalidasi kecamatan dan tahun
    |--------------------------------------------------------------------------
    */
    if ($selectedKecamatan <= 0) {
        setPredictionFlash('error', 'Kecamatan yang dipilih tidak valid.');
        redirectPrediction($selectedKecamatan, $selectedTahun);
    }

    if ($selectedTahun < 1901 || $selectedTahun > 2155) {
        setPredictionFlash('error', 'Tahun prediksi tidak valid.');
        redirectPrediction($selectedKecamatan, $selectedTahun);
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan hasil perhitungan tersedia
    |--------------------------------------------------------------------------
    | Hasil tidak boleh disimpan jika data historis belum cukup atau
    | perhitungan regresi gagal.
    |--------------------------------------------------------------------------
    */
    if (
        $nilaiPrediksi === null
        || $intercept === null
        || $slope === null
        || $mae === null
        || $rmse === null
        || $r2 === null
    ) {
        setPredictionFlash(
            'error',
            'Hasil prediksi belum dapat disimpan karena perhitungan belum lengkap.'
        );

        redirectPrediction($selectedKecamatan, $selectedTahun);
    }

    /*
    |--------------------------------------------------------------------------
    | Mencegah penyimpanan hasil prediksi negatif
    |--------------------------------------------------------------------------
    | Jumlah penduduk miskin tidak mungkin bernilai negatif.
    |--------------------------------------------------------------------------
    */
    if ($nilaiPrediksi < 0) {
        setPredictionFlash(
            'error',
            'Hasil prediksi bernilai negatif sehingga tidak layak disimpan.'
        );

        redirectPrediction($selectedKecamatan, $selectedTahun);
    }

    /*
    |--------------------------------------------------------------------------
    | Menyiapkan nilai sesuai presisi kolom database
    |--------------------------------------------------------------------------
    */
    $nilaiPrediksiDb = round((float) $nilaiPrediksi, 2);
    $interceptDb = round((float) $intercept, 6);
    $slopeDb = round((float) $slope, 6);
    $maeDb = round((float) $mae, 6);
    $rmseDb = round((float) $rmse, 6);
    $r2Db = round((float) $r2, 6);
    $metode = 'Regresi Linier';

    try {
        /*
        |--------------------------------------------------------------------------
        | Memulai transaksi database
        |--------------------------------------------------------------------------
        | Jika salah satu proses penyimpanan gagal, seluruh proses dibatalkan.
        |--------------------------------------------------------------------------
        */
        mysqli_begin_transaction($koneksi);

        /*
        |--------------------------------------------------------------------------
        | SIMPAN KE TABEL prediksi_regresi
        |--------------------------------------------------------------------------
        | Tabel ini menyimpan identitas proses prediksi:
        | - kecamatan yang diprediksi
        | - tahun tujuan prediksi
        | - metode yang digunakan
        |
        | ON DUPLICATE KEY UPDATE digunakan karena kombinasi kecamatan dan
        | tahun_prediksi bersifat unik.
        |--------------------------------------------------------------------------
        */
        $queryProses = "
            INSERT INTO prediksi_regresi (
                id_kecamatan,
                tahun_prediksi,
                metode
            )
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                metode = VALUES(metode),
                id_prediksi_regresi = LAST_INSERT_ID(id_prediksi_regresi)
        ";

        $stmtProses = mysqli_prepare($koneksi, $queryProses);

        mysqli_stmt_bind_param(
            $stmtProses,
            'iis',
            $selectedKecamatan,
            $selectedTahun,
            $metode
        );

        mysqli_stmt_execute($stmtProses);

        /*
        |--------------------------------------------------------------------------
        | Mengambil ID proses prediksi
        |--------------------------------------------------------------------------
        | ID ini akan menjadi foreign key pada tabel hasil_prediksi.
        | LAST_INSERT_ID tetap bekerja untuk data baru maupun data lama
        | yang diperbarui melalui ON DUPLICATE KEY UPDATE.
        |--------------------------------------------------------------------------
        */
        $idPrediksiRegresi = (int) mysqli_insert_id($koneksi);

        if ($idPrediksiRegresi <= 0) {
            throw new RuntimeException(
                'ID proses prediksi tidak berhasil diperoleh.'
            );
        }

        mysqli_stmt_close($stmtProses);

        /*
        |--------------------------------------------------------------------------
        | SIMPAN KE TABEL hasil_prediksi
        |--------------------------------------------------------------------------
        | Tabel ini menyimpan hasil akhir perhitungan:
        | - nilai prediksi
        | - intercept
        | - slope
        | - MAE
        | - RMSE
        | - R²
        |
        | Jika kecamatan dan tahun yang sama dihitung ulang, record lama
        | akan diperbarui dan tidak menghasilkan duplikasi.
        |--------------------------------------------------------------------------
        */
        $queryHasil = "
            INSERT INTO hasil_prediksi (
                id_prediksi_regresi,
                id_kecamatan,
                tahun_prediksi,
                nilai_prediksi,
                intercept,
                slope,
                mae,
                rmse,
                r2
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_prediksi_regresi = VALUES(id_prediksi_regresi),
                nilai_prediksi = VALUES(nilai_prediksi),
                intercept = VALUES(intercept),
                slope = VALUES(slope),
                mae = VALUES(mae),
                rmse = VALUES(rmse),
                r2 = VALUES(r2)
        ";

        $stmtHasil = mysqli_prepare($koneksi, $queryHasil);

        /*
        |--------------------------------------------------------------------------
        | Tipe parameter:
        | i = integer
        | d = double atau angka desimal
        |
        | Terdapat 3 integer dan 6 angka desimal.
        |--------------------------------------------------------------------------
        */
        mysqli_stmt_bind_param(
            $stmtHasil,
            'iiidddddd',
            $idPrediksiRegresi,
            $selectedKecamatan,
            $selectedTahun,
            $nilaiPrediksiDb,
            $interceptDb,
            $slopeDb,
            $maeDb,
            $rmseDb,
            $r2Db
        );

        mysqli_stmt_execute($stmtHasil);
        mysqli_stmt_close($stmtHasil);

        /*
        |--------------------------------------------------------------------------
        | Menyelesaikan transaksi
        |--------------------------------------------------------------------------
        | Data pada kedua tabel dinyatakan berhasil disimpan.
        |--------------------------------------------------------------------------
        */
        mysqli_commit($koneksi);

        setPredictionFlash(
            'success',
            'Hasil prediksi Kecamatan '
            . $namaKecamatan
            . ' tahun '
            . $selectedTahun
            . ' berhasil disimpan ke database.'
        );
    } catch (Throwable $e) {
        /*
        |--------------------------------------------------------------------------
        | Membatalkan transaksi apabila terjadi error
        |--------------------------------------------------------------------------
        */
        @mysqli_rollback($koneksi);

        /*
        |--------------------------------------------------------------------------
        | Menyimpan detail error ke PHP error log
        |--------------------------------------------------------------------------
        | Detail error tidak langsung ditampilkan kepada pengguna.
        |--------------------------------------------------------------------------
        */
        error_log(
            'Penyimpanan hasil prediksi gagal: ' . $e->getMessage()
        );

        setPredictionFlash(
            'error',
            'Hasil prediksi gagal disimpan. Periksa koneksi dan struktur database.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Redirect setelah POST
    |--------------------------------------------------------------------------
    | Mencegah penyimpanan berulang ketika pengguna melakukan refresh.
    |--------------------------------------------------------------------------
    */
    redirectPrediction($selectedKecamatan, $selectedTahun);
}

/*
|--------------------------------------------------------------------------
| Mengambil pesan hasil penyimpanan
|--------------------------------------------------------------------------
*/
$predictionFlash = getPredictionFlash();

/*
|--------------------------------------------------------------------------
| Label periode data historis
|--------------------------------------------------------------------------
*/
$periodeHistoris = '-';

if ($n > 0) {
    $periodeHistoris = $dataHistoris[0]['tahun'] . ' - ' . $dataHistoris[$n - 1]['tahun'];
}

/*
|--------------------------------------------------------------------------
| Opsi tahun prediksi
|--------------------------------------------------------------------------
*/
$tahunSekarang = (int) date('Y');
$tahunUji = 2025;

$calonTahunMulai = $maxTahun ? $maxTahun + 1 : $tahunSekarang;
$tahunMulai = min($tahunUji, $calonTahunMulai);
$tahunSelesai = max($tahunMulai + 5, $calonTahunMulai + 5);
/*
|--------------------------------------------------------------------------
| Siapkan data untuk tabel historis dan grafik
|--------------------------------------------------------------------------
*/
$dataHistorisDesc = $dataHistoris;
usort($dataHistorisDesc, function ($a, $b) {
    return $b['tahun'] <=> $a['tahun'];
});

/*
|--------------------------------------------------------------------------
| Hitung perubahan data historis per tahun
|--------------------------------------------------------------------------
*/
$perubahanHistoris = [];

for ($i = 0; $i < count($dataHistoris); $i++) {
    $tahun = $dataHistoris[$i]['tahun'];
    $jumlah = $dataHistoris[$i]['jumlah'];

    if ($i === 0) {
        $perubahanHistoris[$tahun] = null;
    } else {
        $jumlahSebelumnya = $dataHistoris[$i - 1]['jumlah'];

        if ($jumlahSebelumnya > 0) {
            $perubahanHistoris[$tahun] = (($jumlah - $jumlahSebelumnya) / $jumlahSebelumnya) * 100;
        } else {
            $perubahanHistoris[$tahun] = null;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Siapkan data grafik
|--------------------------------------------------------------------------
*/
$chartData = [];

foreach ($dataHistoris as $data) {
    $chartData[] = [
        'tahun' => $data['tahun'],
        'jumlah' => $data['jumlah'],
        'tipe' => 'historis'
    ];
}

if ($nilaiPrediksi !== null) {
    $chartData[] = [
        'tahun' => $selectedTahun,
        'jumlah' => $nilaiPrediksi,
        'tipe' => 'prediksi'
    ];
}

$chartValues = array_column($chartData, 'jumlah');

$chartMin = count($chartValues) > 0 ? min($chartValues) : 0;
$chartMax = count($chartValues) > 0 ? max($chartValues) : 0;

if ($chartMin === $chartMax) {
    $chartMin = $chartMin - 1;
    $chartMax = $chartMax + 1;
}

$chartPadding = ($chartMax - $chartMin) * 0.15;
$chartMin = max(0, $chartMin - $chartPadding);
$chartMax = $chartMax + $chartPadding;

$svgWidth = 760;
$svgHeight = 300;
$svgLeft = 60;
$svgRight = 28;
$svgTop = 25;
$svgBottom = 48;
$plotWidth = $svgWidth - $svgLeft - $svgRight;
$plotHeight = $svgHeight - $svgTop - $svgBottom;

$chartPoints = [];

$totalChartData = count($chartData);

foreach ($chartData as $index => $item) {
    $x = $totalChartData > 1
        ? $svgLeft + (($plotWidth / ($totalChartData - 1)) * $index)
        : $svgLeft + ($plotWidth / 2);

    $y = $svgTop + (($chartMax - $item['jumlah']) / ($chartMax - $chartMin)) * $plotHeight;

    $chartPoints[] = [
        'x' => $x,
        'y' => $y,
        'tahun' => $item['tahun'],
        'jumlah' => $item['jumlah'],
        'tipe' => $item['tipe']
    ];
}

/*
|--------------------------------------------------------------------------
| Bentuk path garis historis dan garis prediksi
|--------------------------------------------------------------------------
*/
$historicalPath = '';
$predictionPath = '';

$historicalPoints = array_values(array_filter($chartPoints, function ($point) {
    return $point['tipe'] === 'historis';
}));

foreach ($historicalPoints as $index => $point) {
    $historicalPath .= ($index === 0 ? 'M' : ' L') . $point['x'] . ' ' . $point['y'];
}

if ($nilaiPrediksi !== null && count($chartPoints) >= 2) {
    $lastHistoricalPoint = $chartPoints[count($chartPoints) - 2];
    $predictionPoint = $chartPoints[count($chartPoints) - 1];

    if ($predictionPoint['tipe'] === 'prediksi') {
        $predictionPath = 'M' . $lastHistoricalPoint['x'] . ' ' . $lastHistoricalPoint['y'] .
            ' L' . $predictionPoint['x'] . ' ' . $predictionPoint['y'];
    }
}

/*
|--------------------------------------------------------------------------
| Label evaluasi R Square
|--------------------------------------------------------------------------
*/
$evaluasiLabel = 'Belum tersedia';
$evaluasiClass = 'neutral';

if ($r2 !== null) {
    if ($r2 >= 0.8) {
        $evaluasiLabel = 'Model memiliki performa sangat baik Dari data tahun 2025';
        $evaluasiClass = 'good';
    } elseif ($r2 >= 0.6) {
        $evaluasiLabel = 'Model memiliki performa cukup baik Dari data tahun 2025';
        $evaluasiClass = 'warning';
    } else {
        $evaluasiLabel = 'Model perlu dievaluasi kembali Dari data tahun 2025';
        $evaluasiClass = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediksi Jumlah Penduduk Miskin</title>

    <link rel="stylesheet" href="../style-sidebar.css">
    <link rel="stylesheet" href="../style-header.css">
    <link rel="stylesheet" href="style-prediksi.css?v=2">
</head>
<body>

<?php include __DIR__ . '/../sidebar.php'; ?>

<main class="main-content">
    <?php
    $pageTitle = 'Prediksi Jumlah Penduduk Miskin';
    $pageSubtitle = 'Menghitung prediksi jumlah penduduk miskin menggunakan metode regresi linier sederhana.';
    $pageIcon = '📈';
    require_once __DIR__ . '/../template-header.php';
    ?>

    <?php if ($predictionFlash): ?>
    <!--
    |--------------------------------------------------------------------------
    | Menampilkan pesan hasil penyimpanan
    |--------------------------------------------------------------------------
    -->
    <div class="prediction-alert <?= $predictionFlash['type'] === 'success'
        ? 'prediction-alert-success'
        : 'prediction-alert-error'; ?>">
        <?= e($predictionFlash['message']); ?>
    </div>
<?php endif; ?>

    <!-- BARIS PERTAMA -->
    <section class="prediction-row-first">

        <!-- Filter Prediksi -->
        <div class="filter-card">
            <h3>Filter Prediksi</h3>

            <form action="" method="GET" class="filter-form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="id_kecamatan">Kecamatan</label>
                        <select name="id_kecamatan" id="id_kecamatan" required>
                            <?php if (count($kecamatanList) === 0): ?>
                                <option value="">Belum ada data kecamatan</option>
                            <?php else: ?>
                                <?php foreach ($kecamatanList as $kecamatan): ?>
                                    <option value="<?= e($kecamatan['id_kecamatan']); ?>"
                                        <?= (int) $kecamatan['id_kecamatan'] === $selectedKecamatan ? 'selected' : ''; ?>>
                                        <?= e($kecamatan['nama_kecamatan']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tahun_prediksi">Tahun Prediksi</label>
                        <select name="tahun_prediksi" id="tahun_prediksi" required>

                            <?php for ($tahun = $tahunMulai; $tahun <= $tahunSelesai; $tahun++): ?>
                                <option value="<?= $tahun; ?>" <?= $tahun === $selectedTahun ? 'selected' : ''; ?>>
                                    <?= $tahun; ?><?= $tahun === 2025 ? ' - Data Uji' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-process">
                    <span>↗</span>
                    Proses Prediksi
                </button>
            </form>

            <?php if ($regressionMessage): ?>
                <div class="filter-warning">
                    <?= e($regressionMessage); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Card Data Historis -->
        <div class="summary-card">
            <div class="summary-icon green">
                🗄
            </div>

            <div class="summary-content">
                <span>Data Historis</span>
                <strong><?= e($n); ?></strong>
                <small>Tahun <?= e($periodeHistoris); ?></small>
            </div>
        </div>

        <!-- Card Hasil Prediksi -->
        <div class="summary-card">
            <div class="summary-icon blue">
                📈
            </div>

            <div class="summary-content">
                <span>Hasil Prediksi <?= e($selectedTahun); ?></span>
                <strong><?= $nilaiPrediksi !== null ? angkaSingkat($nilaiPrediksi) : '-'; ?></strong>
                <small>Jiwa Miskin</small>
            </div>
        </div>

        <!-- Card MAE -->
        <div class="summary-card">
            <div class="summary-icon yellow">
                🎯
            </div>

            <div class="summary-content">
                <span>MAE</span>
                <strong><?= $mae !== null ? angkaSingkat($mae) : '-'; ?></strong>
                <small>Rata-rata Error (Jiwa)</small>
            </div>
        </div>

        <!-- Card R Square -->
        <div class="summary-card">
            <div class="summary-icon purple">
                R²
            </div>

            <div class="summary-content">
                <span>R²</span>
                <strong><?= $r2 !== null ? angka($r2, 3) : '-'; ?></strong>
                <small>
                    <?php if ($r2 !== null): ?>
                        <?= $r2 >= 0.8 ? 'Good Fit' : 'Perlu Evaluasi'; ?>
                    <?php else: ?>
                        Belum tersedia
                    <?php endif; ?>
                </small>
            </div>
        </div>

    </section>

    <!-- BARIS KEDUA -->
    <section class="prediction-row-second">

        <!-- Informasi Model Regresi Linier -->
        <div class="model-card">
            <h3>Informasi Model Regresi Linier</h3>

            <div class="model-content">
                <div class="equation-box">
                    <span>Persamaan Regresi</span>

                    <?php if ($intercept !== null && $slope !== null): ?>
                        <strong>
                            Ŷ =
                            <?= angka($intercept, 2); ?>
                            <?= $slope >= 0 ? '+' : '-'; ?>
                            <?= angka(abs($slope), 2); ?>X
                        </strong>
                    <?php else: ?>
                        <strong>Belum tersedia</strong>
                    <?php endif; ?>
                </div>

                <div class="model-divider"></div>

                <div class="model-detail">
                    <div class="model-item">
                        <span>Intercept (a)</span>
                        <strong><?= $intercept !== null ? angka($intercept, 2) : '-'; ?></strong>
                    </div>

                    <div class="model-item">
                        <span>Slope (b)</span>
                        <strong><?= $slope !== null ? angka($slope, 2) : '-'; ?></strong>
                    </div>

                    <div class="model-item">
                        <span>Variabel X</span>
                        <strong>Tahun</strong>
                    </div>

                    <div class="model-item">
                        <span>Variabel Y</span>
                        <strong>Jumlah Penduduk Miskin (Jiwa)</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nilai Prediksi -->
        <div class="prediction-result-card">
            <div class="prediction-result-title">
                <h3>Nilai Prediksi (<?= e($selectedTahun); ?>)</h3>
                <span title="Nilai prediksi dihitung menggunakan regresi linier sederhana berdasarkan data historis.">ⓘ</span>
            </div>

            <div class="prediction-big-number">
                <?= $nilaiPrediksi !== null ? angkaSingkat($nilaiPrediksi) : '-'; ?>
            </div>

            <p>Jiwa Penduduk Miskin</p>

           <?php if ($delta !== null && $deltaPersen !== null): ?>
                <?php
                    $deltaClass = $delta >= 0 ? 'is-up' : 'is-down';
                    $deltaIcon = $delta >= 0 ? '▲' : '▼';
                    $deltaText = $delta >= 0 ? 'naik' : 'turun';
                    $tahunPembanding = $dataHistoris[$n - 1]['tahun'] ?? '-';
                ?>

                <div class="prediction-change <?= e($deltaClass); ?>">
                    <span><?= e($deltaIcon); ?></span>

                    <?= angkaSingkat(abs($delta)); ?> jiwa
                    (<?= angka(abs($deltaPersen), 2); ?>%)

                    <?= e($deltaText); ?>
                    dibanding tahun <?= e($tahunPembanding); ?>
                </div>
            <?php else: ?>
                <div class="prediction-change is-neutral">
                    Data pembanding belum tersedia
                </div>
            <?php endif; ?>

            <?php if (
                $nilaiPrediksi !== null
                && $intercept !== null
                && $slope !== null
                && $mae !== null
                && $rmse !== null
                && $r2 !== null
            ): ?>
                <form
                    method="POST"
                    action=""
                    class="save-prediction-form"
                    id="savePredictionForm"
                >
                    <input
                        type="hidden"
                        name="action"
                        value="save_prediction"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']); ?>"
                    >

                    <input
                        type="hidden"
                        name="id_kecamatan"
                        value="<?= (int) $selectedKecamatan; ?>"
                    >

                    <input
                        type="hidden"
                        name="tahun_prediksi"
                        value="<?= (int) $selectedTahun; ?>"
                    >

                    <button
                        type="submit"
                        class="btn-save-prediction"
                        id="btnSavePrediction"
                    >
                        <span class="btn-save-icon">💾</span>
                        <span class="btn-save-text">Simpan Hasil Prediksi</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

    </section>

    <!-- BARIS KETIGA -->
    <section class="prediction-row-third">

        <!-- Data Historis -->
        <div class="history-card">
            <h3>Data Historis - Kecamatan <?= e($namaKecamatan); ?></h3>

            <div class="history-table-wrapper">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Tahun</th>
                            <th>Jumlah Penduduk Miskin (Jiwa)</th>
                            <th>Perubahan (%)</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($dataHistorisDesc) > 0): ?>
                            <?php foreach ($dataHistorisDesc as $data): ?>
                                <?php
                                    $tahun = $data['tahun'];
                                    $jumlah = $data['jumlah'];
                                    $perubahan = $perubahanHistoris[$tahun] ?? null;
                                ?>
                                <tr>
                                    <td><?= e($tahun); ?></td>
                                    <td><?= angkaSingkat($jumlah); ?></td>
                                    <td>
                                        <?php if ($perubahan === null): ?>
                                            <span class="change-neutral">-</span>
                                        <?php elseif ($perubahan >= 0): ?>
                                            <span class="change-up">
                                                <?= angka(abs($perubahan), 2); ?>% ↑
                                            </span>
                                        <?php else: ?>
                                            <span class="change-down">
                                                <?= angka(abs($perubahan), 2); ?>% ↓
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-table">
                                    Belum ada data historis untuk kecamatan ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="history-source">
                ⓘ Sumber: BPS Kabupaten Sumba Timur
            </div>
        </div>

        <!-- Trend Data Historis dan Hasil Prediksi -->
        <div class="trend-card">
            <div class="trend-header">
                <h3>Trend Data Historis & Hasil Prediksi</h3>

                <div class="trend-legend">
                    <span><i class="legend-blue"></i> Data Historis</span>
                    <span><i class="legend-green"></i> Prediksi</span>
                </div>
            </div>

            <div class="chart-wrapper">
                <?php if (count($chartPoints) >= 2): ?>
                    <svg class="trend-chart" viewBox="0 0 <?= $svgWidth; ?> <?= $svgHeight; ?>" role="img">

                        <!-- Grid horizontal -->
                        <?php for ($i = 0; $i <= 4; $i++): ?>
                            <?php
                                $gridY = $svgTop + (($plotHeight / 4) * $i);
                                $gridValue = $chartMax - (($chartMax - $chartMin) / 4 * $i);
                            ?>
                            <line
                                x1="<?= $svgLeft; ?>"
                                y1="<?= $gridY; ?>"
                                x2="<?= $svgWidth - $svgRight; ?>"
                                y2="<?= $gridY; ?>"
                                class="chart-grid"
                            />

                            <text
                                x="<?= $svgLeft - 12; ?>"
                                y="<?= $gridY + 4; ?>"
                                text-anchor="end"
                                class="chart-label"
                            >
                                <?= angkaSingkat($gridValue); ?>
                            </text>
                        <?php endfor; ?>

                        <!-- Axis -->
                        <line
                            x1="<?= $svgLeft; ?>"
                            y1="<?= $svgTop; ?>"
                            x2="<?= $svgLeft; ?>"
                            y2="<?= $svgHeight - $svgBottom; ?>"
                            class="chart-axis"
                        />

                        <line
                            x1="<?= $svgLeft; ?>"
                            y1="<?= $svgHeight - $svgBottom; ?>"
                            x2="<?= $svgWidth - $svgRight; ?>"
                            y2="<?= $svgHeight - $svgBottom; ?>"
                            class="chart-axis"
                        />

                        <!-- Garis historis -->
                        <?php if ($historicalPath !== ''): ?>
                            <path d="<?= e($historicalPath); ?>" class="line-historical" />
                        <?php endif; ?>

                        <!-- Garis prediksi -->
                        <?php if ($predictionPath !== ''): ?>
                            <path d="<?= e($predictionPath); ?>" class="line-prediction" />
                        <?php endif; ?>

                        <!-- Titik data -->
                        <?php foreach ($chartPoints as $point): ?>
                            <circle
                                cx="<?= $point['x']; ?>"
                                cy="<?= $point['y']; ?>"
                                r="5"
                                class="<?= $point['tipe'] === 'prediksi' ? 'point-prediction' : 'point-historical'; ?>"
                            />

                            <text
                                x="<?= $point['x']; ?>"
                                y="<?= $point['y'] - 12; ?>"
                                text-anchor="middle"
                                class="point-value"
                            >
                                <?= angkaSingkat($point['jumlah']); ?>
                            </text>

                            <text
                                x="<?= $point['x']; ?>"
                                y="<?= $svgHeight - 18; ?>"
                                text-anchor="middle"
                                class="chart-year"
                            >
                                <?= e($point['tahun']); ?>
                            </text>
                        <?php endforeach; ?>

                        <!-- Label sumbu -->
                        <text
                            x="<?= $svgWidth / 2; ?>"
                            y="<?= $svgHeight - 2; ?>"
                            text-anchor="middle"
                            class="axis-title"
                        >
                            Tahun
                        </text>

                        <text
                            x="18"
                            y="<?= $svgHeight / 2; ?>"
                            text-anchor="middle"
                            transform="rotate(-90 18 <?= $svgHeight / 2; ?>)"
                            class="axis-title"
                        >
                            Jumlah Penduduk Miskin (Jiwa)
                        </text>
                    </svg>
                <?php else: ?>
                    <div class="empty-chart">
                        Data belum cukup untuk menampilkan grafik trend.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Evaluasi Model -->
<!-- Evaluasi Model -->
<!-- Evaluasi Model -->
<div class="evaluation-card">
    <h3>Evaluasi Model</h3>

    <div class="evaluation-list">
        <div class="evaluation-item">
            <div class="evaluation-icon">A</div>

            <div class="evaluation-text">
                <div class="metric-title-row">


                        <span class="metric-popup">
                            <strong>MAE - Mean Absolute Error</strong>
                            <span>
                                MAE menunjukkan rata-rata selisih antara hasil prediksi dan data aktual.
                                Perumpamaannya: jika model menebak jumlah penduduk miskin, MAE menunjukkan
                                rata-rata seberapa jauh tebakan itu meleset.
                            </span>
                            <em>
                                Semakin kecil MAE, semakin baik. Contoh: MAE 500 berarti rata-rata prediksi
                                meleset sekitar 500 jiwa.
                            </em>
                        </span>
                    </span>
                </div>

                <span>Mean Absolute Error</span>
            </div>

            <div class="evaluation-value">
                <strong><?= $mae !== null ? angkaSingkat($mae) : '-'; ?></strong>
                <span>Jiwa</span>
            </div>
        </div>

        <div class="evaluation-item">
            <div class="evaluation-icon">R</div>

            <div class="evaluation-text">
                <div class="metric-title-row">

                        <span class="metric-popup">
                            <strong>RMSE - Root Mean Square Error</strong>
                            <span>
                                RMSE juga mengukur kesalahan prediksi, tetapi lebih sensitif terhadap kesalahan
                                yang besar. Perumpamaannya: jika ada satu prediksi yang sangat jauh meleset,
                                RMSE akan memberi hukuman lebih besar dibanding MAE.
                            </span>
                            <em>
                                Semakin kecil RMSE, semakin baik. Jika RMSE jauh lebih besar dari MAE,
                                berarti ada error prediksi yang cukup besar.
                            </em>
                        </span>
                    </span>
                </div>

                <span>Root Mean Square Error</span>
            </div>

            <div class="evaluation-value">
                <strong><?= $rmse !== null ? angkaSingkat($rmse) : '-'; ?></strong>
                <span>Jiwa</span>
            </div>
        </div>

        <div class="evaluation-item">
            <div class="evaluation-icon">R²</div>

            <div class="evaluation-text">
                <div class="metric-title-row">

                        <span class="metric-popup">
                            <strong>R² - Koefisien Determinasi</strong>
                            <span>
                                R² menunjukkan seberapa baik model regresi menjelaskan pola data.
                                Perumpamaannya: jika data kemiskinan memiliki pola naik atau turun,
                                R² menunjukkan seberapa mampu model mengikuti pola tersebut.
                            </span>
                            <em>
                                Semakin mendekati 1, semakin baik. Contoh: R² 0,85 berarti sekitar 85%
                                variasi data dapat dijelaskan oleh model.
                            </em>
                        </span>
                    </span>
                </div>

                <span>Koefisien Determinasi</span>
            </div>

            <div class="evaluation-value">
                <strong><?= $r2 !== null ? angka($r2, 3) : '-'; ?></strong>
            </div>
        </div>
    </div>

    <div class="model-status <?= e($evaluasiClass); ?>">
        <div class="status-icon">✓</div>

        <div>
            <strong><?= e($evaluasiLabel); ?></strong>

            <?php if ($r2 !== null): ?>
                <p>
                    Nilai R² sebesar <?= angka($r2, 3); ?> menunjukkan kemampuan model
                    dalam menjelaskan variasi data historis yang digunakan.
                </p>
            <?php else: ?>
                <p>
                    Evaluasi model belum tersedia karena data historis belum mencukupi.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

    </section>

</main>
<script src="script-prediksi.js"></script>
</body>
</html> 