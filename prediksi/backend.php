<?php
/**
 * Backend halaman Prediksi Kelompok Kesejahteraan Rendah.
 *
 * Berkas ini hanya menangani autentikasi, koneksi database, pemilihan data,
 * validasi input GET, dan penyiapan variabel untuk index.php.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../koneksi.php';

/** @var mysqli $koneksi */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$active_menu = 'prediksi';

function prediksi_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function prediksi_angka(mixed $value, int $decimal = 0): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, $decimal, ',', '.');
}

function prediksi_bulat(mixed $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 0, ',', '.');
}

function prediksi_signed(mixed $value, int $decimal = 2, string $suffix = ''): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $number = (float) $value;
    $sign = $number > 0 ? '+' : ($number < 0 ? '-' : '');

    return $sign . prediksi_angka(abs($number), $decimal) . $suffix;
}

function prediksi_status_class(?string $status): string
{
    return match (strtolower(trim((string) $status))) {
        'rendah' => 'status-low',
        'sedang' => 'status-medium',
        'tinggi' => 'status-high',
        default => 'status-neutral',
    };
}

function prediksi_query_all(mysqli $db, string $sql): array
{
    $rows = [];
    $result = mysqli_query($db, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_free_result($result);
    return $rows;
}

$backendError = null;
$kecamatanList = [];
$tahunList = [];
$selectedKecamatan = max(0, (int) ($_GET['id_kecamatan'] ?? 0));
$selectedTahun = max(0, (int) ($_GET['tahun_prediksi'] ?? 0));
$prediction = null;
$historicalRows = [];
$model = null;
$modelCoefficients = [];
$districtEffect = null;
$chartDataJson = '[]';
$latestActual = null;
$periodLabel = '-';
$selectedDistrictName = '-';

try {
    /*
     * Daftar tahun hanya berasal dari hasil utama yang tersedia. Dengan aturan
     * ini, pengguna tidak dapat memilih tahun yang belum mempunyai hasil resmi.
     */
    $tahunList = array_map(
        static fn(array $row): int => (int) $row['tahun_prediksi'],
        prediksi_query_all(
            $koneksi,
            "SELECT DISTINCT tahun_prediksi
             FROM v_hasil_prediksi_utama
             ORDER BY tahun_prediksi DESC"
        )
    );

    if ($selectedTahun === 0) {
        $selectedTahun = $tahunList[0] ?? 2026;
    }

    if ($tahunList !== [] && !in_array($selectedTahun, $tahunList, true)) {
        $selectedTahun = $tahunList[0];
    }

    /*
     * Daftar kecamatan berasal dari tabel induk agar urutannya stabil dan tetap
     * tersedia walaupun suatu hasil prediksi belum terbentuk.
     */
    $kecamatanList = prediksi_query_all(
        $koneksi,
        "SELECT id_kecamatan, kode_panel, nama_kecamatan
         FROM kecamatan
         WHERE COALESCE(status_aktif, 1) = 1
         ORDER BY nama_kecamatan ASC"
    );

    if ($selectedKecamatan === 0 && $kecamatanList !== []) {
        $selectedKecamatan = (int) $kecamatanList[0]['id_kecamatan'];
    }

    $validDistrictIds = array_map(
        static fn(array $row): int => (int) $row['id_kecamatan'],
        $kecamatanList
    );

    if ($kecamatanList !== [] && !in_array($selectedKecamatan, $validDistrictIds, true)) {
        $selectedKecamatan = (int) $kecamatanList[0]['id_kecamatan'];
    }

    foreach ($kecamatanList as $district) {
        if ((int) $district['id_kecamatan'] === $selectedKecamatan) {
            $selectedDistrictName = (string) $district['nama_kecamatan'];
            break;
        }
    }

    /* Hasil utama tahun target. */
    $stmtPrediction = mysqli_prepare(
        $koneksi,
        "SELECT *
         FROM v_hasil_prediksi_utama
         WHERE id_kecamatan = ?
           AND tahun_prediksi = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmtPrediction, 'ii', $selectedKecamatan, $selectedTahun);
    mysqli_stmt_execute($stmtPrediction);
    $predictionResult = mysqli_stmt_get_result($stmtPrediction);
    $prediction = mysqli_fetch_assoc($predictionResult) ?: null;
    mysqli_stmt_close($stmtPrediction);

    /* Data aktual 2022 sampai 2025 untuk tabel dan grafik. */
    $stmtHistory = mysqli_prepare(
        $koneksi,
        "SELECT *
         FROM v_data_historis_website
         WHERE id_kecamatan = ?
         ORDER BY tahun ASC"
    );
    mysqli_stmt_bind_param($stmtHistory, 'i', $selectedKecamatan);
    mysqli_stmt_execute($stmtHistory);
    $historyResult = mysqli_stmt_get_result($stmtHistory);

    while ($row = mysqli_fetch_assoc($historyResult)) {
        $historicalRows[] = $row;
    }
    mysqli_stmt_close($stmtHistory);

    if ($historicalRows !== []) {
        $firstYear = (int) $historicalRows[0]['tahun'];
        $lastYear = (int) $historicalRows[count($historicalRows) - 1]['tahun'];
        $periodLabel = $firstYear . ' sampai ' . $lastYear;
        $latestActual = $historicalRows[count($historicalRows) - 1];
    }

    /* Identitas model dan metrik validasi berlaku untuk seluruh kecamatan. */
    if ($prediction !== null) {
        $stmtModel = mysqli_prepare(
            $koneksi,
            "SELECT *
             FROM model_regresi
             WHERE kode_model = ?
             LIMIT 1"
        );
        $kodeModel = (string) $prediction['kode_model'];
        mysqli_stmt_bind_param($stmtModel, 's', $kodeModel);
        mysqli_stmt_execute($stmtModel);
        $modelResult = mysqli_stmt_get_result($stmtModel);
        $model = mysqli_fetch_assoc($modelResult) ?: null;
        mysqli_stmt_close($stmtModel);
    }

    if ($model !== null) {
        $idModel = (int) $model['id_model'];

        $stmtCoef = mysqli_prepare(
            $koneksi,
            "SELECT kode_parameter, nama_parameter, koefisien,
                    p_value, arah, status_signifikansi
             FROM koefisien_model
             WHERE id_model = ?
             ORDER BY FIELD(
                 kode_parameter,
                 'const',
                 'rasio_murid_guru',
                 'rasio_ketergantungan',
                 'tenaga_kesehatan_10000',
                 'ikm_10000',
                 'log_kepadatan',
                 'trend'
             ), id_koefisien ASC"
        );
        mysqli_stmt_bind_param($stmtCoef, 'i', $idModel);
        mysqli_stmt_execute($stmtCoef);
        $coefResult = mysqli_stmt_get_result($stmtCoef);

        while ($row = mysqli_fetch_assoc($coefResult)) {
            $modelCoefficients[] = $row;
        }
        mysqli_stmt_close($stmtCoef);

        $stmtEffect = mysqli_prepare(
            $koneksi,
            "SELECT nilai_efek, status_acuan, status_signifikansi
             FROM efek_kecamatan
             WHERE id_model = ?
               AND id_kecamatan = ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmtEffect, 'ii', $idModel, $selectedKecamatan);
        mysqli_stmt_execute($stmtEffect);
        $effectResult = mysqli_stmt_get_result($stmtEffect);
        $districtEffect = mysqli_fetch_assoc($effectResult) ?: null;
        mysqli_stmt_close($stmtEffect);
    }

    /* Data grafik. Titik aktual dan proyeksi dibedakan secara eksplisit. */
    $chartData = [];

    foreach ($historicalRows as $row) {
        $chartData[] = [
            'tahun' => (int) $row['tahun'],
            'persen' => (float) $row['y_persen'],
            'jumlah' => (int) $row['jumlah_kelompok_kesejahteraan_rendah'],
            'status' => 'Aktual',
        ];
    }

    if ($prediction !== null) {
        $chartData[] = [
            'tahun' => (int) $prediction['tahun_prediksi'],
            'persen' => (float) $prediction['prediksi_operasional_persen'],
            'jumlah' => (int) $prediction['prediksi_jumlah_individu'],
            'status' => 'Proyeksi',
        ];
    }

    $encodedChart = json_encode(
        $chartData,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_HEX_AMP
    );

    $chartDataJson = $encodedChart !== false ? $encodedChart : '[]';
} catch (Throwable $error) {
    error_log('Halaman prediksi terbaru gagal memuat data: ' . $error->getMessage());
    $backendError = 'Data prediksi belum dapat ditampilkan. Pastikan file SQL Tahap 18 telah diimpor dan koneksi database aktif.';
}

$observationCount = count($historicalRows);
$actual2025Percent = $prediction['aktual_2025_persen'] ?? ($latestActual['y_persen'] ?? null);
$actual2025Count = $prediction['aktual_2025_jumlah'] ?? ($latestActual['jumlah_kelompok_kesejahteraan_rendah'] ?? null);
$predictionPercent = $prediction['prediksi_operasional_persen'] ?? null;
$predictionRawPercent = $prediction['prediksi_raw_persen'] ?? null;
$predictionCount = $prediction['prediksi_jumlah_individu'] ?? null;
$projectedPopulation = $prediction['jumlah_penduduk_2026'] ?? null;
$deltaPercent = $prediction['selisih_persen_vs_2025'] ?? null;
$deltaCount = $prediction['selisih_jiwa_vs_2025'] ?? null;
$sensitivityStatus = $prediction['status_sensitivitas'] ?? null;
$sensitivityClass = prediksi_status_class($sensitivityStatus);
$hasBoundaryWarning = !empty($prediction['flag_prediksi_di_luar_batas']);
$hasIkmWarning = !empty($prediction['flag_ikm_perlu_verifikasi']);
