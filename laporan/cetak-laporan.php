<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Mencegah browser menggunakan hasil cetak lama
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

$jenisLaporan = laporan_normalize_jenis(
    $_GET['jenis_laporan'] ?? null
);

$tahunOptions = laporan_get_tahun_options($koneksi);
$tahunDefault = laporan_get_tahun_default($koneksi);

$tahunPrediksi = isset($_GET['tahun_prediksi'])
    && (int) $_GET['tahun_prediksi'] > 0
        ? (int) $_GET['tahun_prediksi']
        : $tahunDefault;

$periodeOptions = laporan_get_periode_options(
    $koneksi,
    $tahunPrediksi
);

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

$format = laporan_normalize_format(
    $_GET['format'] ?? 'cetak'
);

$filter = [
    'jenis_laporan' => $jenisLaporan,
    'tahun_prediksi' => $tahunPrediksi,
    'id_kecamatan' => $idKecamatan,
    'periode' => $periode,
];

$dataLaporan = laporan_get_laporan_data(
    $koneksi,
    $filter
);

$ringkasan = laporan_get_ringkasan(
    $dataLaporan
);

$periodeEfektif = laporan_format_periode_efektif(
    $periode,
    $tahunPrediksi
);

$namaAdmin = $_SESSION['nama_admin'] ?? 'Admin';
$idAdmin = (int) ($_SESSION['id_admin'] ?? 0);

/*
|--------------------------------------------------------------------------
| Menyimpan riwayat laporan
|--------------------------------------------------------------------------
| Riwayat hanya disimpan jika terdapat minimal satu hasil_prediksi.
| Hubungan ke hasil prediksi disimpan pada laporan_hasil_prediksi.
|--------------------------------------------------------------------------
*/
$idLaporan = laporan_simpan_riwayat(
    $koneksi,
    $idAdmin,
    $format,
    $dataLaporan
);

/*
|--------------------------------------------------------------------------
| Header export Excel sederhana
|--------------------------------------------------------------------------
| File masih berupa tabel HTML yang kompatibel dengan Excel .xls.
|--------------------------------------------------------------------------
*/
if ($format === 'excel') {
    ob_clean();

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="laporan-prediksi-'
        . $tahunPrediksi
        . '.xls"'
    );
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Prediksi <?= (int) $tahunPrediksi; ?></title>

    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 24px;
            background: #ffffff;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 18px;
        }

        .print-actions button {
            padding: 10px 16px;
            border: 1px solid #1455d9;
            border-radius: 7px;
            background: #1455d9;
            color: #ffffff;
            cursor: pointer;
            font-weight: 700;
        }

        .kop {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 3px solid #0f172a;
            text-align: center;
        }

        .kop h1 {
            margin: 0 0 6px;
            font-size: 20px;
            text-transform: uppercase;
        }

        .kop h2 {
            margin: 0 0 6px;
            font-size: 16px;
        }

        .kop p {
            margin: 0;
            color: #475569;
            font-size: 13px;
        }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        .box {
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
        }

        .box h3 {
            margin: 0 0 9px;
            font-size: 14px;
        }

        dl {
            margin: 0;
        }

        dl div {
            display: grid;
            grid-template-columns: 170px 1fr;
            gap: 8px;
            margin: 5px 0;
            font-size: 12px;
        }

        dt,
        dd {
            margin: 0;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }

        .summary .item {
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
        }

        .summary span {
            display: block;
            margin-bottom: 5px;
            color: #475569;
            font-size: 11px;
        }

        .summary strong {
            font-size: 19px;
        }

        .notice {
            margin-bottom: 16px;
            padding: 11px 13px;
            border: 1px solid #fed7aa;
            border-radius: 7px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 12px;
        }

        table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 7px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }

        .text-red {
            color: #dc2626;
        }

        .text-green {
            color: #15803d;
        }

        .signature {
            width: 260px;
            margin-top: 40px;
            margin-left: auto;
            text-align: center;
            font-size: 12px;
        }

        .signature .space {
            height: 58px;
        }

        .note {
            margin-top: 16px;
            color: #475569;
            font-size: 11px;
            line-height: 1.5;
        }

        @media print {
            body {
                margin: 0;
            }

            .print-actions {
                display: none;
            }

            .box,
            .summary .item,
            table,
            tr,
            td,
            th {
                break-inside: avoid;
            }
        }

        @media (max-width: 800px) {
            .meta,
            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if ($format !== 'excel'): ?>
        <div class="print-actions">
            <button type="button" onclick="window.print()">
                Cetak / Simpan PDF
            </button>
        </div>
    <?php endif; ?>

    <header class="kop">
        <h1>SIPREDIKSI GIS Sumba Timur</h1>
        <h2>Laporan Hasil Prediksi Jumlah Penduduk Miskin</h2>
        <p>Kabupaten Sumba Timur</p>
    </header>

    <?php if ($ringkasan['jumlah_valid'] === 0): ?>
        <div class="notice">
            Belum ada hasil prediksi tersimpan untuk filter laporan ini.
            Nilai prediksi tidak dihitung ulang pada halaman laporan.
        </div>
    <?php elseif ($ringkasan['jumlah_belum_disimpan'] > 0): ?>
        <div class="notice">
            <?= angka_pendek($ringkasan['jumlah_belum_disimpan']); ?>
            kecamatan belum memiliki hasil prediksi tersimpan dan ditampilkan
            dengan tanda “-”.
        </div>
    <?php endif; ?>

    <section class="meta">
        <div class="box">
            <h3>Informasi Laporan</h3>
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
                    <dt>Tanggal Cetak</dt>
                    <dd>: <?= e(tanggal_indonesia(null, true)); ?></dd>
                </div>
                <div>
                    <dt>Dicetak oleh</dt>
                    <dd>: <?= e($namaAdmin); ?></dd>
                </div>
                <div>
                    <dt>ID Riwayat Laporan</dt>
                    <dd>: <?= $idLaporan !== null ? (int) $idLaporan : '-'; ?></dd>
                </div>
            </dl>
        </div>

        <div class="box">
            <h3>Ringkasan</h3>
            <dl>
                <div>
                    <dt>Jumlah Kecamatan</dt>
                    <dd>: <?= angka_pendek($ringkasan['jumlah_kecamatan']); ?></dd>
                </div>
                <div>
                    <dt>Hasil Prediksi Tersimpan</dt>
                    <dd>: <?= angka_pendek($ringkasan['jumlah_valid']); ?></dd>
                </div>
                <div>
                    <dt>Total Prediksi</dt>
                    <dd>
                        : <?= $ringkasan['jumlah_valid'] > 0
                            ? angka_pendek($ringkasan['total_prediksi']) . ' jiwa'
                            : '-'; ?>
                    </dd>
                </div>
                <div>
                    <dt>Rata-rata Prediksi</dt>
                    <dd>
                        : <?= $ringkasan['jumlah_valid'] > 0
                            ? angka_pendek($ringkasan['rata_rata']) . ' jiwa'
                            : '-'; ?>
                    </dd>
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
            </dl>
        </div>
    </section>

    <section class="summary">
        <div class="item">
            <span>Prediksi Tertinggi</span>
            <strong>
                <?= $ringkasan['tertinggi']
                    ? angka_pendek($ringkasan['tertinggi']['nilai_prediksi'])
                    : '-'; ?>
            </strong>
            <br>
            <?= $ringkasan['tertinggi']
                ? e($ringkasan['tertinggi']['nama_kecamatan'])
                : '-'; ?>
        </div>

        <div class="item">
            <span>Prediksi Terendah</span>
            <strong>
                <?= $ringkasan['terendah']
                    ? angka_pendek($ringkasan['terendah']['nilai_prediksi'])
                    : '-'; ?>
            </strong>
            <br>
            <?= $ringkasan['terendah']
                ? e($ringkasan['terendah']['nama_kecamatan'])
                : '-'; ?>
        </div>

        <div class="item">
            <span>Jumlah Data Tampil</span>
            <strong><?= angka_pendek(count($dataLaporan)); ?></strong>
            <br>kecamatan
        </div>

        <div class="item">
            <span>Tahun Prediksi</span>
            <strong><?= (int) $tahunPrediksi; ?></strong>
            <br>periode laporan
        </div>
    </section>

    <section>
        <h3>Rincian Hasil Prediksi per Kecamatan</h3>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Kecamatan</th>
                    <th class="text-right">Penduduk Kecamatan</th>
                    <th class="text-right">Tahun Penduduk</th>
                    <th class="text-right">Data Terakhir</th>
                    <th class="text-right">Prediksi <?= (int) $tahunPrediksi; ?></th>
                    <th class="text-right">Perubahan</th>
                    <th class="text-right">Persentase</th>
                    <th class="text-right">MAE</th>
                    <th class="text-right">RMSE</th>
                    <th class="text-right">R²</th>
                    <th>Sumber</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($dataLaporan) === 0): ?>
                    <tr>
                        <td colspan="13" style="text-align: center;">
                            Data laporan belum tersedia.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dataLaporan as $index => $row): ?>
                        <?php
                        $perubahanClass = '';

                        if ($row['perubahan'] !== null) {
                            $perubahanClass = $row['perubahan'] >= 0
                                ? 'text-red'
                                : 'text-green';
                        }
                        ?>

                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td><?= e($row['kode_kecamatan']); ?></td>
                            <td><?= e($row['nama_kecamatan']); ?></td>
                            <td class="text-right">
                                <?= $row['jumlah_penduduk_kecamatan'] !== null
                                    ? angka_pendek($row['jumlah_penduduk_kecamatan'])
                                    : '-'; ?>
                            </td>
                            <td class="text-right">
                                <?= $row['tahun_data_penduduk'] !== null
                                    ? e($row['tahun_data_penduduk'])
                                    : '-'; ?>
                            </td>
                            <td class="text-right">
                                <?= angka_pendek($row['data_aktual_terakhir']); ?>
                            </td>
                            <td class="text-right">
                                <?= angka_pendek($row['nilai_prediksi']); ?>
                            </td>
                            <td class="text-right <?= e($perubahanClass); ?>">
                                <?= $row['perubahan'] === null
                                    ? '-'
                                    : (($row['perubahan'] >= 0 ? '+' : '-')
                                        . angka_pendek(abs($row['perubahan']))); ?>
                            </td>
                            <td class="text-right <?= e($perubahanClass); ?>">
                                <?= $row['persentase_perubahan'] === null
                                    ? '-'
                                    : (($row['persentase_perubahan'] >= 0 ? '+' : '-')
                                        . angka(abs($row['persentase_perubahan']), 2)
                                        . '%'); ?>
                            </td>
                            <td class="text-right"><?= angka($row['mae'], 2); ?></td>
                            <td class="text-right"><?= angka($row['rmse'], 2); ?></td>
                            <td class="text-right"><?= angka($row['r2'], 4); ?></td>
                            <td><?= e(laporan_label_sumber($row['sumber'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <p class="note">
        Catatan: laporan hanya menggunakan hasil prediksi yang sudah disimpan
        pada tabel <strong>hasil_prediksi</strong>. Kecamatan tanpa hasil
        tersimpan tidak dihitung ulang dan ditampilkan dengan tanda “-”.
    </p>

    <?php if ($format !== 'excel'): ?>
        <div class="signature">
            <p>Waingapu, <?= e(tanggal_indonesia()); ?></p>
            <p>Admin Sistem,</p>
            <div class="space"></div>
            <strong><?= e($namaAdmin); ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($format === 'pdf'): ?>
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    <?php endif; ?>
</body>
</html>
