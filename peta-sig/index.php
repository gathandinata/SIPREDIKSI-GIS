<?php
require_once __DIR__ . '/backend.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Peta SIG Prediksi Kelompok Kesejahteraan Rendah</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="../style-sidebar.css">
    <link rel="stylesheet" href="../style-header.css">
    <link rel="stylesheet" href="style-peta-sig.css?v=5">
</head>
<body>
<?php include __DIR__ . '/../sidebar.php'; ?>

<main class="main-content sig-page">
    <?php
    $pageTitle = 'Peta SIG Prediksi Kelompok Kesejahteraan Rendah';
    $pageSubtitle = 'Visualisasi spasial hasil Model C Final untuk seluruh kecamatan di Kabupaten Sumba Timur.';
    $pageIcon = '🗺';
    require_once __DIR__ . '/../template-header.php';
    ?>

    <?php if ($backendError !== null): ?>
        <div class="page-alert page-alert-error">
            <?= sig_e($backendError); ?>
        </div>
    <?php endif; ?>

    <section class="model-banner" aria-label="Informasi model">
        <div>
            <span class="banner-eyebrow">Visualisasi model aktif</span>
            <strong><?= sig_e($model['nama_model'] ?? 'Model C Final'); ?></strong>
            <p>
                Peta menampilkan estimasi eksploratif tahun <?= (int) $selectedTahun; ?>.
                Persentase menunjukkan proporsi dalam kecamatan, sedangkan jumlah
                individu menunjukkan besarnya populasi sasaran.
            </p>
        </div>

        <div class="banner-chips">
            <span class="info-chip chip-blue"><?= sig_e($model['kode_model'] ?? 'C_FINAL'); ?></span>
            <span class="info-chip chip-green">Skenario Hibrida</span>
            <span class="info-chip chip-amber"><?= sig_e($modeLabel); ?></span>
        </div>
    </section>

    <section class="summary-grid">
        <article class="summary-card card-shell">
            <div class="summary-icon icon-map">🗺</div>
            <div>
                <span>Jumlah Kecamatan</span>
                <strong><?= sig_bulat($totalKecamatan); ?></strong>
                <small><?= sig_bulat($totalGeojson); ?> wilayah memiliki GeoJSON</small>
            </div>
        </article>

        <article class="summary-card card-shell">
            <div class="summary-icon icon-percent">%</div>
            <div>
                <span>Persentase Agregat</span>
                <strong><?= sig_angka($persentaseAgregat, 2); ?>%</strong>
                <small>Agregat tertimbang kabupaten</small>
            </div>
        </article>

        <article class="summary-card card-shell">
            <div class="summary-icon icon-people">👥</div>
            <div>
                <span>Total Prediksi Individu</span>
                <strong><?= sig_bulat($totalPrediksiJiwa); ?></strong>
                <small>Dari <?= sig_bulat($totalPendudukProyeksi); ?> penduduk proyeksi</small>
            </div>
        </article>

        <article class="summary-card card-shell">
            <div class="summary-icon icon-rank">1</div>
            <div>
                <span>Tertinggi Menurut <?= sig_e($modeLabel); ?></span>
                <?php $highestActive = $selectedMode === 'jumlah' ? $tertinggiJumlah : $tertinggiPersentase; ?>
                <strong>
                    <?= $selectedMode === 'jumlah'
                        ? sig_bulat($highestActive['prediksi_jumlah_individu'] ?? null)
                        : sig_angka($highestActive['prediksi_operasional_persen'] ?? null, 2) . '%'; ?>
                </strong>
                <small><?= sig_e($highestActive['nama_kecamatan'] ?? '-'); ?></small>
            </div>
        </article>
    </section>

    <section class="map-layout">
        <aside class="map-control-column">
            <article class="filter-card card-shell">
                <div class="card-heading">
                    <span class="section-kicker">Filter tampilan</span>
                    <h2>Pengaturan Peta</h2>
                </div>

                <form method="GET" action="index.php" class="map-filter-form" id="mapFilterForm">
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

                    <div class="form-group">
                        <label for="mode">Dasar Pewarnaan</label>
                        <select name="mode" id="mode" required>
                            <option value="persentase" <?= $selectedMode === 'persentase' ? 'selected' : ''; ?>>
                                Persentase Operasional
                            </option>
                            <option value="jumlah" <?= $selectedMode === 'jumlah' ? 'selected' : ''; ?>>
                                Jumlah Individu
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_kecamatan">Wilayah Awal</label>
                        <select name="id_kecamatan" id="id_kecamatan">
                            <?php foreach ($kecamatanList as $district): ?>
                                <option
                                    value="<?= (int) $district['id_kecamatan']; ?>"
                                    <?= (int) $district['id_kecamatan'] === $selectedKecamatan ? 'selected' : ''; ?>
                                >
                                    <?= sig_e($district['nama_kecamatan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary">
                        <span aria-hidden="true">↗</span>
                        Tampilkan Peta
                    </button>
                </form>
            </article>

            <article class="legend-card card-shell">
                <div class="card-heading">
                    <span class="section-kicker">Klasifikasi</span>
                    <h2>Legenda <?= sig_e($modeLabel); ?></h2>
                </div>

                <div class="legend-list">
                    <?php foreach ($legendItems as $legend): ?>
                        <div class="legend-item">
                            <span class="legend-color <?= sig_e($legend['class']); ?>"></span>
                            <div>
                                <strong><?= sig_e($legend['range']); ?></strong>
                                <small><?= sig_e($legend['label']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="legend-note">
                    Kelas warna menggunakan ambang tetap agar perbandingan antarkecamatan
                    konsisten. Peta persentase dan peta jumlah individu menggunakan batas yang berbeda.
                </p>
            </article>
        </aside>

        <article class="map-card card-shell">
            <div class="map-card-header">
                <div>
                    <span class="section-kicker">WebGIS Kabupaten Sumba Timur</span>
                    <h2>Distribusi Prediksi Tahun <?= (int) $selectedTahun; ?></h2>
                </div>

                <span class="map-status-chip">
                    <?= sig_bulat($totalGeojson); ?> GeoJSON aktif
                </span>
            </div>

            <div class="map-wrapper">
                <div id="mapSig" aria-label="Peta prediksi per kecamatan"></div>
                <div id="mapEmptyMessage" class="map-empty-message">
                    Data geometri wilayah belum tersedia.
                </div>
            </div>

            <div class="map-source-note">
                <strong>Catatan:</strong>
                Hasil merupakan estimasi eksploratif berdasarkan data administratif dan
                proyeksi variabel independen tahun <?= (int) $selectedTahun; ?>.
                Peta tidak menggantikan angka kemiskinan resmi BPS.
            </div>
        </article>

        <aside class="region-card card-shell">
            <div class="card-heading">
                <span class="section-kicker">Wilayah terpilih</span>
                <h2>Ringkasan Kecamatan</h2>
            </div>

            <div id="selectedRegionContent" class="selected-region-content">
                <div class="region-loading">Memuat informasi wilayah...</div>
            </div>
        </aside>
    </section>

    <section class="method-note card-shell">
        <div>
            <span class="section-kicker">Interpretasi peta</span>
            <h2>Dua Perspektif Visualisasi</h2>
        </div>

        <div class="method-note-grid">
            <div>
                <strong>Persentase operasional</strong>
                <p>
                    Menunjukkan proporsi individu kelompok kesejahteraan rendah
                    terhadap penduduk proyeksi setiap kecamatan.
                </p>
            </div>

            <div>
                <strong>Jumlah individu</strong>
                <p>
                    Menunjukkan besarnya populasi sasaran secara absolut dan membantu
                    membaca kebutuhan program menurut skala wilayah.
                </p>
            </div>

            <div>
                <strong>Ketidakpastian</strong>
                <p>
                    Panel wilayah menampilkan confidence interval, prediction interval,
                    dan status sensitivitas untuk mencegah interpretasi angka secara deterministik.
                </p>
            </div>
        </div>
    </section>
</main>

<script type="application/json" id="mapDataJson"><?= $mapDataJson; ?></script>
<script type="application/json" id="mapConfigJson"><?= sig_e($mapConfigJson); ?></script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../script-sidebar.js"></script>
<script src="script-peta-sig.js?v=5"></script>
</body>
</html>
