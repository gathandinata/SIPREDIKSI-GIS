document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const mapElement = document.getElementById('mapSig');
    const mapDataElement = document.getElementById('mapDataJson');
    const mapConfigElement = document.getElementById('mapConfigJson');
    const selectedRegionContent = document.getElementById('selectedRegionContent');
    const districtSelect = document.getElementById('id_kecamatan');
    const emptyMessage = document.getElementById('mapEmptyMessage');

    if (
        !mapElement
        || !mapDataElement
        || !mapConfigElement
        || typeof L === 'undefined'
    ) {
        console.error('Peta tidak dapat dijalankan karena elemen utama atau Leaflet tidak tersedia.');
        return;
    }

    if (mapElement._leaflet_id) {
        return;
    }

    let mapData = [];
    let config = {};

    try {
        mapData = JSON.parse(mapDataElement.textContent || '[]');
        config = JSON.parse(mapConfigElement.textContent || '{}');
    } catch (error) {
        console.error('Data Peta SIG tidak valid.', error);
        mapData = [];
        config = {};
    }

    if (!Array.isArray(mapData)) {
        mapData = [];
    }

    const selectedMode = config.mode === 'jumlah' ? 'jumlah' : 'persentase';
    const selectedYear = Number(config.tahunPrediksi || new Date().getFullYear());
    let activeDistrictId = Number(config.selectedKecamatan || 0);

    const map = L.map(mapElement, {
        zoomControl: true,
        attributionControl: true,
        preferCanvas: false
    }).setView([-9.75, 120.15], 9);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const layerRegistry = new Map();
    let allBounds = null;
    let hasGeojson = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
            return '-';
        }

        return new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0
        }).format(Math.round(Number(value)));
    }

    function formatDecimal(value, decimal = 2) {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
            return '-';
        }

        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimal,
            maximumFractionDigits: decimal
        }).format(Number(value));
    }

    function signedValue(value, decimal = 2, suffix = '') {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return '-';
        }

        const number = Number(value);
        const sign = number > 0 ? '+' : number < 0 ? '-' : '';

        return sign + formatDecimal(Math.abs(number), decimal) + suffix;
    }

    function signedNumber(value, suffix = '') {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return '-';
        }

        const number = Number(value);
        const sign = number > 0 ? '+' : number < 0 ? '-' : '';

        return sign + formatNumber(Math.abs(number)) + suffix;
    }

    function changeClass(value) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return 'text-neutral';
        }

        if (Number(value) > 0) {
            return 'text-up';
        }

        if (Number(value) < 0) {
            return 'text-down';
        }

        return 'text-neutral';
    }

    function safeLevelClass(value) {
        const allowed = ['level-1', 'level-2', 'level-3', 'level-4', 'level-5'];
        return allowed.includes(value) ? value : 'level-1';
    }

    function sensitivityClass(value) {
        const normalized = String(value || '').toLowerCase();

        if (normalized === 'tinggi') {
            return 'sensitivity-high';
        }

        if (normalized === 'sedang') {
            return 'sensitivity-medium';
        }

        if (normalized === 'rendah') {
            return 'sensitivity-low';
        }

        return 'sensitivity-neutral';
    }

    function layerStyle(item, selected) {
        return {
            color: selected ? '#0f172a' : '#ffffff',
            weight: selected ? 3.2 : 1.2,
            fillColor: item.warna || '#e5e7eb',
            fillOpacity: selected ? 0.92 : 0.78
        };
    }

    function popupContent(item) {
        const activeValue = selectedMode === 'jumlah'
            ? formatNumber(item.prediksi_jumlah_individu) + ' individu'
            : formatDecimal(item.prediksi_operasional_persen, 2) + '%';

        return `
            <div class="map-popup">
                <h3>${escapeHtml(item.nama_kecamatan)}</h3>

                <div class="popup-main-value">
                    <span>${selectedMode === 'jumlah' ? 'Prediksi individu' : 'Prediksi persentase'}</span>
                    <strong>${activeValue}</strong>
                </div>

                <div class="popup-grid">
                    <span>Persentase</span>
                    <strong>${formatDecimal(item.prediksi_operasional_persen, 2)}%</strong>

                    <span>Jumlah individu</span>
                    <strong>${formatNumber(item.prediksi_jumlah_individu)}</strong>

                    <span>Penduduk proyeksi</span>
                    <strong>${formatNumber(item.jumlah_penduduk_2026)}</strong>

                    <span>Kategori</span>
                    <strong>
                        <em class="category-badge ${safeLevelClass(item.kategori_class)}">
                            ${escapeHtml(item.kategori)}
                        </em>
                    </strong>
                </div>
            </div>
        `;
    }

    function renderRegion(item) {
        if (!selectedRegionContent) {
            return;
        }

        if (!item) {
            selectedRegionContent.innerHTML = `
                <div class="region-empty">Informasi kecamatan tidak tersedia.</div>
            `;
            return;
        }

        const sensitivity = sensitivityClass(item.status_sensitivitas);
        const percentageChangeClass = changeClass(item.selisih_persen_vs_2025);
        const countChangeClass = changeClass(item.selisih_jiwa_vs_2025);

        const boundNotice = Number(item.flag_prediksi_di_luar_batas) === 1
            ? `
                <div class="region-warning">
                    Prediksi mentah sebesar
                    <strong>${formatDecimal(item.prediksi_raw_persen, 4)}%</strong>
                    melampaui batas logis. Sistem menggunakan nilai operasional
                    <strong>${formatDecimal(item.prediksi_operasional_persen, 2)}%</strong>.
                </div>
            `
            : '';

        const ikmNotice = Number(item.flag_ikm_perlu_verifikasi) === 1
            ? `
                <div class="region-warning warning-amber">
                    Nilai proyeksi IKM pada kecamatan ini memerlukan verifikasi sumber.
                </div>
            `
            : '';

        selectedRegionContent.innerHTML = `
            <div class="region-title-row">
                <div>
                    <span>${escapeHtml(item.kode_panel || item.kode_kecamatan || '-')}</span>
                    <h3>${escapeHtml(item.nama_kecamatan)}</h3>
                </div>

                <span class="category-badge ${safeLevelClass(item.kategori_class)}">
                    ${escapeHtml(item.kategori)}
                </span>
            </div>

            <div class="region-primary-grid">
                <div class="region-primary">
                    <span>Prediksi persentase</span>
                    <strong>${formatDecimal(item.prediksi_operasional_persen, 2)}%</strong>
                    <small>Peringkat ${formatNumber(item.peringkat_persentase)} dari 22</small>
                </div>

                <div class="region-primary">
                    <span>Prediksi individu</span>
                    <strong>${formatNumber(item.prediksi_jumlah_individu)}</strong>
                    <small>Peringkat ${formatNumber(item.peringkat_jumlah_jiwa)} dari 22</small>
                </div>
            </div>

            <div class="region-detail-list">
                <div class="region-detail">
                    <span>Penduduk proyeksi 2026</span>
                    <strong>${formatNumber(item.jumlah_penduduk_2026)} jiwa</strong>
                </div>

                <div class="region-detail">
                    <span>Aktual 2025</span>
                    <strong>
                        ${formatDecimal(item.aktual_2025_persen, 2)}%
                        dan ${formatNumber(item.aktual_2025_jumlah)} individu
                    </strong>
                </div>

                <div class="region-detail">
                    <span>Perubahan persentase</span>
                    <strong class="${percentageChangeClass}">
                        ${signedValue(item.selisih_persen_vs_2025, 2, ' poin')}
                    </strong>
                </div>

                <div class="region-detail">
                    <span>Perubahan jumlah</span>
                    <strong class="${countChangeClass}">
                        ${signedNumber(item.selisih_jiwa_vs_2025, ' individu')}
                    </strong>
                </div>

                <div class="region-detail">
                    <span>Kontribusi terhadap total kabupaten</span>
                    <strong>${formatDecimal(item.kontribusi_prediksi_kabupaten, 2)}%</strong>
                </div>
            </div>

            <div class="region-section">
                <h4>Rentang Ketidakpastian</h4>

                <div class="interval-box">
                    <span>Confidence interval rata-rata 95%</span>
                    <strong>
                        ${formatDecimal(item.ci_mean_bawah_persen, 2)}%
                        sampai ${formatDecimal(item.ci_mean_atas_persen, 2)}%
                    </strong>
                </div>

                <div class="interval-box">
                    <span>Prediction interval 95%</span>
                    <strong>
                        ${formatDecimal(item.interval_prediksi_bawah_persen, 2)}%
                        sampai ${formatDecimal(item.interval_prediksi_atas_persen, 2)}%
                    </strong>
                    <small>
                        ${formatNumber(item.interval_jiwa_bawah)}
                        sampai ${formatNumber(item.interval_jiwa_atas)} individu
                    </small>
                </div>
            </div>

            <div class="region-section">
                <h4>Status Hasil</h4>

                <div class="region-status ${sensitivity}">
                    <span>Status sensitivitas</span>
                    <strong>${escapeHtml(item.status_sensitivitas || '-')}</strong>
                </div>

                <div class="region-detail-list compact">
                    <div class="region-detail">
                        <span>Model</span>
                        <strong>${escapeHtml(item.kode_model || '-')}</strong>
                    </div>

                    <div class="region-detail">
                        <span>Skenario</span>
                        <strong>${escapeHtml(item.kode_skenario || '-')}</strong>
                    </div>

                    <div class="region-detail">
                        <span>Status estimasi</span>
                        <strong>${escapeHtml(item.status_hasil || '-')}</strong>
                    </div>
                </div>
            </div>

            ${boundNotice}
            ${ikmNotice}
        `;
    }

    function refreshLayerStyles() {
        layerRegistry.forEach(function (entry, id) {
            const selected = Number(id) === Number(activeDistrictId);
            entry.layer.setStyle(layerStyle(entry.item, selected));
        });
    }

    function selectRegion(item, featureLayer, fitToRegion = false) {
        if (!item) {
            return;
        }

        activeDistrictId = Number(item.id_kecamatan);
        renderRegion(item);
        refreshLayerStyles();

        if (districtSelect) {
            districtSelect.value = String(activeDistrictId);
        }

        if (featureLayer && typeof featureLayer.openPopup === 'function') {
            featureLayer.openPopup();
        }

        if (fitToRegion) {
            const entry = layerRegistry.get(activeDistrictId);

            if (
                entry
                && entry.layer
                && typeof entry.layer.getBounds === 'function'
                && entry.layer.getBounds().isValid()
            ) {
                map.fitBounds(entry.layer.getBounds(), {
                    padding: [35, 35],
                    maxZoom: 11,
                    animate: false
                });
            }
        }
    }

    mapData.forEach(function (item) {
        if (!item.geojson) {
            return;
        }

        hasGeojson = true;
        const initiallySelected = Number(item.id_kecamatan) === Number(activeDistrictId);

        const geoLayer = L.geoJSON(item.geojson, {
            style: function () {
                return layerStyle(item, initiallySelected);
            },
            onEachFeature: function (feature, featureLayer) {
                featureLayer.bindPopup(popupContent(item), {
                    minWidth: 265,
                    maxWidth: 340,
                    className: 'sig-leaflet-popup',
                    autoPan: true,
                    keepInView: true
                });

                featureLayer.bindTooltip(escapeHtml(item.nama_kecamatan), {
                    permanent: true,
                    direction: 'center',
                    className: 'map-label'
                });

                featureLayer.on('click', function (event) {
                    if (event.originalEvent) {
                        L.DomEvent.stopPropagation(event.originalEvent);
                    }

                    selectRegion(item, featureLayer, false);
                });

                featureLayer.on('mouseover', function () {
                    featureLayer.setStyle({
                        weight: 3,
                        fillOpacity: 0.95
                    });
                });

                featureLayer.on('mouseout', function () {
                    featureLayer.setStyle(
                        layerStyle(
                            item,
                            Number(item.id_kecamatan) === Number(activeDistrictId)
                        )
                    );
                });
            }
        }).addTo(map);

        layerRegistry.set(Number(item.id_kecamatan), {
            layer: geoLayer,
            item: item
        });

        const bounds = geoLayer.getBounds();

        if (bounds && bounds.isValid()) {
            if (!allBounds) {
                allBounds = bounds;
            } else {
                allBounds.extend(bounds);
            }
        }
    });

    let initialItem = mapData.find(function (item) {
        return Number(item.id_kecamatan) === Number(activeDistrictId);
    });

    if (!initialItem && mapData.length > 0) {
        initialItem = mapData[0];
        activeDistrictId = Number(initialItem.id_kecamatan);
    }

    if (initialItem) {
        renderRegion(initialItem);
        refreshLayerStyles();
    }

    const initialEntry = layerRegistry.get(activeDistrictId);

    if (
        initialEntry
        && initialEntry.layer
        && initialEntry.layer.getBounds().isValid()
    ) {
        map.fitBounds(initialEntry.layer.getBounds(), {
            padding: [35, 35],
            maxZoom: 11,
            animate: false
        });
    } else if (hasGeojson && allBounds && allBounds.isValid()) {
        map.fitBounds(allBounds, {
            padding: [30, 30],
            maxZoom: 10,
            animate: false
        });
    }

    if (emptyMessage) {
        emptyMessage.style.display = hasGeojson ? 'none' : 'flex';
    }

    if (districtSelect) {
        districtSelect.addEventListener('change', function () {
            const selectedId = Number(districtSelect.value || 0);
            const item = mapData.find(function (row) {
                return Number(row.id_kecamatan) === selectedId;
            });

            if (item) {
                selectRegion(item, null, true);
            }
        });
    }

    window.setTimeout(function () {
        map.invalidateSize();
    }, 180);
});
