<?php
/**
 * Backend helper halaman Laporan SIPREDIKSI GIS Sumba Timur.
 *
 * Fungsi utama file ini:
 * 1. menyediakan helper format teks, angka, dan tanggal;
 * 2. mengambil filter tahun, periode, dan kecamatan;
 * 3. membaca hasil prediksi yang SUDAH TERSIMPAN dari tabel hasil_prediksi;
 * 4. mengambil data historis dan data penduduk sebagai data pembanding;
 * 5. membuat ringkasan serta data grafik;
 * 6. menyimpan riwayat cetak/export ke tabel laporan dan laporan_hasil_prediksi.
 */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('angka')) {
    function angka($value, int $decimal = 0): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimal, ',', '.');
    }
}

if (!function_exists('angka_pendek')) {
    function angka_pendek($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 0, ',', '.');
    }
}

if (!function_exists('tanggal_indonesia')) {
    function tanggal_indonesia(?string $tanggal = null, bool $withTime = false): string
    {
        $bulan = [
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember',
        ];

        $time = $tanggal ? strtotime($tanggal) : time();

        if ($time === false) {
            $time = time();
        }

        $hasil = date('d', $time)
            . ' '
            . $bulan[(int) date('n', $time)]
            . ' '
            . date('Y', $time);

        if ($withTime) {
            $hasil .= ' ' . date('H:i', $time);
        }

        return $hasil;
    }
}

/**
 * Memastikan jenis laporan hanya menggunakan jenis yang sudah didukung.
 */
function laporan_normalize_jenis(?string $jenis): string
{
    $supported = [
        'Laporan Prediksi per Kecamatan',
    ];

    return in_array($jenis, $supported, true)
        ? $jenis
        : $supported[0];
}

/**
 * Memastikan format riwayat laporan hanya cetak, pdf, atau excel.
 */
function laporan_normalize_format(?string $format): string
{
    $supported = ['cetak', 'pdf', 'excel'];

    return in_array($format, $supported, true)
        ? $format
        : 'cetak';
}

/**
 * Mengubah kode sumber internal menjadi teks yang ramah pengguna.
 */
function laporan_label_sumber(?string $sumber): string
{
    if ($sumber === 'hasil_prediksi') {
        return 'Hasil prediksi tersimpan';
    }

    if ($sumber === 'belum_disimpan') {
        return 'Belum tersimpan';
    }

    return $sumber ?: '-';
}

/**
 * Mengambil seluruh kecamatan untuk filter laporan.
 */
function laporan_get_kecamatan(mysqli $koneksi): array
{
    $items = [];

    $sql = "SELECT id_kecamatan, kode_kecamatan, nama_kecamatan
            FROM kecamatan
            ORDER BY nama_kecamatan ASC";

    $result = mysqli_query($koneksi, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }

    return $items;
}

/**
 * Mengambil daftar tahun prediksi yang sudah tersimpan.
 * Jika belum ada hasil tersimpan, sistem tetap menyediakan satu tahun
 * setelah tahun data historis terakhir sebagai pilihan kosong.
 */
function laporan_get_tahun_options(mysqli $koneksi): array
{
    $years = [];

    /*
    |--------------------------------------------------------------------------
    | Mengurutkan tahun berdasarkan hasil prediksi yang terakhir disimpan
    |--------------------------------------------------------------------------
    | Tahun yang paling baru digunakan pada proses simpan akan berada di atas,
    | meskipun angka tahunnya bukan yang terbesar.
    |--------------------------------------------------------------------------
    */
    $sqlPrediksi = "SELECT
                        tahun_prediksi AS tahun,
                        MAX(id_prediksi) AS id_terakhir
                    FROM hasil_prediksi
                    WHERE tahun_prediksi IS NOT NULL
                    GROUP BY tahun_prediksi
                    ORDER BY id_terakhir DESC, tahun_prediksi DESC";

    $resultPrediksi = mysqli_query($koneksi, $sqlPrediksi);

    if ($resultPrediksi) {
        while ($row = mysqli_fetch_assoc($resultPrediksi)) {
            if (!empty($row['tahun'])) {
                $years[] = (int) $row['tahun'];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fallback ketika hasil_prediksi masih kosong
    |--------------------------------------------------------------------------
    */
    if (count($years) === 0) {
        $sqlData = "SELECT MAX(tahun) AS tahun_terakhir
                    FROM data_kemiskinan";

        $resultData = mysqli_query($koneksi, $sqlData);
        $rowData = $resultData ? mysqli_fetch_assoc($resultData) : null;

        $tahunTerakhir = !empty($rowData['tahun_terakhir'])
            ? (int) $rowData['tahun_terakhir']
            : (int) date('Y');

        $years[] = $tahunTerakhir + 1;
    }

    return array_values(array_unique($years));
}

/**
 * Mengambil tahun dari hasil prediksi yang paling terakhir disimpan.
 */
function laporan_get_tahun_default(mysqli $koneksi): int
{
    $sql = "SELECT tahun_prediksi
            FROM hasil_prediksi
            WHERE tahun_prediksi IS NOT NULL
            ORDER BY id_prediksi DESC
            LIMIT 1";

    $result = mysqli_query($koneksi, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    if (
        $row
        && isset($row['tahun_prediksi'])
        && (int) $row['tahun_prediksi'] > 0
    ) {
        return (int) $row['tahun_prediksi'];
    }

    $sqlHistoris = "SELECT MAX(tahun) AS tahun_terakhir
                    FROM data_kemiskinan";

    $resultHistoris = mysqli_query($koneksi, $sqlHistoris);
    $rowHistoris = $resultHistoris
        ? mysqli_fetch_assoc($resultHistoris)
        : null;

    if (!empty($rowHistoris['tahun_terakhir'])) {
        return (int) $rowHistoris['tahun_terakhir'] + 1;
    }

    return (int) date('Y');
}

/**
 * Menghitung jumlah hasil prediksi yang tersimpan pada setiap tahun.
 *
 * Bentuk hasil:
 * [
 *     2025 => 15,
 *     2026 => 2
 * ]
 */
function laporan_get_jumlah_prediksi_per_tahun(mysqli $koneksi): array
{
    $jumlah = [];

    $sql = "SELECT
                tahun_prediksi,
                COUNT(*) AS total
            FROM hasil_prediksi
            WHERE tahun_prediksi IS NOT NULL
            GROUP BY tahun_prediksi";

    $result = mysqli_query($koneksi, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $jumlah[(int) $row['tahun_prediksi']] = (int) $row['total'];
        }
    }

    return $jumlah;
}

/**
 * Mengambil pilihan periode historis yang berakhir sebelum tahun prediksi.
 */
function laporan_get_periode_options(
    mysqli $koneksi,
    ?int $tahunPrediksi = null
): array {
    $options = [];

    $sql = "SELECT MIN(tahun) AS tahun_awal,
                   MAX(tahun) AS tahun_akhir
            FROM data_kemiskinan";

    $result = mysqli_query($koneksi, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    if (!empty($row['tahun_awal']) && !empty($row['tahun_akhir'])) {
        $awal = (int) $row['tahun_awal'];
        $akhirData = (int) $row['tahun_akhir'];

        $akhir = $tahunPrediksi !== null && $tahunPrediksi > 0
            ? min($akhirData, $tahunPrediksi - 1)
            : $akhirData;

        if ($akhir < $awal) {
            $akhir = $awal;
        }

        $options[] = $awal . ' - ' . $akhir;

        if (($akhir - $awal) >= 2) {
            $options[] = $awal . ' - ' . ($akhir - 1);
        }
    } else {
        $tahun = $tahunPrediksi ?: (int) date('Y');
        $options[] = ($tahun - 4) . ' - ' . ($tahun - 1);
    }

    return array_values(array_unique($options));
}

/**
 * Membaca teks periode seperti "2022 - 2024".
 */
function laporan_parse_periode(?string $periode): array
{
    if (!$periode) {
        return [null, null];
    }

    if (!preg_match('/^\s*(\d{4})\s*-\s*(\d{4})\s*$/', $periode, $matches)) {
        return [null, null];
    }

    $awal = (int) $matches[1];
    $akhir = (int) $matches[2];

    if ($akhir < $awal) {
        [$awal, $akhir] = [$akhir, $awal];
    }

    return [$awal, $akhir];
}

/**
 * Menentukan periode historis efektif.
 * Tahun akhir tidak boleh sama atau melewati tahun prediksi.
 */
function laporan_get_periode_efektif(
    ?string $periode,
    int $tahunPrediksi
): array {
    [$tahunAwal, $tahunAkhir] = laporan_parse_periode($periode);

    $batasAkhir = $tahunPrediksi - 1;

    if ($tahunAkhir === null || $tahunAkhir > $batasAkhir) {
        $tahunAkhir = $batasAkhir;
    }

    if ($tahunAwal !== null && $tahunAkhir < $tahunAwal) {
        $tahunAwal = null;
        $tahunAkhir = $batasAkhir;
    }

    return [$tahunAwal, $tahunAkhir];
}

/**
 * Membentuk label periode efektif untuk ditampilkan.
 */
function laporan_format_periode_efektif(
    ?string $periode,
    int $tahunPrediksi
): string {
    [$awal, $akhir] = laporan_get_periode_efektif(
        $periode,
        $tahunPrediksi
    );

    if ($awal === null && $akhir === null) {
        return '-';
    }

    if ($awal === null) {
        return 'Sampai ' . $akhir;
    }

    return $awal . ' - ' . $akhir;
}

/**
 * Mengambil data penduduk kecamatan terakhir yang tersedia sampai tahun referensi.
 */
function laporan_get_data_penduduk(
    mysqli $koneksi,
    int $idKecamatan,
    int $tahunReferensi
): ?array {
    $sql = "SELECT tahun,
                   jumlah_penduduk_kecamatan,
                   jumlah_penduduk_kabupaten,
                   sumber_data
            FROM data_penduduk
            WHERE id_kecamatan = ?
              AND jenis_data = 'kecamatan'
              AND tahun <= ?
            ORDER BY tahun DESC
            LIMIT 1";

    $stmt = mysqli_prepare($koneksi, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ii',
        $idKecamatan,
        $tahunReferensi
    );

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    return [
        'tahun_data_penduduk' => (int) $row['tahun'],
        'jumlah_penduduk_kecamatan' => $row['jumlah_penduduk_kecamatan'] !== null
            ? (float) $row['jumlah_penduduk_kecamatan']
            : null,
        'jumlah_penduduk_kabupaten' => $row['jumlah_penduduk_kabupaten'] !== null
            ? (float) $row['jumlah_penduduk_kabupaten']
            : null,
        'sumber_data_penduduk' => $row['sumber_data'] ?? '-',
    ];
}

/**
 * Mengambil data historis satu kecamatan untuk periode tertentu.
 */
function laporan_get_historis(
    mysqli $koneksi,
    int $idKecamatan,
    ?int $tahunAwal = null,
    ?int $tahunAkhir = null
): array {
    $where = 'WHERE id_kecamatan = ?';

    if ($tahunAwal !== null) {
        $where .= ' AND tahun >= ?';
    }

    if ($tahunAkhir !== null) {
        $where .= ' AND tahun <= ?';
    }

    $sql = "SELECT tahun, jumlah_penduduk_miskin
            FROM data_kemiskinan
            $where
            ORDER BY tahun ASC";

    $stmt = mysqli_prepare($koneksi, $sql);

    if (!$stmt) {
        return [];
    }

    if ($tahunAwal !== null && $tahunAkhir !== null) {
        mysqli_stmt_bind_param(
            $stmt,
            'iii',
            $idKecamatan,
            $tahunAwal,
            $tahunAkhir
        );
    } elseif ($tahunAwal !== null) {
        mysqli_stmt_bind_param(
            $stmt,
            'ii',
            $idKecamatan,
            $tahunAwal
        );
    } elseif ($tahunAkhir !== null) {
        mysqli_stmt_bind_param(
            $stmt,
            'ii',
            $idKecamatan,
            $tahunAkhir
        );
    } else {
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $idKecamatan
        );
    }

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'tahun' => (int) $row['tahun'],
            'jumlah_penduduk_miskin' => (float) $row['jumlah_penduduk_miskin'],
        ];
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Mengambil data laporan.
 *
 * Penting:
 * - nilai prediksi hanya dibaca dari tabel hasil_prediksi;
 * - laporan tidak menghitung regresi ulang;
 * - kecamatan tanpa hasil tersimpan tetap ditampilkan dengan nilai kosong.
 */
function laporan_get_laporan_data(
    mysqli $koneksi,
    array $filter
): array {
    $tahunPrediksi = (int) ($filter['tahun_prediksi'] ?? date('Y'));
    $idKecamatan = $filter['id_kecamatan'] ?? 'all';

    [$tahunAwal, $tahunAkhir] = laporan_get_periode_efektif(
        $filter['periode'] ?? null,
        $tahunPrediksi
    );

    $where = '';

    if (
        $idKecamatan !== 'all'
        && $idKecamatan !== ''
        && $idKecamatan !== null
    ) {
        $where = 'WHERE k.id_kecamatan = ?';
    }

    $sql = "SELECT
                k.id_kecamatan,
                k.kode_kecamatan,
                k.nama_kecamatan,
                hp.id_prediksi,
                hp.id_prediksi_regresi,
                hp.tahun_prediksi,
                hp.nilai_prediksi,
                hp.intercept,
                hp.slope,
                hp.mae,
                hp.rmse,
                hp.r2
            FROM kecamatan AS k
            LEFT JOIN hasil_prediksi AS hp
                   ON hp.id_kecamatan = k.id_kecamatan
                  AND hp.tahun_prediksi = ?
            $where
            ORDER BY k.nama_kecamatan ASC";

    $stmt = mysqli_prepare($koneksi, $sql);

    if (!$stmt) {
        return [];
    }

    if ($idKecamatan !== 'all' && $idKecamatan !== '' && $idKecamatan !== null) {
        $idKecamatanInt = (int) $idKecamatan;

        mysqli_stmt_bind_param(
            $stmt,
            'ii',
            $tahunPrediksi,
            $idKecamatanInt
        );
    } else {
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $tahunPrediksi
        );
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $id = (int) $row['id_kecamatan'];

        $historis = laporan_get_historis(
            $koneksi,
            $id,
            $tahunAwal,
            $tahunAkhir
        );

        $lastData = count($historis) > 0
            ? $historis[array_key_last($historis)]
            : null;

        $dataAktualTerakhir = $lastData !== null
            ? (float) $lastData['jumlah_penduduk_miskin']
            : null;

        $tahunAktualTerakhir = $lastData !== null
            ? (int) $lastData['tahun']
            : null;

        if ($row['nilai_prediksi'] !== null) {
            $nilaiPrediksi = (float) $row['nilai_prediksi'];

            $delta = $dataAktualTerakhir !== null
                ? $nilaiPrediksi - $dataAktualTerakhir
                : null;

            $deltaPersen = (
                $dataAktualTerakhir !== null
                && $dataAktualTerakhir > 0
                && $delta !== null
            )
                ? ($delta / $dataAktualTerakhir) * 100
                : null;

            $row['sumber'] = 'hasil_prediksi';
        } else {
            $nilaiPrediksi = null;
            $delta = null;
            $deltaPersen = null;

            $row['id_prediksi'] = null;
            $row['id_prediksi_regresi'] = null;
            $row['tahun_prediksi'] = $tahunPrediksi;
            $row['nilai_prediksi'] = null;
            $row['intercept'] = null;
            $row['slope'] = null;
            $row['mae'] = null;
            $row['rmse'] = null;
            $row['r2'] = null;
            $row['sumber'] = 'belum_disimpan';
        }

        $dataPenduduk = laporan_get_data_penduduk(
            $koneksi,
            $id,
            $tahunPrediksi
        );

        $row['nilai_prediksi'] = $nilaiPrediksi;
        $row['data_aktual_terakhir'] = $dataAktualTerakhir;
        $row['tahun_aktual_terakhir'] = $tahunAktualTerakhir;
        $row['perubahan'] = $delta;
        $row['persentase_perubahan'] = $deltaPersen;
        $row['jumlah_data_historis'] = count($historis);
        $row['historis'] = $historis;
        $row['periode_historis_efektif'] = laporan_format_periode_efektif(
            $filter['periode'] ?? null,
            $tahunPrediksi
        );

        $row['jumlah_penduduk_kecamatan'] = $dataPenduduk['jumlah_penduduk_kecamatan'] ?? null;
        $row['jumlah_penduduk_kabupaten'] = $dataPenduduk['jumlah_penduduk_kabupaten'] ?? null;
        $row['tahun_data_penduduk'] = $dataPenduduk['tahun_data_penduduk'] ?? null;
        $row['sumber_data_penduduk'] = $dataPenduduk['sumber_data_penduduk'] ?? '-';

        $data[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Membuat ringkasan laporan hanya dari hasil prediksi yang tersimpan.
 */
function laporan_get_ringkasan(array $data): array
{
    $valid = array_values(
        array_filter(
            $data,
            static function (array $row): bool {
                return array_key_exists('nilai_prediksi', $row)
                    && $row['nilai_prediksi'] !== null;
            }
        )
    );

    $jumlahKecamatan = count($data);
    $jumlahValid = count($valid);

    $totalPrediksi = 0.0;

    foreach ($valid as $row) {
        $totalPrediksi += (float) $row['nilai_prediksi'];
    }

    $rataRata = $jumlahValid > 0
        ? $totalPrediksi / $jumlahValid
        : 0.0;

    usort(
        $valid,
        static function (array $a, array $b): int {
            return ((float) $b['nilai_prediksi'])
                <=> ((float) $a['nilai_prediksi']);
        }
    );

    $tertinggi = $valid[0] ?? null;
    $terendah = $jumlahValid > 0
        ? $valid[$jumlahValid - 1]
        : null;

    $totalPendudukKecamatanTampil = 0.0;
    $jumlahDataPendudukKecamatan = 0;
    $totalPendudukKabupaten = null;
    $tahunDataPenduduk = null;
    $sumberDataPenduduk = '-';

    foreach ($data as $row) {
        if (
            isset($row['jumlah_penduduk_kecamatan'])
            && $row['jumlah_penduduk_kecamatan'] !== null
            && $row['jumlah_penduduk_kecamatan'] !== ''
        ) {
            $totalPendudukKecamatanTampil += (float) $row['jumlah_penduduk_kecamatan'];
            $jumlahDataPendudukKecamatan++;
        }

        if (
            isset($row['jumlah_penduduk_kabupaten'])
            && $row['jumlah_penduduk_kabupaten'] !== null
            && $row['jumlah_penduduk_kabupaten'] !== ''
        ) {
            $tahunRow = isset($row['tahun_data_penduduk'])
                && $row['tahun_data_penduduk'] !== null
                    ? (int) $row['tahun_data_penduduk']
                    : 0;

            if (
                $tahunDataPenduduk === null
                || $tahunRow >= (int) $tahunDataPenduduk
            ) {
                $totalPendudukKabupaten = (float) $row['jumlah_penduduk_kabupaten'];
                $tahunDataPenduduk = $tahunRow > 0 ? $tahunRow : null;
                $sumberDataPenduduk = $row['sumber_data_penduduk'] ?? '-';
            }
        }
    }

    return [
        'jumlah_kecamatan' => $jumlahKecamatan,
        'jumlah_valid' => $jumlahValid,
        'jumlah_belum_disimpan' => max(0, $jumlahKecamatan - $jumlahValid),
        'total_prediksi' => $totalPrediksi,
        'rata_rata' => $rataRata,
        'tertinggi' => $tertinggi,
        'terendah' => $terendah,
        'total_penduduk_kecamatan_tampil' => $totalPendudukKecamatanTampil,
        'jumlah_data_penduduk_kecamatan' => $jumlahDataPendudukKecamatan,
        'total_penduduk_kabupaten' => $totalPendudukKabupaten,
        'tahun_data_penduduk' => $tahunDataPenduduk,
        'sumber_data_penduduk' => $sumberDataPenduduk,
    ];
}

/**
 * Menyiapkan maksimal sejumlah kecamatan untuk grafik.
 * Baris tanpa prediksi tersimpan dilewati.
 */
function laporan_get_chart_series(array $data, int $limit = 6): array
{
    $series = [];

    foreach ($data as $row) {
        if (
            !array_key_exists('nilai_prediksi', $row)
            || $row['nilai_prediksi'] === null
        ) {
            continue;
        }

        $series[] = [
            'label' => (string) $row['nama_kecamatan'],
            'aktual' => $row['data_aktual_terakhir'] !== null
                ? (float) $row['data_aktual_terakhir']
                : null,
            'prediksi' => (float) $row['nilai_prediksi'],
        ];

        if (count($series) >= $limit) {
            break;
        }
    }

    return $series;
}

/**
 * Menyimpan riwayat laporan dan hubungan ke hasil prediksi yang dicetak.
 */
function laporan_simpan_riwayat(
    mysqli $koneksi,
    int $idAdmin,
    string $formatLaporan,
    array $dataLaporan
): ?int {
    if ($idAdmin <= 0) {
        return null;
    }

    $formatLaporan = laporan_normalize_format($formatLaporan);

    $idPrediksiList = [];

    foreach ($dataLaporan as $row) {
        $idPrediksi = (int) ($row['id_prediksi'] ?? 0);

        if ($idPrediksi > 0) {
            $idPrediksiList[$idPrediksi] = $idPrediksi;
        }
    }

    if (count($idPrediksiList) === 0) {
        return null;
    }

    mysqli_begin_transaction($koneksi);

    try {
        $sqlLaporan = "INSERT INTO laporan (
                           id_admin,
                           format_laporan
                       ) VALUES (?, ?)";

        $stmtLaporan = mysqli_prepare($koneksi, $sqlLaporan);

        if (!$stmtLaporan) {
            throw new RuntimeException('Query riwayat laporan gagal disiapkan.');
        }

        mysqli_stmt_bind_param(
            $stmtLaporan,
            'is',
            $idAdmin,
            $formatLaporan
        );

        mysqli_stmt_execute($stmtLaporan);

        $idLaporan = (int) mysqli_insert_id($koneksi);

        mysqli_stmt_close($stmtLaporan);

        if ($idLaporan <= 0) {
            throw new RuntimeException('ID laporan tidak berhasil diperoleh.');
        }

        $sqlDetail = "INSERT INTO laporan_hasil_prediksi (
                          id_laporan,
                          id_prediksi
                      ) VALUES (?, ?)";

        $stmtDetail = mysqli_prepare($koneksi, $sqlDetail);

        if (!$stmtDetail) {
            throw new RuntimeException('Query detail laporan gagal disiapkan.');
        }

        foreach ($idPrediksiList as $idPrediksi) {
            mysqli_stmt_bind_param(
                $stmtDetail,
                'ii',
                $idLaporan,
                $idPrediksi
            );

            mysqli_stmt_execute($stmtDetail);
        }

        mysqli_stmt_close($stmtDetail);
        mysqli_commit($koneksi);

        return $idLaporan;
    } catch (Throwable $e) {
        mysqli_rollback($koneksi);

        error_log(
            'Gagal menyimpan riwayat laporan: '
            . $e->getMessage()
        );

        return null;
    }
}
