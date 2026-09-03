<?php
require_once __DIR__ . '/backend.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediksi Kelompok Kesejahteraan Rendah</title>

    <link rel="stylesheet" href="../style-sidebar.css">
    <link rel="stylesheet" href="../style-header.css">
    <link rel="stylesheet" href="style-prediksi.css?v=4">
</head>
<body>
<?php include __DIR__ . '/../sidebar.php'; ?>

<main class="main-content prediction-page">
    <?php
    $pageTitle = 'Prediksi Kelompok Kesejahteraan Rendah';
    $pageSubtitle = 'Estimasi tahun 2026 menggunakan Model C Final berbasis regresi linier data panel.';
    $pageIcon = '📈';
    require_once __DIR__ . '/../template-header.php';
    ?>

    <?php if ($backendError !== null): ?>
        <div class="page-alert page-alert-error">
            <?= prediksi_e($backendError); ?>
        </div>
    <?php endif; ?>

    <section class="model-banner" aria-label="Status model">
        <div>
            <span class="banner-eyebrow">Model aktif</span>
            <strong><?= prediksi_e($model['nama_model'] ?? 'Model C Final'); ?></strong>
            <p>
                Data tahun 2022 sampai 2025 digunakan sebagai dasar model. Hasil tahun
                <?= prediksi_e($selectedTahun); ?> berstatus estimasi eksploratif.
            </p>
        </div>

        <div class="banner-chips">
            <span class="info-chip chip-blue"><?= prediksi_e($prediction['kode_model'] ?? 'C_FINAL'); ?></span>
            <span class="info-chip chip-green">Skenario <?= prediksi_e($prediction['kode_skenario'] ?? 'HIBRIDA'); ?></span>
            <span class="info-chip chip-amber"><?= prediksi_e($prediction['status_hasil'] ?? 'Estimasi eksploratif'); ?></span>
        </div>
    </section>

    <section class="prediction-summary-grid">
        <article class="filter-card card-shell">
            <div class="card-heading">
                <div>
                    <span class="section-kicker">Filter tampilan</span>
                    <h2>Pilih Wilayah</h2>
                </div>
            </div>

            <form method="GET" action="index.php" class="filter-form" id="predictionFilterForm">
                <div class="form-group">
                    <label for="id_kecamatan">Kecamatan</label>
                    <select name="id_kecamatan" id="id_kecamatan" required>
                        <?php foreach ($kecamatanList as $district): ?>
                            <option
                                value="<?= (int) $district['id_kecamatan']; ?>"
                                <?= (int) $district['id_kecamatan'] === $selectedKecamatan ? 'selected' : ''; ?>
                            >
                                <?= prediksi_e($district['nama_kecamatan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tahun_prediksi">Tahun Prediksi</label>
                    <select name="tahun_prediksi" id="tahun_prediksi" required>
                        <?php foreach ($tahunList as $year): ?>
                            <option value="<?= (int) $year; ?>" <?= (int) $year === $selectedTahun ? 'selected' : ''; ?>>
                                <?= (int) $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary">
                    <span aria-hidden="true">↗</span>
                    Tampilkan Prediksi
                </button>
            </form>
        </article>

        <article class="summary-card card-shell">
            <div class="summary-icon icon-database">▤</div>
            <div>
                <span>Data Aktual</span>
                <strong><?= prediksi_bulat($observationCount); ?></strong>
                <small><?= prediksi_e($periodLabel); ?></small>
            </div>
        </article>

        <article class="summary-card card-shell">
            <div class="summary-icon icon-actual">A</div>
            <div>
                <span>Aktual 2025</span>
                <strong><?= prediksi_angka($actual2025Percent, 2); ?>%</strong>
                <small><?= prediksi_bulat($actual2025Count); ?> individu</small>
            </div>
        </article>

        <article class="summary-card card-shell">
            <div class="summary-icon icon-percent">%</div>
            <div>
                <span>Prediksi <?= prediksi_e($selectedTahun); ?></span>
                <strong><?= prediksi_angka($predictionPercent, 2); ?>%</strong>
                <small>Persentase operasional</small>
            </div>
        </article>

        <article class="summary-card card-shell">
            <div class="summary-icon icon-people">👥</div>
            <div>
                <span>Prediksi Individu</span>
                <strong><?= prediksi_bulat($predictionCount); ?></strong>
                <small>Dari <?= prediksi_bulat($projectedPopulation); ?> penduduk</small>
            </div>
        </article>
    </section>

    <section class="prediction-main-grid">
        <article class="model-card card-shell">
            <div class="card-heading card-heading-split">
                <div>
                    <span class="section-kicker">Spesifikasi model</span>
                    <h2>Informasi Model dan Input 2026</h2>
                </div>
                <span class="model-code"><?= prediksi_e($model['kode_model'] ?? '-'); ?></span>
            </div>

            <div class="model-meta-grid">
                <div class="meta-item">
                    <span>Jenis model</span>
                    <strong><?= prediksi_e($model['jenis_model'] ?? '-'); ?></strong>
                </div>
                <div class="meta-item">
                    <span>Periode pelatihan</span>
                    <strong><?= prediksi_e($model['periode_pelatihan'] ?? '-'); ?></strong>
                </div>
                <div class="meta-item">
                    <span>Skenario utama</span>
                    <strong><?= prediksi_e($prediction['kode_skenario'] ?? '-'); ?></strong>
                </div>
                <div class="meta-item">
                    <span>Efek kecamatan</span>
                    <strong>
                        <?= $districtEffect !== null
                            ? prediksi_angka($districtEffect['nilai_efek'], 4)
                            : '-'; ?>
                        <?= !empty($districtEffect['status_acuan']) ? '(acuan)' : ''; ?>
                    </strong>
                </div>
            </div>

            <div class="input-table-wrap">
                <table class="input-table">
                    <thead>
                        <tr>
                            <th>Variabel input</th>
                            <th>Nilai 2026</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Rasio murid dan guru</td>
                            <td><?= prediksi_angka($prediction['rasio_murid_guru'] ?? null, 4); ?></td>
                            <td>Proyeksi pendidikan</td>
                        </tr>
                        <tr>
                            <td>Rasio ketergantungan</td>
                            <td><?= prediksi_angka($prediction['rasio_ketergantungan'] ?? null, 4); ?></td>
                            <td>Proyeksi demografi</td>
                        </tr>
                        <tr>
                            <td>Tenaga kesehatan per 10.000 penduduk</td>
                            <td><?= prediksi_angka($prediction['tenaga_kesehatan_10000'] ?? null, 4); ?></td>
                            <td>Proyeksi layanan kesehatan</td>
                        </tr>
                        <tr>
                            <td>IKM per 10.000 penduduk</td>
                            <td><?= prediksi_angka($prediction['ikm_10000_2026'] ?? null, 4); ?></td>
                            <td><?= $hasIkmWarning ? 'Perlu verifikasi' : 'Skenario hibrida'; ?></td>
                        </tr>
                        <tr>
                            <td>Kepadatan penduduk</td>
                            <td><?= prediksi_angka($prediction['kepadatan'] ?? null, 4); ?></td>
                            <td>Jiwa per kilometer persegi</td>
                        </tr>
                        <tr>
                            <td>Log kepadatan dan tren</td>
                            <td>
                                <?= prediksi_angka($prediction['log_kepadatan'] ?? null, 4); ?>
                                dan <?= prediksi_bulat($prediction['trend'] ?? null); ?>
                            </td>
                            <td>Transformasi model</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <details class="coefficient-details">
                <summary>Lihat koefisien Model C Final</summary>
                <div class="coefficient-grid">
                    <?php foreach ($modelCoefficients as $coefficient): ?>
                        <div class="coefficient-item">
                            <span><?= prediksi_e($coefficient['nama_parameter']); ?></span>
                            <strong><?= prediksi_angka($coefficient['koefisien'], 6); ?></strong>
                            <small><?= prediksi_e($coefficient['status_signifikansi'] ?? '-'); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </article>

        <article class="result-card card-shell">
            <div class="card-heading">
                <span class="section-kicker">Hasil utama</span>
                <h2><?= prediksi_e($selectedDistrictName); ?>, <?= prediksi_e($selectedTahun); ?></h2>
            </div>

            <?php if ($prediction !== null): ?>
                <div class="result-percentage"><?= prediksi_angka($predictionPercent, 2); ?>%</div>
                <p class="result-caption">Persentase individu kelompok kesejahteraan rendah</p>

                <div class="result-count">
                    <strong><?= prediksi_bulat($predictionCount); ?></strong>
                    <span>individu</span>
                </div>

                <div class="change-grid">
                    <div class="change-box <?= (float) $deltaPercent > 0 ? 'change-up' : 'change-down'; ?>">
                        <span>Perubahan persentase</span>
                        <strong><?= prediksi_signed($deltaPercent, 2, ' poin'); ?></strong>
                        <small>Dibandingkan aktual 2025</small>
                    </div>
                    <div class="change-box <?= (int) $deltaCount > 0 ? 'change-up' : 'change-down'; ?>">
                        <span>Perubahan individu</span>
                        <strong><?= prediksi_signed($deltaCount, 0, ' jiwa'); ?></strong>
                        <small>Dibandingkan aktual 2025</small>
                    </div>
                </div>

                <div class="interval-list">
                    <div class="interval-item">
                        <span>Confidence interval rata-rata 95%</span>
                        <strong>
                            <?= prediksi_angka($prediction['ci_mean_bawah_persen'] ?? null, 2); ?>%
                            sampai
                            <?= prediksi_angka($prediction['ci_mean_atas_persen'] ?? null, 2); ?>%
                        </strong>
                    </div>
                    <div class="interval-item">
                        <span>Interval prediksi 95%</span>
                        <strong>
                            <?= prediksi_angka($prediction['interval_prediksi_bawah_persen'] ?? null, 2); ?>%
                            sampai
                            <?= prediksi_angka($prediction['interval_prediksi_atas_persen'] ?? null, 2); ?>%
                        </strong>
                        <small>
                            <?= prediksi_bulat($prediction['interval_jiwa_bawah'] ?? null); ?> sampai
                            <?= prediksi_bulat($prediction['interval_jiwa_atas'] ?? null); ?> individu
                        </small>
                    </div>
                </div>

                <div class="sensitivity-box <?= prediksi_e($sensitivityClass); ?>">
                    <div class="sensitivity-dot"></div>
                    <div>
                        <span>Status sensitivitas</span>
                        <strong><?= prediksi_e($sensitivityStatus ?? 'Belum tersedia'); ?></strong>
                        <p>Nilai ini menunjukkan kestabilan hasil terhadap model pembanding.</p>
                    </div>
                </div>

                <?php if ($hasBoundaryWarning): ?>
                    <div class="result-warning">
                        Prediksi mentah sebesar <?= prediksi_angka($predictionRawPercent, 4); ?>% melampaui batas logis.
                        Sistem menampilkan nilai operasional sebesar <?= prediksi_angka($predictionPercent, 2); ?>%.
                    </div>
                <?php endif; ?>

                <?php if ($hasIkmWarning): ?>
                    <div class="result-warning warning-blue">
                        Input IKM pada kecamatan ini ditandai untuk verifikasi lanjutan. Hasil tetap ditampilkan sesuai skenario utama.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    Hasil prediksi belum tersedia untuk kombinasi kecamatan dan tahun yang dipilih.
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="prediction-detail-grid">
        <article class="history-card card-shell">
            <div class="card-heading">
                <span class="section-kicker">Data panel</span>
                <h2>Aktual dan Proyeksi</h2>
            </div>

            <div class="history-table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Tahun</th>
                            <th>Status</th>
                            <th>Penduduk</th>
                            <th>Persentase</th>
                            <th>Individu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historicalRows as $row): ?>
                            <tr>
                                <td><?= prediksi_e($row['tahun']); ?></td>
                                <td><span class="data-status status-actual">Aktual</span></td>
                                <td><?= prediksi_bulat($row['jumlah_penduduk']); ?></td>
                                <td><?= prediksi_angka($row['y_persen'], 2); ?>%</td>
                                <td><?= prediksi_bulat($row['jumlah_kelompok_kesejahteraan_rendah']); ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($prediction !== null): ?>
                            <tr class="projection-row">
                                <td><?= prediksi_e($selectedTahun); ?></td>
                                <td><span class="data-status status-projection">Proyeksi</span></td>
                                <td><?= prediksi_bulat($projectedPopulation); ?></td>
                                <td><?= prediksi_angka($predictionPercent, 2); ?>%</td>
                                <td><?= prediksi_bulat($predictionCount); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="source-note">
                Sumber Y: DTKS hasil audit untuk 2022 sampai 2024, DTSEN Desil 1 sampai 5 untuk 2025,
                dan Model C Final untuk 2026.
            </p>
        </article>

        <article class="chart-card card-shell">
            <div class="card-heading card-heading-split">
                <div>
                    <span class="section-kicker">Visualisasi tren</span>
                    <h2>Aktual 2022 sampai 2025 dan Prediksi 2026</h2>
                </div>
                <div class="chart-legend">
                    <span><i class="legend-dot actual-dot"></i>Aktual</span>
                    <span><i class="legend-dot projection-dot"></i>Proyeksi</span>
                </div>
            </div>

            <div id="predictionChart" class="chart-container" aria-label="Grafik tren persentase"></div>
            <script type="application/json" id="predictionChartData"><?= $chartDataJson; ?></script>
        </article>

        <article class="evaluation-card card-shell">
            <div class="card-heading">
                <span class="section-kicker">Validasi model</span>
                <h2>Evaluasi Model C</h2>
            </div>

            <div class="evaluation-list">
                <div class="evaluation-item">
                    <span>R² validasi</span>
                    <strong><?= prediksi_angka($model['r2_validasi'] ?? null, 4); ?></strong>
                    <small>Pengujian tahun 2025</small>
                </div>
                <div class="evaluation-item">
                    <span>MAE persentase</span>
                    <strong><?= prediksi_angka($model['mae_validasi_persen'] ?? null, 4); ?></strong>
                    <small>Poin persentase</small>
                </div>
                <div class="evaluation-item">
                    <span>RMSE persentase</span>
                    <strong><?= prediksi_angka($model['rmse_validasi_persen'] ?? null, 4); ?></strong>
                    <small>Poin persentase</small>
                </div>
                <div class="evaluation-item">
                    <span>MAE jumlah individu</span>
                    <strong><?= prediksi_bulat($model['mae_validasi_jiwa'] ?? null); ?></strong>
                    <small>Individu</small>
                </div>
                <div class="evaluation-item">
                    <span>RMSE jumlah individu</span>
                    <strong><?= prediksi_bulat($model['rmse_validasi_jiwa'] ?? null); ?></strong>
                    <small>Individu</small>
                </div>
                <div class="evaluation-item">
                    <span>R² pelatihan final</span>
                    <strong><?= prediksi_angka($model['r2_training'] ?? null, 4); ?></strong>
                    <small>Data 2022 sampai 2025</small>
                </div>
            </div>

            <div class="evaluation-note">
                Model menunjukkan performa validasi yang memadai. Hasil 2026 tetap harus dibaca sebagai estimasi eksploratif karena nilai variabel input tahun 2026 masih berupa proyeksi.
            </div>
        </article>
    </section>
</main>

<script src="script-prediksi.js?v=4"></script>
</body>
</html>
