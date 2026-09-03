<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Mencegah browser menampilkan data laporan lama
|--------------------------------------------------------------------------
*/
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/laporan.php';

/** @var mysqli $koneksi */

$active_menu = 'laporan';
$base_url = '../';

/*
|--------------------------------------------------------------------------
| Menentukan filter laporan
|--------------------------------------------------------------------------
*/
$jenisLaporan = laporan_normalize_jenis(
    $_GET['jenis_laporan'] ?? null
);

/*
|--------------------------------------------------------------------------
| Pilihan tahun dan tahun default
|--------------------------------------------------------------------------
| Tahun default mengikuti hasil prediksi yang paling terakhir disimpan,
| bukan sekadar tahun dengan angka terbesar.
|--------------------------------------------------------------------------
*/
$tahunOptions = laporan_get_tahun_options($koneksi);
$tahunDefault = laporan_get_tahun_default($koneksi);
$jumlahPrediksiPerTahun = laporan_get_jumlah_prediksi_per_tahun($koneksi);

$tahunPrediksi = isset($_GET['tahun_prediksi'])
    && (int) $_GET['tahun_prediksi'] > 0
        ? (int) $_GET['tahun_prediksi']
        : $tahunDefault;

if (!in_array($tahunPrediksi, $tahunOptions, true)) {
    array_unshift($tahunOptions, $tahunPrediksi);
    $tahunOptions = array_values(array_unique($tahunOptions));
}

$jumlahHasilTahunAktif = $jumlahPrediksiPerTahun[$tahunPrediksi] ?? 0;

$periodeOptions = laporan_get_periode_options(
    $koneksi,
    $tahunPrediksi
);

$kecamatanList = laporan_get_kecamatan($koneksi);

$idKecamatan = $_GET['id_kecamatan'] ?? 'all';

if (
    $idKecamatan !== 'all'
    && (!ctype_digit((string) $idKecamatan) || (int) $idKecamatan <= 0)
) {
    $idKecamatan = 'all';
}

$periode = $_GET['periode'] ?? ($periodeOptions[0] ?? '');

if (!in_array($periode, $periodeOptions, true)) {
    $periode = $periodeOptions[0] ?? '';
}

$filter = [
    'jenis_laporan' => $jenisLaporan,
    'tahun_prediksi' => $tahunPrediksi,
    'id_kecamatan' => $idKecamatan,
    'periode' => $periode,
];

/*
|--------------------------------------------------------------------------
| Mengambil data laporan dari hasil prediksi yang sudah tersimpan
|--------------------------------------------------------------------------
*/
$dataLaporan = laporan_get_laporan_data(
    $koneksi,
    $filter
);

$ringkasan = laporan_get_ringkasan(
    $dataLaporan
);

$chartSeries = laporan_get_chart_series(
    $dataLaporan,
    6
);

$periodeEfektif = laporan_format_periode_efektif(
    $periode,
    $tahunPrediksi
);

$namaAdmin = $_SESSION['nama_admin'] ?? 'Admin';
$queryString = http_build_query($filter);

$chartJson = json_encode(
    $chartSeries,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_HEX_TAG
    | JSON_HEX_AMP
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan | SIPREDIKSI GIS Sumba Timur</title>

    <link rel="stylesheet" href="<?= e($base_url); ?>style-sidebar.css">
    <link rel="stylesheet" href="<?= e($base_url); ?>style-header.css">
    <link
        rel="stylesheet"
        href="laporan.css?v=<?= filemtime(__DIR__ . '/laporan.css'); ?>"
    >
</head>
<body>
    <?php require_once __DIR__ . '/../sidebar.php'; ?>

    <main class="main-content">
        <?php
        $pageTitle = 'Laporan';
        $pageSubtitle = 'Cetak atau unduh laporan hasil prediksi jumlah penduduk miskin per kecamatan.';
        $pageIcon = '▤';
        require_once __DIR__ . '/../template-header.php';
        ?>

        <section class="page-header">
            <div>
                <h2>Laporan Data dan Hasil Prediksi</h2>
                <p>
                    Laporan hanya menggunakan hasil prediksi yang sudah disimpan
                    pada tabel hasil_prediksi.
                </p>
            </div>

            <div class="header-actions">
                <a
                    class="btn btn-light"
                    href="cetak-laporan.php?<?= e($queryString); ?>"
                    target="_blank"
                    rel="noopener"
                >
                    ▦ Cetak
                </a>

                <a
                    class="btn btn-green"
                    href="cetak-laporan.php?<?= e($queryString); ?>&amp;format=pdf"
                    target="_blank"
                    rel="noopener"
                >
                    ▧ Cetak / Simpan PDF
                </a>

                <a
                    class="btn btn-green"
                    href="cetak-laporan.php?<?= e($queryString); ?>&amp;format=excel"
                    target="_blank"
                    rel="noopener"
                >
                    ▤ Export Excel (.xls)
                </a>
            </div>
        </section>

        <?php if ($ringkasan['jumlah_valid'] === 0): ?>
            <section class="report-notice warning">
                <strong>Belum ada hasil prediksi tersimpan untuk filter ini.</strong>
                <span>
                    Simpan hasil pada menu Prediksi terlebih dahulu agar data dapat
                    muncul pada Dashboard, Peta SIG, dan Laporan.
                </span>
            </section>
        <?php elseif ($ringkasan['jumlah_belum_disimpan'] > 0): ?>
            <section class="report-notice info">
                <strong>
                    <?= angka_pendek($ringkasan['jumlah_belum_disimpan']); ?>
                    kecamatan belum memiliki hasil prediksi tersimpan.
                </strong>
                <span>
                    Kecamatan tersebut tetap ditampilkan, tetapi nilai prediksi dan
                    evaluasi model diberi tanda “-”.
                </span>
            </section>
        <?php endif; ?>

        <section class="filter-card">
            <form method="GET" class="filter-grid" id="filterForm">
                <div class="form-group">
                    <label for="jenis_laporan">Jenis Laporan</label>
                    <select name="jenis_laporan" id="jenis_laporan">
                        <option value="Laporan Prediksi per Kecamatan" selected>
                            Laporan Prediksi per Kecamatan
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tahun_prediksi">Tahun Prediksi</label>
                    <select name="tahun_prediksi" id="tahun_prediksi">
                        <?php foreach ($tahunOptions as $year): ?>
                            <?php
                            $jumlahHasilTahun = $jumlahPrediksiPerTahun[(int) $year] ?? 0;
                            ?>
                            <option
                                value="<?= (int) $year; ?>"
                                <?= (int) $tahunPrediksi === (int) $year ? 'selected' : ''; ?>
                            >
                                <?= (int) $year; ?>
                                — <?= angka_pendek($jumlahHasilTahun); ?> hasil
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_kecamatan">Kecamatan</label>
                    <select name="id_kecamatan" id="id_kecamatan">
                        <option
                            value="all"
                            <?= $idKecamatan === 'all' ? 'selected' : ''; ?>
                        >
                            Semua Kecamatan
                        </option>

                        <?php foreach ($kecamatanList as $kecamatan): ?>
                            <option
                                value="<?= (int) $kecamatan['id_kecamatan']; ?>"
                                <?= (string) $idKecamatan === (string) $kecamatan['id_kecamatan'] ? 'selected' : ''; ?>
                            >
                                <?= e($kecamatan['nama_kecamatan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="periode">Periode Data Historis</label>
                    <select name="periode" id="periode">
                        <?php foreach ($periodeOptions as $option): ?>
                            <option
                                value="<?= e($option); ?>"
                                <?= $periode === $option ? 'selected' : ''; ?>
                            >
                                <?= e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-blue">
                        ⌕ Tampilkan
                    </button>

                    <a href="index.php" class="btn btn-muted">
                        ↻ Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="report-notice info">
            <strong>
                Tahun <?= (int) $tahunPrediksi; ?> memiliki
                <?= angka_pendek($jumlahHasilTahunAktif); ?>
                hasil prediksi tersimpan.
            </strong>
            <span>
                Tabel menampilkan hasil dari tabel hasil_prediksi yang memiliki
                tahun prediksi sama dengan filter yang dipilih.
            </span>
        </section>

        <section class="summary-grid">
            <article class="summary-card blue">
                <div class="summary-icon">♙</div>
                <div>
                    <span>Jumlah Kecamatan</span>
                    <strong><?= angka_pendek($ringkasan['jumlah_kecamatan']); ?></strong>
                    <p>Total kecamatan yang tampil</p>
                </div>
            </article>

            <article class="summary-card green">
                <div class="summary-icon">↗</div>
                <div>
                    <span>Rata-rata Prediksi <?= (int) $tahunPrediksi; ?></span>
                    <strong>
                        <?= $ringkasan['jumlah_valid'] > 0
                            ? angka_pendek($ringkasan['rata_rata'])
                            : '-'; ?>
                    </strong>
                    <p><?= angka_pendek($ringkasan['jumlah_valid']); ?> hasil tersimpan</p>
                </div>
            </article>

            <article class="summary-card orange">
                <div class="summary-icon">▥</div>
                <div>
                    <span>Prediksi Tertinggi</span>
                    <strong>
                        <?= $ringkasan['tertinggi']
                            ? angka_pendek($ringkasan['tertinggi']['nilai_prediksi'])
                            : '-'; ?>
                    </strong>
                    <p>
                        <?= $ringkasan['tertinggi']
                            ? e($ringkasan['tertinggi']['nama_kecamatan'])
                            : 'Belum ada data'; ?>
                    </p>
                </div>
            </article>

            <article class="summary-card purple">
                <div class="summary-icon">↘</div>
                <div>
                    <span>Prediksi Terendah</span>
                    <strong>
                        <?= $ringkasan['terendah']
                            ? angka_pendek($ringkasan['terendah']['nilai_prediksi'])
                            : '-'; ?>
                    </strong>
                    <p>
                        <?= $ringkasan['terendah']
                            ? e($ringkasan['terendah']['nama_kecamatan'])
                            : 'Belum ada data'; ?>
                    </p>
                </div>
            </article>

            <article class="summary-card teal">
                <div class="summary-icon">👥</div>
                <div>
                    <span>Total Penduduk Kabupaten</span>
                    <strong>
                        <?= $ringkasan['total_penduduk_kabupaten'] !== null
                            ? angka_pendek($ringkasan['total_penduduk_kabupaten'])
                            : '-'; ?>
                    </strong>
                    <p>
                        <?= $ringkasan['tahun_data_penduduk'] !== null
                            ? 'Data penduduk tahun ' . e($ringkasan['tahun_data_penduduk'])
                            : 'Data penduduk belum tersedia'; ?>
                    </p>
                </div>
            </article>
        </section>

        <section class="content-grid">
            <article class="report-card table-card">
                <div class="card-header">
                    <h3>
                        Ringkasan Hasil Prediksi Tahun
                        <?= (int) $tahunPrediksi; ?> per Kecamatan
                    </h3>
                    <p>
                        Data historis efektif: <?= e($periodeEfektif); ?>
                    </p>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kecamatan</th>
                                <th>Total Penduduk<br>Kecamatan</th>
                                <th>Tahun Data<br>Penduduk</th>
                                <th>Data Terakhir<br>(Jiwa)</th>
                                <th>Prediksi <?= (int) $tahunPrediksi; ?><br>(Jiwa)</th>
                                <th>Perubahan<br>(Jiwa)</th>
                                <th>Persentase<br>Perubahan</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($dataLaporan) === 0): ?>
                                <tr>
                                    <td colspan="9" class="empty">
                                        Data laporan belum tersedia.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dataLaporan as $index => $row): ?>
                                    <?php
                                    $perubahan = $row['perubahan'];

                                    if ($row['nilai_prediksi'] === null) {
                                        $statusClass = 'neutral';
                                        $statusLabel = 'Belum disimpan';
                                    } elseif ($perubahan === null) {
                                        $statusClass = 'neutral';
                                        $statusLabel = 'Tanpa pembanding';
                                    } elseif ($perubahan >= 0) {
                                        $statusClass = 'up';
                                        $statusLabel = 'Naik';
                                    } else {
                                        $statusClass = 'down';
                                        $statusLabel = 'Turun';
                                    }
                                    ?>

                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td><?= e($row['nama_kecamatan']); ?></td>
                                        <td>
                                            <?= $row['jumlah_penduduk_kecamatan'] !== null
                                                ? angka_pendek($row['jumlah_penduduk_kecamatan'])
                                                : '-'; ?>
                                        </td>
                                        <td>
                                            <?= $row['tahun_data_penduduk'] !== null
                                                ? e($row['tahun_data_penduduk'])
                                                : '-'; ?>
                                        </td>
                                        <td><?= angka_pendek($row['data_aktual_terakhir']); ?></td>
                                        <td><?= angka_pendek($row['nilai_prediksi']); ?></td>
                                        <td class="<?= e($statusClass); ?>">
                                            <?php if ($perubahan === null): ?>
                                                -
                                            <?php else: ?>
                                                <?= $perubahan >= 0 ? '↑ ' : '↓ '; ?>
                                                <?= angka_pendek(abs($perubahan)); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $row['persentase_perubahan'] === null
                                                ? '-'
                                                : angka($row['persentase_perubahan'], 2) . '%'; ?>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= e($statusClass); ?>">
                                                <?= e($statusLabel); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span>
                        Menampilkan <?= angka_pendek(count($dataLaporan)); ?> data kecamatan
                    </span>
                    <span>
                        <?= angka_pendek($ringkasan['jumlah_valid']); ?> hasil prediksi tersimpan
                    </span>
                </div>
            </article>

            <aside class="side-stack">
                <article class="report-card chart-card">
                    <div class="card-header">
                        <h3>Grafik Perbandingan Data Aktual dan Prediksi</h3>
                        <p>Maksimal enam kecamatan dengan hasil tersimpan.</p>
                    </div>

                    <div class="chart-legend">
                        <span><i class="actual"></i> Data Aktual</span>
                        <span><i class="predict"></i> Prediksi</span>
                    </div>

                    <div
                        class="bar-chart"
                        id="barChart"
                        data-series='<?= e($chartJson ?: '[]'); ?>'
                    ></div>
                </article>

                <article class="report-card info-card">
                    <div class="card-header">
                        <h3>Informasi Laporan</h3>
                    </div>

                    <dl>
                        <div>
                            <dt>Jenis Laporan</dt>
                            <dd>: <?= e($jenisLaporan); ?></dd>
                        </div>
                        <div>
                            <dt>Tahun Prediksi</dt>
                            <dd>: <?= (int) $tahunPrediksi; ?></dd>
                        </div>
                        <div>
                            <dt>Periode Historis Efektif</dt>
                            <dd>: <?= e($periodeEfektif); ?></dd>
                        </div>
                        <div>
                            <dt>Hasil Prediksi Tersimpan</dt>
                            <dd>: <?= angka_pendek($ringkasan['jumlah_valid']); ?></dd>
                        </div>
                        <div>
                            <dt>Total Penduduk Kabupaten</dt>
                            <dd>
                                : <?= $ringkasan['total_penduduk_kabupaten'] !== null
                                    ? angka_pendek($ringkasan['total_penduduk_kabupaten']) . ' jiwa'
                                    : '-'; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Tahun Data Penduduk</dt>
                            <dd>
                                : <?= $ringkasan['tahun_data_penduduk'] !== null
                                    ? e($ringkasan['tahun_data_penduduk'])
                                    : '-'; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Sumber Data Penduduk</dt>
                            <dd>: <?= e($ringkasan['sumber_data_penduduk'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt>Tanggal Tampilan</dt>
                            <dd>: <?= e(tanggal_indonesia(null, true)); ?></dd>
                        </div>
                        <div>
                            <dt>Pengguna</dt>
                            <dd>: <?= e($namaAdmin); ?></dd>
                        </div>
                    </dl>
                </article>
            </aside>
        </section>

        <section class="note-box">
            <strong>i</strong>
            <span>
                Laporan hanya memuat hasil yang sudah disimpan pada menu Prediksi.
                Sistem tidak menghitung ulang regresi pada halaman Laporan.
            </span>
        </section>
    </main>

    <script src="<?= e($base_url); ?>script-sidebar.js"></script>
    <script src="laporan.js?v=<?= filemtime(__DIR__ . '/laporan.js'); ?>"></script>
</body>
</html>
