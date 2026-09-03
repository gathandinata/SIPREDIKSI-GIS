document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const mapElement = document.getElementById('mapSig');
    const mapDataElement = document.getElementById('mapDataJson');
    const mapConfigElement = document.getElementById('mapConfigJson');
    const selectedRegionContent = document.getElementById(
        'selectedRegionContent'
    );
    const districtSelect = document.getElementById('id_kecamatan');
    const emptyMessage = document.getElementById('mapEmptyMessage');

    /*
    |--------------------------------------------------------------------------
    | VALIDASI ELEMEN UTAMA
    |--------------------------------------------------------------------------
    */
    if (!mapElement || !mapDataElement) {
        console.error('Elemen utama Peta SIG tidak ditemukan.');
        return;
    }

    if (typeof L === 'undefined') {
        console.error(
            'Leaflet belum dimuat. Periksa koneksi internet atau file Leaflet.'
        );

        showMapMessage(
            'Leaflet tidak berhasil dimuat. Periksa koneksi internet.'
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | MENCEGAH INISIALISASI PETA GANDA
    |--------------------------------------------------------------------------
    */
    if (mapElement._leaflet_id) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | MENAMPILKAN PESAN PADA AREA PETA
    |--------------------------------------------------------------------------
    */
    function showMapMessage(message) {
        if (!emptyMessage) {
            return;
        }

        emptyMessage.textContent = message;

        emptyMessage.style.setProperty(
            'display',
            'flex',
            'important'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MENYEMBUNYIKAN PESAN PADA AREA PETA
    |--------------------------------------------------------------------------
    */
    function hideMapMessage() {
        if (!emptyMessage) {
            return;
        }

        emptyMessage.style.setProperty(
            'display',
            'none',
            'important'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MENDEKODE HTML ENTITY
    |--------------------------------------------------------------------------
    | Fungsi ini menangani JSON yang masih mengandung:
    | &quot;
    | &amp;
    | &#039;
    |--------------------------------------------------------------------------
    */
    function decodeHtmlEntities(value) {
        const decoder = document.createElement('textarea');

        decoder.innerHTML = String(value || '');

        return decoder.value;
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBACA JSON DARI ELEMEN APPLICATION/JSON
    |--------------------------------------------------------------------------
    | Parsing dilakukan melalui beberapa tahap:
    | 1. JSON asli;
    | 2. JSON setelah HTML entity didekode;
    | 3. parsing kedua apabila JSON masih berbentuk string.
    |--------------------------------------------------------------------------
    */
    function parseJsonElement(
        element,
        fallbackValue,
        label
    ) {
        if (!element) {
            console.warn(label + ' tidak ditemukan.');
            return fallbackValue;
        }

        const rawText = String(
            element.textContent || ''
        ).trim();

        if (rawText === '') {
            console.warn(label + ' kosong.');
            return fallbackValue;
        }

        const candidates = [rawText];

        const decodedText = decodeHtmlEntities(
            rawText
        ).trim();

        if (decodedText !== rawText) {
            candidates.push(decodedText);
        }

        for (
            let index = 0;
            index < candidates.length;
            index += 1
        ) {
            try {
                let parsed = JSON.parse(
                    candidates[index]
                );

                /*
                | Apabila hasil pertama masih berupa string JSON,
                | lakukan parsing kedua.
                */
                if (typeof parsed === 'string') {
                    parsed = JSON.parse(parsed);
                }

                return parsed;
            } catch (error) {
                console.warn(
                    label + ' gagal diparsing.',
                    error
                );
            }
        }

        console.error(
            label + ' tidak valid. Potongan data:',
            rawText.substring(0, 300)
        );

        return fallbackValue;
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBACA DATA PETA DAN KONFIGURASI SECARA TERPISAH
    |--------------------------------------------------------------------------
    | Kesalahan pada konfigurasi tidak akan menghapus data peta.
    |--------------------------------------------------------------------------
    */
    let mapData = parseJsonElement(
        mapDataElement,
        [],
        'mapDataJson'
    );

    const config = parseJsonElement(
        mapConfigElement,
        {},
        'mapConfigJson'
    );

    if (!Array.isArray(mapData)) {
        console.error(
            'mapDataJson tidak berbentuk array.'
        );

        mapData = [];
    }

    /*
    |--------------------------------------------------------------------------
    | MENORMALKAN GEOJSON
    |--------------------------------------------------------------------------
    | Fungsi menerima GeoJSON dalam bentuk:
    | 1. object;
    | 2. string JSON;
    | 3. string JSON dengan HTML entity.
    |--------------------------------------------------------------------------
    */
    function normalizeGeojson(
        value,
        districtName
    ) {
        /*
        | GeoJSON sudah berbentuk object.
        */
        if (
            value
            && typeof value === 'object'
        ) {
            return value;
        }

        /*
        | GeoJSON kosong.
        */
        if (
            typeof value !== 'string'
            || value.trim() === ''
        ) {
            return null;
        }

        const originalValue = value.trim();
        const candidates = [originalValue];

        const decodedValue = decodeHtmlEntities(
            originalValue
        ).trim();

        if (decodedValue !== originalValue) {
            candidates.push(decodedValue);
        }

        for (
            let index = 0;
            index < candidates.length;
            index += 1
        ) {
            try {
                let parsed = JSON.parse(
                    candidates[index]
                );

                /*
                | Mendukung JSON yang terbungkus string dua kali.
                */
                if (typeof parsed === 'string') {
                    parsed = JSON.parse(parsed);
                }

                if (
                    parsed
                    && typeof parsed === 'object'
                ) {
                    return parsed;
                }
            } catch (error) {
                console.warn(
                    'GeoJSON gagal diparsing untuk '
                    + districtName
                    + '.',
                    error
                );
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | MENGAMANKAN TEKS HTML
    |--------------------------------------------------------------------------
    */
    function escapeHtml(value) {
        return String(
            value === null
            || value === undefined
                ? ''
                : value
        )
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ANGKA BULAT INDONESIA
    |--------------------------------------------------------------------------
    */
    function formatNumber(value) {
        if (
            value === null
            || value === undefined
            || value === ''
            || Number.isNaN(Number(value))
        ) {
            return '-';
        }

        return new Intl.NumberFormat(
            'id-ID',
            {
                maximumFractionDigits: 0
            }
        ).format(
            Math.round(Number(value))
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ANGKA DESIMAL INDONESIA
    |--------------------------------------------------------------------------
    */
    function formatDecimal(
        value,
        decimal
    ) {
        const digits = Number.isInteger(decimal)
            ? decimal
            : 2;

        if (
            value === null
            || value === undefined
            || value === ''
            || Number.isNaN(Number(value))
        ) {
            return '-';
        }

        return new Intl.NumberFormat(
            'id-ID',
            {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            }
        ).format(
            Number(value)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT DESIMAL BERTANDA
    |--------------------------------------------------------------------------
    */
    function formatSignedDecimal(
        value,
        suffix
    ) {
        if (
            value === null
            || value === undefined
            || Number.isNaN(Number(value))
        ) {
            return '-';
        }

        const number = Number(value);

        const sign = number > 0
            ? '+'
            : (
                number < 0
                    ? '-'
                    : ''
            );

        return (
            sign
            + formatDecimal(
                Math.abs(number),
                2
            )
            + (suffix || '')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ANGKA BULAT BERTANDA
    |--------------------------------------------------------------------------
    */
    function formatSignedNumber(
        value,
        suffix
    ) {
        if (
            value === null
            || value === undefined
            || Number.isNaN(Number(value))
        ) {
            return '-';
        }

        const number = Number(value);

        const sign = number > 0
            ? '+'
            : (
                number < 0
                    ? '-'
                    : ''
            );

        return (
            sign
            + formatNumber(
                Math.abs(number)
            )
            + (suffix || '')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBATASI CLASS KATEGORI
    |--------------------------------------------------------------------------
    */
    function safeLevelClass(value) {
        const allowedClasses = [
            'level-1',
            'level-2',
            'level-3',
            'level-4',
            'level-5'
        ];

        return allowedClasses.includes(value)
            ? value
            : 'level-1';
    }

    /*
    |--------------------------------------------------------------------------
    | CLASS STATUS SENSITIVITAS
    |--------------------------------------------------------------------------
    */
    function sensitivityClass(value) {
        const normalized = String(
            value || ''
        ).toLowerCase();

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

    /*
    |--------------------------------------------------------------------------
    | CLASS PERUBAHAN NILAI
    |--------------------------------------------------------------------------
    | Kenaikan menggunakan merah.
    | Penurunan menggunakan hijau.
    |--------------------------------------------------------------------------
    */
    function changeClass(value) {
        if (
            value === null
            || value === undefined
            || Number.isNaN(Number(value))
        ) {
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

    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI PETA
    |--------------------------------------------------------------------------
    */
    const selectedMode =
        config.mode === 'jumlah'
            ? 'jumlah'
            : 'persentase';

    const selectedYear = Number(
        config.tahunPrediksi
        || new Date().getFullYear()
    );

    let activeDistrictId = Number(
        config.selectedKecamatan || 0
    );

    console.info(
        'Peta SIG menerima',
        mapData.length,
        'baris data.'
    );

    /*
    |--------------------------------------------------------------------------
    | MEMBUAT PETA LEAFLET
    |--------------------------------------------------------------------------
    */
    const map = L.map(
        mapElement,
        {
            zoomControl: true,
            attributionControl: true,
            preferCanvas: false
        }
    ).setView(
        [-9.75, 120.15],
        9
    );

    /*
    |--------------------------------------------------------------------------
    | MENAMBAHKAN BASEMAP OPENSTREETMAP
    |--------------------------------------------------------------------------
    */
    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap'
        }
    ).addTo(map);

    /*
    |--------------------------------------------------------------------------
    | PENYIMPANAN LAYER
    |--------------------------------------------------------------------------
    */
    const layerRegistry = new Map();

    let allBounds = null;
    let validGeojsonCount = 0;

    /*
    |--------------------------------------------------------------------------
    | STYLE POLYGON
    |--------------------------------------------------------------------------
    */
    function getLayerStyle(
        item,
        isSelected
    ) {
        return {
            color: isSelected
                ? '#0f172a'
                : '#ffffff',

            weight: isSelected
                ? 3.2
                : 1.2,

            fillColor: item.warna
                || '#e5e7eb',

            fillOpacity: isSelected
                ? 0.92
                : 0.78
        };
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBUAT ISI POPUP PETA
    |--------------------------------------------------------------------------
    */
    function getPopupContent(item) {
        const activeValue =
            selectedMode === 'jumlah'
                ? (
                    formatNumber(
                        item.prediksi_jumlah_individu
                    )
                    + ' individu'
                )
                : (
                    formatDecimal(
                        item.prediksi_operasional_persen,
                        2
                    )
                    + '%'
                );

        return `
            <div class="map-popup">
                <h3>
                    ${escapeHtml(
                        item.nama_kecamatan
                    )}
                </h3>

                <div class="popup-main-value">
                    <span>
                        ${
                            selectedMode === 'jumlah'
                                ? 'Prediksi individu'
                                : 'Prediksi persentase'
                        }
                    </span>

                    <strong>
                        ${activeValue}
                    </strong>
                </div>

                <div class="popup-grid">
                    <span>Persentase</span>

                    <strong>
                        ${formatDecimal(
                            item.prediksi_operasional_persen,
                            2
                        )}%
                    </strong>

                    <span>Jumlah individu</span>

                    <strong>
                        ${formatNumber(
                            item.prediksi_jumlah_individu
                        )}
                    </strong>

                    <span>Penduduk proyeksi</span>

                    <strong>
                        ${formatNumber(
                            item.jumlah_penduduk_2026
                        )}
                    </strong>

                    <span>Kategori</span>

                    <strong>
                        <em
                            class="category-badge ${safeLevelClass(
                                item.kategori_class
                            )}"
                        >
                            ${escapeHtml(
                                item.kategori || '-'
                            )}
                        </em>
                    </strong>
                </div>
            </div>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | MENAMPILKAN PANEL WILAYAH TERPILIH
    |--------------------------------------------------------------------------
    */
    function renderSelectedRegion(item) {
        if (!selectedRegionContent) {
            return;
        }

        if (!item) {
            selectedRegionContent.innerHTML = `
                <div class="region-empty">
                    Informasi kecamatan tidak tersedia.
                </div>
            `;

            return;
        }

        const sensitivity = sensitivityClass(
            item.status_sensitivitas
        );

        const percentageChangeClass =
            changeClass(
                item.selisih_persen_vs_2025
            );

        const countChangeClass =
            changeClass(
                item.selisih_jiwa_vs_2025
            );

        /*
        |--------------------------------------------------------------------------
        | PERINGATAN NILAI DI LUAR BATAS
        |--------------------------------------------------------------------------
        */
        const boundaryNotice =
            Number(
                item.flag_prediksi_di_luar_batas
            ) === 1
                ? `
                    <div class="region-warning">
                        Prediksi mentah sebesar

                        <strong>
                            ${formatDecimal(
                                item.prediksi_raw_persen,
                                4
                            )}%
                        </strong>

                        melampaui batas logis.
                        Sistem menggunakan nilai operasional

                        <strong>
                            ${formatDecimal(
                                item.prediksi_operasional_persen,
                                2
                            )}%
                        </strong>.
                    </div>
                `
                : '';

        /*
        |--------------------------------------------------------------------------
        | PERINGATAN VERIFIKASI IKM
        |--------------------------------------------------------------------------
        */
        const ikmNotice =
            Number(
                item.flag_ikm_perlu_verifikasi
            ) === 1
                ? `
                    <div
                        class="region-warning warning-amber"
                    >
                        Nilai proyeksi IKM kecamatan ini
                        memerlukan verifikasi sumber.
                    </div>
                `
                : '';

        selectedRegionContent.innerHTML = `
            <div class="region-title-row">
                <div>
                    <span>
                        ${escapeHtml(
                            item.kode_panel
                            || item.kode_kecamatan
                            || '-'
                        )}
                    </span>

                    <h3>
                        ${escapeHtml(
                            item.nama_kecamatan
                        )}
                    </h3>
                </div>

                <span
                    class="category-badge ${safeLevelClass(
                        item.kategori_class
                    )}"
                >
                    ${escapeHtml(
                        item.kategori || '-'
                    )}
                </span>
            </div>

            <div class="region-primary-grid">
                <div class="region-primary">
                    <span>
                        Prediksi persentase
                    </span>

                    <strong>
                        ${formatDecimal(
                            item.prediksi_operasional_persen,
                            2
                        )}%
                    </strong>

                    <small>
                        Peringkat
                        ${formatNumber(
                            item.peringkat_persentase
                        )}
                        dari 22
                    </small>
                </div>

                <div class="region-primary">
                    <span>
                        Prediksi individu
                    </span>

                    <strong>
                        ${formatNumber(
                            item.prediksi_jumlah_individu
                        )}
                    </strong>

                    <small>
                        Peringkat
                        ${formatNumber(
                            item.peringkat_jumlah_jiwa
                        )}
                        dari 22
                    </small>
                </div>
            </div>

            <div class="region-detail-list">
                <div class="region-detail">
                    <span>
                        Penduduk proyeksi
                        ${escapeHtml(selectedYear)}
                    </span>

                    <strong>
                        ${formatNumber(
                            item.jumlah_penduduk_2026
                        )}
                        jiwa
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Aktual 2025
                    </span>

                    <strong>
                        ${formatDecimal(
                            item.aktual_2025_persen,
                            2
                        )}%

                        dan

                        ${formatNumber(
                            item.aktual_2025_jumlah
                        )}

                        individu
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Perubahan persentase
                    </span>

                    <strong
                        class="${percentageChangeClass}"
                    >
                        ${formatSignedDecimal(
                            item.selisih_persen_vs_2025,
                            ' poin'
                        )}
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Perubahan jumlah
                    </span>

                    <strong
                        class="${countChangeClass}"
                    >
                        ${formatSignedNumber(
                            item.selisih_jiwa_vs_2025,
                            ' individu'
                        )}
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Kontribusi terhadap total kabupaten
                    </span>

                    <strong>
                        ${formatDecimal(
                            item.kontribusi_prediksi_kabupaten,
                            2
                        )}%
                    </strong>
                </div>
            </div>

            <div class="region-section">
                <h4>
                    Rentang Ketidakpastian
                </h4>

                <div class="interval-box">
                    <span>
                        Confidence interval rata-rata 95%
                    </span>

                    <strong>
                        ${formatDecimal(
                            item.ci_mean_bawah_persen,
                            2
                        )}%

                        sampai

                        ${formatDecimal(
                            item.ci_mean_atas_persen,
                            2
                        )}%
                    </strong>
                </div>

                <div class="interval-box">
                    <span>
                        Prediction interval 95%
                    </span>

                    <strong>
                        ${formatDecimal(
                            item.interval_prediksi_bawah_persen,
                            2
                        )}%

                        sampai

                        ${formatDecimal(
                            item.interval_prediksi_atas_persen,
                            2
                        )}%
                    </strong>

                    <small>
                        ${formatNumber(
                            item.interval_jiwa_bawah
                        )}

                        sampai

                        ${formatNumber(
                            item.interval_jiwa_atas
                        )}

                        individu
                    </small>
                </div>
            </div>

            <div class="region-section">
                <h4>
                    Status Hasil
                </h4>

                <div
                    class="region-status ${sensitivity}"
                >
                    <span>
                        Status sensitivitas
                    </span>

                    <strong>
                        ${escapeHtml(
                            item.status_sensitivitas
                            || '-'
                        )}
                    </strong>
                </div>

                <div
                    class="region-detail-list compact"
                >
                    <div class="region-detail">
                        <span>Model</span>

                        <strong>
                            ${escapeHtml(
                                item.kode_model || '-'
                            )}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Skenario</span>

                        <strong>
                            ${escapeHtml(
                                item.kode_skenario || '-'
                            )}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Status estimasi</span>

                        <strong>
                            ${escapeHtml(
                                item.status_hasil || '-'
                            )}
                        </strong>
                    </div>
                </div>
            </div>

            ${boundaryNotice}
            ${ikmNotice}
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | MEMPERBARUI STYLE SELURUH POLYGON
    |--------------------------------------------------------------------------
    */
    function refreshLayerStyles() {
        layerRegistry.forEach(
            function (
                entry,
                districtId
            ) {
                const isSelected =
                    Number(districtId)
                    === Number(activeDistrictId);

                entry.layer.setStyle(
                    getLayerStyle(
                        entry.item,
                        isSelected
                    )
                );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MEMILIH KECAMATAN
    |--------------------------------------------------------------------------
    */
    function selectRegion(
        item,
        featureLayer,
        fitToRegion
    ) {
        if (!item) {
            return;
        }

        activeDistrictId = Number(
            item.id_kecamatan
        );

        renderSelectedRegion(item);
        refreshLayerStyles();

        /*
        | Memperbarui dropdown tanpa mengirim form.
        */
        if (districtSelect) {
            districtSelect.value = String(
                activeDistrictId
            );
        }

        /*
        | Membuka popup polygon.
        */
        if (
            featureLayer
            && typeof featureLayer.openPopup
                === 'function'
        ) {
            featureLayer.openPopup();
        }

        /*
        | Memusatkan peta apabila pilihan berasal
        | dari dropdown.
        */
        if (fitToRegion) {
            const entry = layerRegistry.get(
                activeDistrictId
            );

            if (
                entry
                && entry.layer
                && typeof entry.layer.getBounds
                    === 'function'
                && entry.layer.getBounds().isValid()
            ) {
                map.fitBounds(
                    entry.layer.getBounds(),
                    {
                        padding: [35, 35],
                        maxZoom: 11,
                        animate: false
                    }
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBENTUK LAYER GEOJSON
    |--------------------------------------------------------------------------
    */
    mapData.forEach(function (item) {
        const districtName =
            item.nama_kecamatan
            || 'tanpa nama';

        const geojson = normalizeGeojson(
            item.geojson,
            districtName
        );

        /*
        | Lewati kecamatan apabila GeoJSON gagal dibaca.
        */
        if (!geojson) {
            console.warn(
                'GeoJSON tidak tersedia atau tidak valid:',
                districtName
            );

            return;
        }

        item.geojson = geojson;

        try {
            const initiallySelected =
                Number(item.id_kecamatan)
                === Number(activeDistrictId);

            const geoLayer = L.geoJSON(
                geojson,
                {
                    /*
                    |--------------------------------------------------------------------------
                    | STYLE AWAL POLYGON
                    |--------------------------------------------------------------------------
                    */
                    style: function () {
                        return getLayerStyle(
                            item,
                            initiallySelected
                        );
                    },

                    /*
                    |--------------------------------------------------------------------------
                    | EVENT SETIAP FEATURE
                    |--------------------------------------------------------------------------
                    */
                    onEachFeature: function (
                        feature,
                        featureLayer
                    ) {
                        /*
                        | Popup kecamatan.
                        */
                        featureLayer.bindPopup(
                            getPopupContent(item),
                            {
                                minWidth: 265,
                                maxWidth: 340,
                                className:
                                    'sig-leaflet-popup',
                                autoPan: true,
                                keepInView: true
                            }
                        );

                        /*
                        | Label nama kecamatan.
                        */
                        featureLayer.bindTooltip(
                            escapeHtml(
                                item.nama_kecamatan
                            ),
                            {
                                permanent: true,
                                direction: 'center',
                                className: 'map-label'
                            }
                        );

                        /*
                        | Klik polygon.
                        */
                        featureLayer.on(
                            'click',
                            function (event) {
                                if (
                                    event.originalEvent
                                ) {
                                    L.DomEvent.stopPropagation(
                                        event.originalEvent
                                    );
                                }

                                selectRegion(
                                    item,
                                    featureLayer,
                                    false
                                );
                            }
                        );

                        /*
                        | Hover polygon.
                        */
                        featureLayer.on(
                            'mouseover',
                            function () {
                                featureLayer.setStyle({
                                    weight: 3,
                                    fillOpacity: 0.95
                                });
                            }
                        );

                        /*
                        | Mengembalikan style setelah hover.
                        */
                        featureLayer.on(
                            'mouseout',
                            function () {
                                featureLayer.setStyle(
                                    getLayerStyle(
                                        item,
                                        Number(
                                            item.id_kecamatan
                                        ) === Number(
                                            activeDistrictId
                                        )
                                    )
                                );
                            }
                        );
                    }
                }
            ).addTo(map);

            validGeojsonCount += 1;

            /*
            | Menyimpan layer berdasarkan ID kecamatan.
            */
            layerRegistry.set(
                Number(item.id_kecamatan),
                {
                    layer: geoLayer,
                    item: item
                }
            );

            /*
            | Menghitung batas seluruh wilayah.
            */
            const bounds = geoLayer.getBounds();

            if (
                bounds
                && bounds.isValid()
            ) {
                if (!allBounds) {
                    allBounds =
                        L.latLngBounds(bounds);
                } else {
                    allBounds.extend(bounds);
                }
            }
        } catch (error) {
            console.error(
                'Leaflet gagal membuat layer untuk kecamatan '
                + districtName
                + '.',
                error
            );
        }
    });

    console.info(
        'Peta SIG berhasil membuat',
        validGeojsonCount,
        'layer GeoJSON.'
    );

    /*
    |--------------------------------------------------------------------------
    | MENENTUKAN KECAMATAN AWAL
    |--------------------------------------------------------------------------
    */
    let initialItem = mapData.find(
        function (item) {
            return (
                Number(item.id_kecamatan)
                === Number(activeDistrictId)
            );
        }
    );

    /*
    | Gunakan kecamatan pertama apabila kecamatan
    | dari konfigurasi tidak ditemukan.
    */
    if (
        !initialItem
        && mapData.length > 0
    ) {
        initialItem = mapData[0];

        activeDistrictId = Number(
            initialItem.id_kecamatan
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MENAMPILKAN PANEL WILAYAH AWAL
    |--------------------------------------------------------------------------
    */
    if (initialItem) {
        renderSelectedRegion(initialItem);
        refreshLayerStyles();
    } else if (selectedRegionContent) {
        selectedRegionContent.innerHTML = `
            <div class="region-empty">
                Data hasil prediksi tidak berhasil dibaca.
            </div>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | MENENTUKAN POSISI AWAL PETA
    |--------------------------------------------------------------------------
    */
    const initialEntry = layerRegistry.get(
        activeDistrictId
    );

    if (
        initialEntry
        && initialEntry.layer
        && initialEntry.layer
            .getBounds()
            .isValid()
    ) {
        /*
        | Fokus pada kecamatan terpilih.
        */
        map.fitBounds(
            initialEntry.layer.getBounds(),
            {
                padding: [35, 35],
                maxZoom: 11,
                animate: false
            }
        );
    } else if (
        validGeojsonCount > 0
        && allBounds
        && allBounds.isValid()
    ) {
        /*
        | Menampilkan seluruh wilayah.
        */
        map.fitBounds(
            allBounds,
            {
                padding: [30, 30],
                maxZoom: 10,
                animate: false
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MENAMPILKAN ATAU MENYEMBUNYIKAN PESAN GEOJSON
    |--------------------------------------------------------------------------
    */
    if (validGeojsonCount > 0) {
        hideMapMessage();
    } else if (mapData.length === 0) {
        showMapMessage(
            'Data peta tidak berhasil dibaca oleh JavaScript.'
        );
    } else {
        showMapMessage(
            'GeoJSON tersedia di database, tetapi tidak dapat dibentuk menjadi layer Leaflet. Periksa Console browser.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERUBAHAN DROPDOWN KECAMATAN TANPA RELOAD
    |--------------------------------------------------------------------------
    */
    if (districtSelect) {
        districtSelect.addEventListener(
            'change',
            function () {
                const selectedId = Number(
                    districtSelect.value || 0
                );

                const item = mapData.find(
                    function (row) {
                        return (
                            Number(
                                row.id_kecamatan
                            ) === selectedId
                        );
                    }
                );

                if (item) {
                    selectRegion(
                        item,
                        null,
                        true
                    );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MEMPERBAIKI UKURAN PETA SETELAH LAYOUT SELESAI
    |--------------------------------------------------------------------------
    */
    window.setTimeout(
        function () {
            map.invalidateSize();
        },
        180
    );
});