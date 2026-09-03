
document.addEventListener('DOMContentLoaded', function () {
    /*
    |--------------------------------------------------------------------------
    | ELEMEN UTAMA HALAMAN PETA
    |--------------------------------------------------------------------------
    */
    const mapElement = document.getElementById('mapSig');
    const mapDataElement = document.getElementById('mapDataJson');
    const mapConfigElement = document.getElementById('mapConfig');
    const emptyMessage = document.getElementById('mapEmptyMessage');
    const selectedRegionContent = document.getElementById(
        'selectedRegionContent'
    );
    const kecamatanSelect = document.getElementById('id_kecamatan');

    /*
    |--------------------------------------------------------------------------
    | VALIDASI ELEMEN DAN LIBRARY LEAFLET
    |--------------------------------------------------------------------------
    */
    if (
        !mapElement
        || !mapDataElement
        || !mapConfigElement
        || typeof L === 'undefined'
    ) {
        console.error(
            'Peta gagal dijalankan: elemen utama atau Leaflet tidak tersedia.'
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | MENCEGAH PETA DIINISIALISASI DUA KALI
    |--------------------------------------------------------------------------
    | Diperlukan apabila file JavaScript tidak sengaja dipanggil lebih dari
    | satu kali.
    |--------------------------------------------------------------------------
    */
    if (mapElement._leaflet_id) {
        console.warn(
            'Peta sudah diinisialisasi. Inisialisasi kedua dihentikan.'
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBACA DATA JSON DARI PHP
    |--------------------------------------------------------------------------
    */
    let mapData = [];

    try {
        mapData = JSON.parse(
            mapDataElement.textContent || '[]'
        );

        if (!Array.isArray(mapData)) {
            mapData = [];
        }
    } catch (error) {
        console.error(
            'Data JSON peta tidak valid:',
            error
        );

        mapData = [];
    }

    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI AWAL PETA
    |--------------------------------------------------------------------------
    */
    const selectedKecamatanId = Number(
        mapConfigElement.dataset.selectedKecamatan || 0
    );

    const tahunPrediksi = Number(
        mapConfigElement.dataset.tahunPrediksi
        || new Date().getFullYear()
    );

    /*
    |--------------------------------------------------------------------------
    | MEMBUAT PETA LEAFLET
    |--------------------------------------------------------------------------
    */
    const map = L.map(mapElement, {
        zoomControl: true,
        attributionControl: true,
        preferCanvas: false
    }).setView(
        [-9.75, 120.15],
        9
    );

    /*
    |--------------------------------------------------------------------------
    | BASEMAP OPENSTREETMAP
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
    | PENYIMPANAN STATUS PETA
    |--------------------------------------------------------------------------
    */
    const layerRegistry = new Map();

    let activeKecamatanId = selectedKecamatanId;
    let allBounds = null;
    let hasGeojsonLayer = false;

    /*
    |--------------------------------------------------------------------------
    | MENGAMANKAN TEKS HTML
    |--------------------------------------------------------------------------
    */
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBATASI CLASS KATEGORI
    |--------------------------------------------------------------------------
    */
    function getSafeCategoryClass(value) {
        const allowedClasses = [
            'sangat-tinggi',
            'tinggi',
            'sedang',
            'rendah',
            'sangat-rendah',
            'tidak-ada-data'
        ];

        return allowedClasses.includes(value)
            ? value
            : 'tidak-ada-data';
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT BILANGAN BULAT INDONESIA
    |--------------------------------------------------------------------------
    | Contoh: 8693 menjadi 8.693
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
    | FORMAT BILANGAN DESIMAL INDONESIA
    |--------------------------------------------------------------------------
    */
    function formatDecimal(value, decimal = 2) {
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
                minimumFractionDigits: decimal,
                maximumFractionDigits: decimal
            }
        ).format(
            Number(value)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CLASS WARNA PERUBAHAN
    |--------------------------------------------------------------------------
    | Kenaikan kemiskinan menggunakan warna merah.
    | Penurunan kemiskinan menggunakan warna hijau.
    |--------------------------------------------------------------------------
    */
    function getChangeClass(value) {
        if (
            value === null
            || value === undefined
            || value === ''
            || Number.isNaN(Number(value))
        ) {
            return '';
        }

        return Number(value) >= 0
            ? 'text-red'
            : 'text-green';
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ANGKA BERTANDA
    |--------------------------------------------------------------------------
    */
    function formatSignedNumber(value, suffix = '') {
        if (
            value === null
            || value === undefined
            || value === ''
            || Number.isNaN(Number(value))
        ) {
            return '-';
        }

        const number = Number(value);
        const sign = number >= 0 ? '+' : '-';

        return (
            sign
            + formatNumber(Math.abs(number))
            + suffix
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT PERSENTASE BERTANDA
    |--------------------------------------------------------------------------
    */
    function formatSignedPercent(value) {
        if (
            value === null
            || value === undefined
            || value === ''
            || Number.isNaN(Number(value))
        ) {
            return '-';
        }

        const number = Number(value);
        const sign = number >= 0 ? '+' : '-';

        return (
            sign
            + formatDecimal(Math.abs(number), 2)
            + '%'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STYLE POLYGON
    |--------------------------------------------------------------------------
    */
    function getLayerStyle(item, isSelected) {
        return {
            color: isSelected
                ? '#111827'
                : '#ffffff',

            weight: isSelected
                ? 3
                : 1.3,

            fillColor: item.warna
                || '#f3f4f6',

            fillOpacity: isSelected
                ? 0.90
                : 0.72
        };
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBUAT ISI POPUP
    |--------------------------------------------------------------------------
    | Badge kategori diberi style nowrap secara langsung agar tulisan seperti
    | "Sangat Tinggi" tidak turun menjadi dua baris.
    |--------------------------------------------------------------------------
    */
    function getPopupContent(item) {
        const kategoriClass = getSafeCategoryClass(
            item.kategori_class
        );

        const kategori = escapeHtml(
            item.kategori || 'Tidak Ada Data'
        );

        const nilaiPrediksi =
            item.nilai_prediksi !== null
            && item.nilai_prediksi !== undefined
                ? formatNumber(item.nilai_prediksi) + ' Jiwa'
                : '-';

        const nilaiR2 =
            item.r2 !== null
            && item.r2 !== undefined
                ? formatDecimal(item.r2, 3)
                : '-';

        return `
            <div
                class="map-popup"
                style="
                    width: 100%;
                    min-width: 250px;
                    max-width: 320px;
                "
            >
                <h4>
                    ${escapeHtml(item.nama_kecamatan)}
                </h4>

                <table
                    style="
                        width: 100%;
                        border-collapse: collapse;
                        table-layout: auto;
                    "
                >
                    <tr>
                        <td
                            style="
                                width: 45%;
                                padding: 5px 7px 5px 0;
                                vertical-align: top;
                            "
                        >
                            Nama Kecamatan
                        </td>

                        <td
                            style="
                                width: 55%;
                                padding: 5px 0;
                                vertical-align: top;
                            "
                        >
                            : ${escapeHtml(item.nama_kecamatan)}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                padding: 5px 7px 5px 0;
                                vertical-align: top;
                            "
                        >
                            Tahun Prediksi
                        </td>

                        <td
                            style="
                                padding: 5px 0;
                                vertical-align: top;
                            "
                        >
                            : ${escapeHtml(tahunPrediksi)}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                padding: 5px 7px 5px 0;
                                vertical-align: top;
                            "
                        >
                            Prediksi Penduduk Miskin
                        </td>

                        <td
                            style="
                                padding: 5px 0;
                                vertical-align: top;
                            "
                        >
                            : ${nilaiPrediksi}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                padding: 5px 7px 5px 0;
                                vertical-align: middle;
                            "
                        >
                            Kategori
                        </td>

                        <td
                            style="
                                padding: 5px 0;
                                vertical-align: middle;
                            "
                        >
                            <div
                                style="
                                    display: flex;
                                    align-items: center;
                                    gap: 5px;
                                    flex-wrap: nowrap;
                                    white-space: nowrap;
                                "
                            >
                                <span>:</span>

                                <span
                                    class="popup-badge ${kategoriClass}"
                                    style="
                                        display: inline-flex;
                                        align-items: center;
                                        justify-content: center;
                                        flex-shrink: 0;
                                        white-space: nowrap;
                                        word-break: normal;
                                        overflow-wrap: normal;
                                        min-width: max-content;
                                        padding: 5px 9px;
                                        border-radius: 999px;
                                        font-size: 11px;
                                        line-height: 1;
                                        font-weight: 800;
                                    "
                                >
                                    ${kategori}
                                </span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                padding: 5px 7px 5px 0;
                                vertical-align: top;
                            "
                        >
                            R² Model
                        </td>

                        <td
                            style="
                                padding: 5px 0;
                                vertical-align: top;
                            "
                        >
                            : ${nilaiR2}
                        </td>
                    </tr>
                </table>
            </div>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | MEMPERBARUI PANEL RINGKASAN WILAYAH
    |--------------------------------------------------------------------------
    | Seluruh data panel diperbarui langsung dengan JavaScript.
    | Tidak ada request ulang ke PHP.
    |--------------------------------------------------------------------------
    */
    function renderSelectedRegion(item) {
        if (!selectedRegionContent) {
            return;
        }

        if (!item) {
            selectedRegionContent.innerHTML = `
                <div class="empty-region">
                    Data wilayah tidak tersedia.
                </div>
            `;

            return;
        }

        const kategoriClass = getSafeCategoryClass(
            item.kategori_class
        );

        const changeClass = getChangeClass(
            item.selisih_prediksi
        );

        const percentageChangeClass = getChangeClass(
            item.persen_perubahan
        );

        const modelGood =
            item.r2 !== null
            && item.r2 !== undefined
            && Number(item.r2) >= 0.8;

        const modelTitle = modelGood
            ? 'Model memiliki performa sangat baik'
            : 'Evaluasi model perlu diperhatikan';

        const modelText =
            item.r2 !== null
            && item.r2 !== undefined
                ? (
                    'Nilai R² model sebesar '
                    + formatDecimal(item.r2, 3)
                    + ' untuk tahun '
                    + tahunPrediksi
                    + '.'
                )
                : 'Nilai R² belum tersedia untuk wilayah ini.';

        const geojsonStatus = item.geojson
            ? 'GeoJSON tersedia'
            : 'GeoJSON belum tersedia';

        const tahunPenduduk =
            item.tahun_data_penduduk !== null
            && item.tahun_data_penduduk !== undefined
                ? escapeHtml(item.tahun_data_penduduk)
                : '-';

        const tahunSebelumnya =
            item.tahun_sebelumnya !== null
            && item.tahun_sebelumnya !== undefined
                ? item.tahun_sebelumnya
                : tahunPrediksi - 1;

        selectedRegionContent.innerHTML = `
            <h2>
                ${escapeHtml(item.nama_kecamatan)}
            </h2>

            <div class="region-detail-list">
                <div class="region-detail">
                    <span>Tahun Prediksi</span>

                    <strong>
                        ${escapeHtml(tahunPrediksi)}
                    </strong>
                </div>

                <div class="region-detail">
                    <span>Prediksi Penduduk Miskin</span>

                    <strong class="text-red">
                        ${
                            item.nilai_prediksi !== null
                            && item.nilai_prediksi !== undefined
                                ? formatNumber(
                                    item.nilai_prediksi
                                ) + ' Jiwa'
                                : '-'
                        }
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Total Penduduk Kecamatan
                        ${
                            tahunPenduduk !== '-'
                                ? '(' + tahunPenduduk + ')'
                                : ''
                        }
                    </span>

                    <strong>
                        ${
                            item.jumlah_penduduk_kecamatan !== null
                            && item.jumlah_penduduk_kecamatan !== undefined
                                ? formatNumber(
                                    item.jumlah_penduduk_kecamatan
                                ) + ' Jiwa'
                                : '-'
                        }
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Total Penduduk Kabupaten Sumba Timur
                    </span>

                    <strong>
                        ${
                            item.jumlah_penduduk_kabupaten !== null
                            && item.jumlah_penduduk_kabupaten !== undefined
                                ? formatNumber(
                                    item.jumlah_penduduk_kabupaten
                                ) + ' Jiwa'
                                : '-'
                        }
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Persentase terhadap Total Penduduk Kecamatan
                    </span>

                    <strong>
                        ${
                            item.persen_terhadap_kecamatan !== null
                            && item.persen_terhadap_kecamatan !== undefined
                                ? formatDecimal(
                                    item.persen_terhadap_kecamatan,
                                    2
                                ) + '%'
                                : '-'
                        }
                    </strong>
                </div>

                <div class="region-detail">
                    <span>
                        Persentase terhadap Total Kabupaten Sumba Timur
                    </span>

                    <strong>
                        ${
                            item.persen_terhadap_kabupaten !== null
                            && item.persen_terhadap_kabupaten !== undefined
                                ? formatDecimal(
                                    item.persen_terhadap_kabupaten,
                                    2
                                ) + '%'
                                : '-'
                        }
                    </strong>
                </div>

                <div class="region-detail">
                    <span>Kategori</span>

                    <strong>
                        <span
                            class="category-badge ${kategoriClass}"
                            style="white-space: nowrap;"
                        >
                            ${escapeHtml(
                                item.kategori || 'Tidak Ada Data'
                            )}
                        </span>
                    </strong>
                </div>
            </div>

            <div class="region-section">
                <h4>
                    Perbandingan dengan Tahun Lalu
                    (${escapeHtml(tahunSebelumnya)})
                </h4>

                <div class="region-detail-list">
                    <div class="region-detail">
                        <span>
                            Prediksi
                            ${escapeHtml(tahunSebelumnya)}
                        </span>

                        <strong>
                            ${
                                item.nilai_prediksi_tahun_sebelumnya !== null
                                && item.nilai_prediksi_tahun_sebelumnya !== undefined
                                    ? formatNumber(
                                        item.nilai_prediksi_tahun_sebelumnya
                                    ) + ' Jiwa'
                                    : '-'
                            }
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Selisih</span>

                        <strong class="${changeClass}">
                            ${formatSignedNumber(
                                item.selisih_prediksi,
                                ' Jiwa'
                            )}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Perubahan</span>

                        <strong class="${percentageChangeClass}">
                            ${formatSignedPercent(
                                item.persen_perubahan
                            )}
                        </strong>
                    </div>
                </div>
            </div>

            <div class="region-section">
                <h4>Informasi Tambahan</h4>

                <div class="region-detail-list">
                    <div class="region-detail">
                        <span>Kode Kecamatan</span>

                        <strong>
                            ${escapeHtml(
                                item.kode_kecamatan || '-'
                            )}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Status Data Peta</span>

                        <strong>
                            ${geojsonStatus}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Sumber Prediksi</span>

                        <strong>
                            ${escapeHtml(
                                item.sumber_prediksi || '-'
                            )}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Tahun Data Penduduk</span>

                        <strong>
                            ${tahunPenduduk}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>Sumber Data Penduduk</span>

                        <strong>
                            ${escapeHtml(
                                item.sumber_data_penduduk || '-'
                            )}
                        </strong>
                    </div>

                    <div class="region-detail">
                        <span>R² Model</span>

                        <strong>
                            ${
                                item.r2 !== null
                                && item.r2 !== undefined
                                    ? formatDecimal(item.r2, 3)
                                    : '-'
                            }
                        </strong>
                    </div>
                </div>

                <div class="region-status">
                    <div class="status-icon">✓</div>

                    <div>
                        <strong>
                            ${modelTitle}
                        </strong>

                        <p>
                            ${modelText}
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | MEMPERBARUI STYLE POLYGON
    |--------------------------------------------------------------------------
    */
    function refreshLayerStyles() {
        layerRegistry.forEach(
            function (entry, idKecamatan) {
                const isSelected =
                    Number(idKecamatan)
                    === Number(activeKecamatanId);

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
    | MEMILIH WILAYAH TANPA RELOAD
    |--------------------------------------------------------------------------
    | Fungsi ini tidak mengubah window.location dan tidak mengirim form.
    |--------------------------------------------------------------------------
    */
    function selectRegionWithoutReload(
        item,
        geoJsonLayer,
        clickedFeatureLayer
    ) {
        if (!item) {
            return;
        }

        /*
        | Mengganti kecamatan aktif.
        */
        activeKecamatanId = Number(
            item.id_kecamatan
        );

        /*
        | Memperbarui panel keterangan wilayah.
        */
        renderSelectedRegion(item);

        /*
        | Memperbarui dropdown tanpa submit.
        */
        if (kecamatanSelect) {
            kecamatanSelect.value = String(
                activeKecamatanId
            );
        }

        /*
        | Memperbarui style polygon.
        */
        refreshLayerStyles();

        /*
        | Menampilkan popup wilayah yang diklik.
        */
        if (
            clickedFeatureLayer
            && typeof clickedFeatureLayer.openPopup === 'function'
        ) {
            clickedFeatureLayer.openPopup();
        }

        /*
        | Tidak ada:
        | - window.location.href
        | - location.reload()
        | - form.submit()
        | - history redirect
        | - map.fitBounds() saat klik
        */
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBUAT POLYGON SETIAP KECAMATAN
    |--------------------------------------------------------------------------
    */
    mapData.forEach(function (item) {
        if (!item.geojson) {
            return;
        }

        hasGeojsonLayer = true;

        const isInitiallySelected =
            Number(item.id_kecamatan)
            === Number(activeKecamatanId);

        let geoJsonLayer = null;

        geoJsonLayer = L.geoJSON(
            item.geojson,
            {
                /*
                |--------------------------------------------------------------------------
                | STYLE AWAL POLYGON
                |--------------------------------------------------------------------------
                */
                style: function () {
                    return getLayerStyle(
                        item,
                        isInitiallySelected
                    );
                },

                /*
                |--------------------------------------------------------------------------
                | EVENT SETIAP FEATURE GEOJSON
                |--------------------------------------------------------------------------
                */
                onEachFeature: function (
                    feature,
                    leafletLayer
                ) {
                    /*
                    |--------------------------------------------------------------------------
                    | POPUP RESPONSIF
                    |--------------------------------------------------------------------------
                    | minWidth menjaga badge "Sangat Tinggi" tetap satu baris.
                    |--------------------------------------------------------------------------
                    */
                    leafletLayer.bindPopup(
                        getPopupContent(item),
                        {
                            minWidth: 270,
                            maxWidth: 340,
                            className: 'region-map-popup',
                            autoPan: true,
                            closeButton: true,
                            keepInView: true
                        }
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | LABEL NAMA KECAMATAN
                    |--------------------------------------------------------------------------
                    */
                    leafletLayer.bindTooltip(
                        escapeHtml(item.nama_kecamatan),
                        {
                            permanent: true,
                            direction: 'center',
                            className: 'map-label'
                        }
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | KLIK POLYGON TANPA REFRESH ATAU RELOAD
                    |--------------------------------------------------------------------------
                    */
                    leafletLayer.on(
                        'click',
                        function (event) {
                            /*
                            | Menghentikan event browser agar tidak merambat
                            | ke elemen lain.
                            */
                            if (event.originalEvent) {
                                L.DomEvent.preventDefault(
                                    event.originalEvent
                                );

                                L.DomEvent.stopPropagation(
                                    event.originalEvent
                                );
                            }

                            /*
                            | Memperbarui popup dan panel tanpa reload.
                            */
                            selectRegionWithoutReload(
                                item,
                                geoJsonLayer,
                                leafletLayer
                            );
                        }
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | EFEK HOVER
                    |--------------------------------------------------------------------------
                    */
                    leafletLayer.on(
                        'mouseover',
                        function () {
                            leafletLayer.setStyle({
                                weight: 3,
                                fillOpacity: 0.92
                            });
                        }
                    );

                    leafletLayer.on(
                        'mouseout',
                        function () {
                            const isSelected =
                                Number(item.id_kecamatan)
                                === Number(activeKecamatanId);

                            leafletLayer.setStyle(
                                getLayerStyle(
                                    item,
                                    isSelected
                                )
                            );
                        }
                    );
                }
            }
        ).addTo(map);

        /*
        |--------------------------------------------------------------------------
        | MENYIMPAN LAYER BERDASARKAN ID KECAMATAN
        |--------------------------------------------------------------------------
        */
        layerRegistry.set(
            Number(item.id_kecamatan),
            {
                layer: geoJsonLayer,
                item: item
            }
        );

        /*
        |--------------------------------------------------------------------------
        | MENGHITUNG BATAS SELURUH WILAYAH
        |--------------------------------------------------------------------------
        */
        if (
            typeof geoJsonLayer.getBounds === 'function'
        ) {
            const bounds = geoJsonLayer.getBounds();

            if (bounds && bounds.isValid()) {
                if (!allBounds) {
                    allBounds = bounds;
                } else {
                    allBounds.extend(bounds);
                }
            }
        }
    });

    /*
    |--------------------------------------------------------------------------
    | MENENTUKAN WILAYAH AWAL
    |--------------------------------------------------------------------------
    */
    let initialItem = null;

    /*
    | Prioritas pertama: kecamatan dari konfigurasi PHP.
    */
    if (activeKecamatanId > 0) {
        initialItem = mapData.find(
            function (item) {
                return (
                    Number(item.id_kecamatan)
                    === Number(activeKecamatanId)
                );
            }
        );
    }

    /*
    | Prioritas kedua: kecamatan dengan nilai prediksi tertinggi.
    */
    if (!initialItem) {
        const itemsWithPrediction = mapData.filter(
            function (item) {
                return (
                    item.nilai_prediksi !== null
                    && item.nilai_prediksi !== undefined
                    && !Number.isNaN(
                        Number(item.nilai_prediksi)
                    )
                );
            }
        );

        if (itemsWithPrediction.length > 0) {
            initialItem = itemsWithPrediction.reduce(
                function (highest, current) {
                    if (!highest) {
                        return current;
                    }

                    return (
                        Number(current.nilai_prediksi)
                        > Number(highest.nilai_prediksi)
                    )
                        ? current
                        : highest;
                },
                null
            );
        }
    }

    /*
    | Prioritas ketiga: kecamatan pertama.
    */
    if (!initialItem && mapData.length > 0) {
        initialItem = mapData[0];
    }

    /*
    |--------------------------------------------------------------------------
    | MENAMPILKAN PANEL WILAYAH AWAL
    |--------------------------------------------------------------------------
    */
    if (initialItem) {
        activeKecamatanId = Number(
            initialItem.id_kecamatan
        );

        renderSelectedRegion(initialItem);

        if (kecamatanSelect) {
            kecamatanSelect.value = String(
                activeKecamatanId
            );
        }

        refreshLayerStyles();
    }

    /*
    |--------------------------------------------------------------------------
    | MENENTUKAN POSISI PETA PERTAMA KALI
    |--------------------------------------------------------------------------
    | fitBounds hanya dijalankan saat halaman pertama kali dibuka.
    | fitBounds tidak dijalankan ketika polygon diklik.
    |--------------------------------------------------------------------------
    */
    const selectedEntry = layerRegistry.get(
        activeKecamatanId
    );

    if (
        selectedEntry
        && selectedEntry.layer
        && typeof selectedEntry.layer.getBounds === 'function'
        && selectedEntry.layer.getBounds().isValid()
    ) {
        map.fitBounds(
            selectedEntry.layer.getBounds(),
            {
                padding: [35, 35],
                maxZoom: 11,
                animate: false
            }
        );
    } else if (
        hasGeojsonLayer
        && allBounds
        && allBounds.isValid()
    ) {
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
    | PESAN KETIKA GEOJSON TIDAK TERSEDIA
    |--------------------------------------------------------------------------
    */
    if (
        hasGeojsonLayer
        && allBounds
        && allBounds.isValid()
    ) {
        if (emptyMessage) {
            emptyMessage.style.display = 'none';
        }
    } else if (emptyMessage) {
        emptyMessage.style.display = 'flex';
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
        150
    );
});
