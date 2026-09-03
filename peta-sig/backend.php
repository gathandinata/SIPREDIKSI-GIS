<?php
/**
 * Backend halaman Peta SIG Prediksi Kelompok Kesejahteraan Rendah.
 *
 * Berkas ini menangani autentikasi, koneksi database, filter tahun dan mode
 * visualisasi, pembacaan view peta, klasifikasi warna, serta penyiapan JSON
 * yang digunakan oleh index.php dan script-peta-sig.js.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../koneksi.php';

/** @var mysqli $koneksi */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$active_menu = 'peta-sig';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function sig_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sig_angka(mixed $value, int $decimal = 0): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, $decimal, ',', '.');
}

function sig_bulat(mixed $value): string
{
    return sig_angka($value, 0);
}

function sig_signed(mixed $value, int $decimal = 2, string $suffix = ''): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $number = (float) $value;
    $sign = $number > 0 ? '+' : ($number < 0 ? '-' : '');

    return $sign . sig_angka(abs($number), $decimal) . $suffix;
}

function sig_query_all(mysqli $db, string $sql): array
{
    $rows = [];
    $result = mysqli_query($db, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_free_result($result);

    return $rows;
}

function sig_geojson_valid(mixed $value): ?array
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

/**
 * Klasifikasi tetap digunakan agar legenda konsisten dan mudah dibandingkan.
 * Mode persentase menggunakan ambang substantif, sedangkan mode jumlah
 * menggunakan rentang yang sesuai dengan distribusi hasil tahun 2026.
 */
function sig_kategori(float $value, string $mode): array
{
    if ($mode === 'jumlah') {
        if ($value >= 15000) {
            return ['label' => 'Sangat Tinggi', 'class' => 'level-5', 'warna' => '#08306b'];
        }

        if ($value >= 10000) {
            return ['label' => 'Tinggi', 'class' => 'level-4', 'warna' => '#2171b5'];
        }

        if ($value >= 7500) {
            return ['label' => 'Sedang', 'class' => 'level-3', 'warna' => '#6baed6'];
        }

        if ($value >= 5000) {
            return ['label' => 'Rendah', 'class' => 'level-2', 'warna' => '#bdd7e7'];
        }

        return ['label' => 'Sangat Rendah', 'class' => 'level-1', 'warna' => '#eff3ff'];
    }

    if ($value >= 95) {
        return ['label' => 'Sangat Tinggi', 'class' => 'level-5', 'warna' => '#08306b'];
    }

    if ($value >= 85) {
        return ['label' => 'Tinggi', 'class' => 'level-4', 'warna' => '#2171b5'];
    }

    if ($value >= 75) {
        return ['label' => 'Sedang', 'class' => 'level-3', 'warna' => '#6baed6'];
    }

    if ($value >= 60) {
        return ['label' => 'Rendah', 'class' => 'level-2', 'warna' => '#bdd7e7'];
    }

    return ['label' => 'Sangat Rendah', 'class' => 'level-1', 'warna' => '#eff3ff'];
}

function sig_legend(string $mode): array
{
    if ($mode === 'jumlah') {
        return [
            ['class' => 'level-5', 'range' => '≥ 15.000 individu', 'label' => 'Sangat Tinggi'],
            ['class' => 'level-4', 'range' => '10.000–14.999', 'label' => 'Tinggi'],
            ['class' => 'level-3', 'range' => '7.500–9.999', 'label' => 'Sedang'],
            ['class' => 'level-2', 'range' => '5.000–7.499', 'label' => 'Rendah'],
            ['class' => 'level-1', 'range' => '< 5.000 individu', 'label' => 'Sangat Rendah'],
        ];
    }

    return [
        ['class' => 'level-5', 'range' => '≥ 95,00%', 'label' => 'Sangat Tinggi'],
        ['class' => 'level-4', 'range' => '85,00–94,99%', 'label' => 'Tinggi'],
        ['class' => 'level-3', 'range' => '75,00–84,99%', 'label' => 'Sedang'],
        ['class' => 'level-2', 'range' => '60,00–74,99%', 'label' => 'Rendah'],
        ['class' => 'level-1', 'range' => '< 60,00%', 'label' => 'Sangat Rendah'],
    ];
}

$backendError = null;
$tahunList = [];
$selectedTahun = max(0, (int) ($_GET['tahun_prediksi'] ?? $_GET['tahun'] ?? 0));
$selectedMode = strtolower(trim((string) ($_GET['mode'] ?? 'persentase')));
$selectedKecamatan = max(0, (int) ($_GET['id_kecamatan'] ?? 0));

if (!in_array($selectedMode, ['persentase', 'jumlah'], true)) {
    $selectedMode = 'persentase';
}

$mapRows = [];
$mapData = [];
$kecamatanList = [];
$selectedRegion = null;
$model = null;
$mapDataJson = '[]';
$mapConfigJson = '{}';
$legendItems = sig_legend($selectedMode);

$totalKecamatan = 0;
$totalGeojson = 0;
$totalPrediksiJiwa = 0;
$totalPendudukProyeksi = 0;
$persentaseAgregat = null;
$rataRataPersentase = null;
$tertinggiPersentase = null;
$tertinggiJumlah = null;
$terendahPersentase = null;
$terendahJumlah = null;
$modeLabel = $selectedMode === 'jumlah'
    ? 'Jumlah Individu'
    : 'Persentase Operasional';

try {
    $tahunList = array_map(
        static fn(array $row): int => (int) $row['tahun_prediksi'],
        sig_query_all(
            $koneksi,
            "SELECT DISTINCT tahun_prediksi
             FROM v_peta_prediksi_utama
             ORDER BY tahun_prediksi DESC"
        )
    );

    if ($selectedTahun === 0) {
        $selectedTahun = $tahunList[0] ?? 2026;
    }

    if ($tahunList !== [] && !in_array($selectedTahun, $tahunList, true)) {
        $selectedTahun = $tahunList[0];
    }

    $stmtMap = mysqli_prepare(
        $koneksi,
        "SELECT *
         FROM v_peta_prediksi_utama
         WHERE tahun_prediksi = ?
         ORDER BY nama_kecamatan ASC"
    );
    mysqli_stmt_bind_param($stmtMap, 'i', $selectedTahun);
    mysqli_stmt_execute($stmtMap);
    $resultMap = mysqli_stmt_get_result($stmtMap);

    while ($row = mysqli_fetch_assoc($resultMap)) {
        $mapRows[] = $row;
    }

    mysqli_stmt_close($stmtMap);

    foreach ($mapRows as $row) {
        $idKecamatan = (int) $row['id_kecamatan'];
        $prediksiPersen = (float) $row['prediksi_operasional_persen'];
        $prediksiJiwa = (int) $row['prediksi_jumlah_individu'];
        $pendudukProyeksi = (int) $row['jumlah_penduduk_2026'];
        $nilaiKlasifikasi = $selectedMode === 'jumlah'
            ? (float) $prediksiJiwa
            : $prediksiPersen;

        $kategori = sig_kategori($nilaiKlasifikasi, $selectedMode);
        $geojson = sig_geojson_valid($row['geojson_wilayah'] ?? null);

        if ($geojson !== null) {
            $totalGeojson++;
        }

        $kontribusiKabupaten = null;
        $totalPrediksiKabupatenRow = (float) ($row['total_prediksi_kabupaten'] ?? 0);

        if ($totalPrediksiKabupatenRow > 0) {
            $kontribusiKabupaten = ($prediksiJiwa / $totalPrediksiKabupatenRow) * 100;
        }

        $item = [
            'id_kecamatan' => $idKecamatan,
            'kode_panel' => $row['kode_panel'],
            'kode_kecamatan' => $row['kode_kecamatan'],
            'nama_kecamatan' => $row['nama_kecamatan'],
            'tahun_prediksi' => (int) $row['tahun_prediksi'],
            'kode_model' => $row['kode_model'],
            'nama_model' => $row['nama_model'],
            'kode_skenario' => $row['kode_skenario'],
            'status_hasil' => $row['status_hasil'],
            'prediksi_raw_persen' => $row['prediksi_raw_persen'] !== null
                ? (float) $row['prediksi_raw_persen']
                : null,
            'prediksi_operasional_persen' => $prediksiPersen,
            'prediksi_jumlah_individu' => $prediksiJiwa,
            'jumlah_penduduk_2026' => $pendudukProyeksi,
            'aktual_2025_persen' => $row['aktual_2025_persen'] !== null
                ? (float) $row['aktual_2025_persen']
                : null,
            'aktual_2025_jumlah' => $row['aktual_2025_jumlah'] !== null
                ? (int) $row['aktual_2025_jumlah']
                : null,
            'selisih_persen_vs_2025' => $row['selisih_persen_vs_2025'] !== null
                ? (float) $row['selisih_persen_vs_2025']
                : null,
            'selisih_jiwa_vs_2025' => $row['selisih_jiwa_vs_2025'] !== null
                ? (int) $row['selisih_jiwa_vs_2025']
                : null,
            'ci_mean_bawah_persen' => $row['ci_mean_bawah_persen'] !== null
                ? (float) $row['ci_mean_bawah_persen']
                : null,
            'ci_mean_atas_persen' => $row['ci_mean_atas_persen'] !== null
                ? (float) $row['ci_mean_atas_persen']
                : null,
            'interval_prediksi_bawah_persen' => $row['interval_prediksi_bawah_persen'] !== null
                ? (float) $row['interval_prediksi_bawah_persen']
                : null,
            'interval_prediksi_atas_persen' => $row['interval_prediksi_atas_persen'] !== null
                ? (float) $row['interval_prediksi_atas_persen']
                : null,
            'interval_jiwa_bawah' => $row['interval_jiwa_bawah'] !== null
                ? (int) $row['interval_jiwa_bawah']
                : null,
            'interval_jiwa_atas' => $row['interval_jiwa_atas'] !== null
                ? (int) $row['interval_jiwa_atas']
                : null,
            'peringkat_persentase' => $row['peringkat_persentase'] !== null
                ? (int) $row['peringkat_persentase']
                : null,
            'peringkat_jumlah_jiwa' => $row['peringkat_jumlah_jiwa'] !== null
                ? (int) $row['peringkat_jumlah_jiwa']
                : null,
            'status_sensitivitas' => $row['status_sensitivitas'],
            'flag_ikm_perlu_verifikasi' => (int) $row['flag_ikm_perlu_verifikasi'],
            'flag_prediksi_di_luar_batas' => (int) $row['flag_prediksi_di_luar_batas'],
            'kontribusi_prediksi_kabupaten' => $kontribusiKabupaten,
            'kategori' => $kategori['label'],
            'kategori_class' => $kategori['class'],
            'warna' => $kategori['warna'],
            'nilai_klasifikasi' => $nilaiKlasifikasi,
            'mode_klasifikasi' => $selectedMode,
            'geojson' => $geojson,
        ];

        $mapData[] = $item;
        $kecamatanList[] = [
            'id_kecamatan' => $idKecamatan,
            'nama_kecamatan' => $row['nama_kecamatan'],
        ];

        $totalPrediksiJiwa += $prediksiJiwa;
        $totalPendudukProyeksi += $pendudukProyeksi;

        if ($tertinggiPersentase === null || $prediksiPersen > $tertinggiPersentase['prediksi_operasional_persen']) {
            $tertinggiPersentase = $item;
        }

        if ($terendahPersentase === null || $prediksiPersen < $terendahPersentase['prediksi_operasional_persen']) {
            $terendahPersentase = $item;
        }

        if ($tertinggiJumlah === null || $prediksiJiwa > $tertinggiJumlah['prediksi_jumlah_individu']) {
            $tertinggiJumlah = $item;
        }

        if ($terendahJumlah === null || $prediksiJiwa < $terendahJumlah['prediksi_jumlah_individu']) {
            $terendahJumlah = $item;
        }
    }

    $totalKecamatan = count($mapData);

    if ($totalPendudukProyeksi > 0) {
        $persentaseAgregat = ($totalPrediksiJiwa / $totalPendudukProyeksi) * 100;
    }

    if ($totalKecamatan > 0) {
        $rataRataPersentase = array_sum(
            array_column($mapData, 'prediksi_operasional_persen')
        ) / $totalKecamatan;
    }

    if ($mapRows !== []) {
        $kodeModel = (string) $mapRows[0]['kode_model'];

        $stmtModel = mysqli_prepare(
            $koneksi,
            "SELECT *
             FROM model_regresi
             WHERE kode_model = ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmtModel, 's', $kodeModel);
        mysqli_stmt_execute($stmtModel);
        $resultModel = mysqli_stmt_get_result($stmtModel);
        $model = mysqli_fetch_assoc($resultModel) ?: null;
        mysqli_stmt_close($stmtModel);
    }

    $validDistrictIds = array_map(
        static fn(array $item): int => (int) $item['id_kecamatan'],
        $mapData
    );

    if ($selectedKecamatan <= 0 || !in_array($selectedKecamatan, $validDistrictIds, true)) {
        $defaultItem = $selectedMode === 'jumlah'
            ? $tertinggiJumlah
            : $tertinggiPersentase;

        $selectedKecamatan = (int) ($defaultItem['id_kecamatan'] ?? ($mapData[0]['id_kecamatan'] ?? 0));
    }

    foreach ($mapData as $item) {
        if ((int) $item['id_kecamatan'] === $selectedKecamatan) {
            $selectedRegion = $item;
            break;
        }
    }

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

    $mapConfigJson = json_encode(
        [
            'selectedKecamatan' => $selectedKecamatan,
            'tahunPrediksi' => $selectedTahun,
            'mode' => $selectedMode,
            'modeLabel' => $modeLabel,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($mapConfigJson === false) {
        $mapConfigJson = '{}';
    }
} catch (Throwable $exception) {
    error_log('Halaman Peta SIG Tahap 18 gagal: ' . $exception->getMessage());
    $backendError = 'Data Peta SIG belum dapat dimuat. Pastikan database Tahap 18 telah diimpor dengan lengkap.';
}
