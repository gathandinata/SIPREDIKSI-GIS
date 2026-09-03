(function () {
    'use strict';

    const chartEl = document.getElementById('barChart');
    const filterForm = document.getElementById('filterForm');

    let resizeTimer = null;

    /*
    |--------------------------------------------------------------------------
    | Mengamankan teks sebelum dimasukkan ke SVG/HTML
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
    | Format angka Indonesia
    |--------------------------------------------------------------------------
    */
    function formatNumber(value) {
        const number = Number(value);

        if (!Number.isFinite(number)) {
            return '-';
        }

        return new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0,
        }).format(Math.round(number));
    }

    /*
    |--------------------------------------------------------------------------
    | Membuat label kecamatan pendek untuk sumbu grafik
    |--------------------------------------------------------------------------
    */
    function shortenLabel(value, maxLength = 13) {
        const text = String(value ?? '').trim();

        if (text.length <= maxLength) {
            return text;
        }

        return text.substring(0, maxLength - 1) + '…';
    }

    /*
    |--------------------------------------------------------------------------
    | Membaca data grafik dari atribut data-series
    |--------------------------------------------------------------------------
    */
    function getSeries() {
        if (!chartEl) {
            return [];
        }

        try {
            const parsed = JSON.parse(chartEl.dataset.series || '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.error('Data grafik laporan tidak valid:', error);
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menampilkan keadaan grafik kosong
    |--------------------------------------------------------------------------
    */
    function renderEmptyChart(message) {
        if (!chartEl) {
            return;
        }

        chartEl.innerHTML = `
            <div class="chart-empty-state">
                ${escapeHtml(message)}
            </div>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | Merender grafik perbandingan aktual dan prediksi
    |--------------------------------------------------------------------------
    */
    function renderBarChart() {
        if (!chartEl) {
            return;
        }

        const series = getSeries();

        if (series.length === 0) {
            renderEmptyChart(
                'Data grafik belum tersedia karena belum ada hasil prediksi tersimpan.'
            );
            return;
        }

        const width = Math.max(chartEl.clientWidth || 520, 320);
        const height = 250;
        const padding = {
            top: 18,
            right: 18,
            bottom: 56,
            left: 62,
        };

        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;

        const numericValues = [];

        series.forEach(function (item) {
            const actual = Number(item.aktual);
            const prediction = Number(item.prediksi);

            if (Number.isFinite(actual)) {
                numericValues.push(actual);
            }

            if (Number.isFinite(prediction)) {
                numericValues.push(prediction);
            }
        });

        if (numericValues.length === 0) {
            renderEmptyChart('Nilai grafik belum tersedia.');
            return;
        }

        const maxValue = Math.max(...numericValues, 1);
        const groupWidth = plotWidth / series.length;
        const barWidth = Math.max(9, Math.min(22, groupWidth / 4));
        const barGap = 6;

        let svg = `
            <svg
                width="100%"
                height="${height}"
                viewBox="0 0 ${width} ${height}"
                role="img"
                aria-label="Grafik perbandingan data aktual dan hasil prediksi"
            >
        `;

        /* Grid horizontal dan label nilai sumbu Y. */
        for (let index = 0; index <= 4; index += 1) {
            const y = padding.top + (plotHeight / 4) * index;
            const value = Math.round(
                maxValue - (maxValue / 4) * index
            );

            svg += `
                <line
                    x1="${padding.left}"
                    y1="${y}"
                    x2="${width - padding.right}"
                    y2="${y}"
                    stroke="#e5edf6"
                    stroke-width="1"
                />
                <text
                    x="${padding.left - 10}"
                    y="${y + 4}"
                    text-anchor="end"
                    font-size="11"
                    fill="#475569"
                >
                    ${escapeHtml(formatNumber(value))}
                </text>
            `;
        }

        /* Sumbu X dan Y. */
        svg += `
            <line
                x1="${padding.left}"
                y1="${padding.top + plotHeight}"
                x2="${width - padding.right}"
                y2="${padding.top + plotHeight}"
                stroke="#cbd5e1"
            />
            <line
                x1="${padding.left}"
                y1="${padding.top}"
                x2="${padding.left}"
                y2="${padding.top + plotHeight}"
                stroke="#cbd5e1"
            />
        `;

        series.forEach(function (item, index) {
            const actual = Number(item.aktual);
            const prediction = Number(item.prediksi);
            const actualValid = Number.isFinite(actual);
            const predictionValid = Number.isFinite(prediction);

            const groupX = padding.left
                + (index * groupWidth)
                + (groupWidth / 2);

            const baseY = padding.top + plotHeight;

            if (actualValid) {
                const actualHeight = (actual / maxValue) * plotHeight;
                const actualX = groupX - barWidth - (barGap / 2);
                const actualY = baseY - actualHeight;

                svg += `
                    <rect
                        x="${actualX}"
                        y="${actualY}"
                        width="${barWidth}"
                        height="${actualHeight}"
                        rx="2"
                        fill="#2563eb"
                    >
                        <title>
                            ${escapeHtml(item.label)} — Aktual: ${escapeHtml(formatNumber(actual))}
                        </title>
                    </rect>
                `;
            }

            if (predictionValid) {
                const predictionHeight = (prediction / maxValue) * plotHeight;
                const predictionX = groupX + (barGap / 2);
                const predictionY = baseY - predictionHeight;

                svg += `
                    <rect
                        x="${predictionX}"
                        y="${predictionY}"
                        width="${barWidth}"
                        height="${predictionHeight}"
                        rx="2"
                        fill="#1f9d61"
                    >
                        <title>
                            ${escapeHtml(item.label)} — Prediksi: ${escapeHtml(formatNumber(prediction))}
                        </title>
                    </rect>
                `;
            }

            svg += `
                <text
                    x="${groupX}"
                    y="${height - 19}"
                    text-anchor="middle"
                    font-size="10"
                    fill="#334155"
                >
                    ${escapeHtml(shortenLabel(item.label))}
                </text>
            `;
        });

        svg += `
            <text
                x="${padding.left - 46}"
                y="${padding.top + (plotHeight / 2)}"
                transform="rotate(-90 ${padding.left - 46},${padding.top + (plotHeight / 2)})"
                text-anchor="middle"
                font-size="11"
                fill="#334155"
            >
                Jumlah Penduduk Miskin
            </text>
        `;

        svg += '</svg>';

        chartEl.innerHTML = svg;
    }

    /*
    |--------------------------------------------------------------------------
    | Status tombol filter ketika form dikirim
    |--------------------------------------------------------------------------
    */
    if (filterForm) {
        filterForm.addEventListener('submit', function () {
            const button = filterForm.querySelector(
                'button[type="submit"]'
            );

            if (!button) {
                return;
            }

            button.disabled = true;
            button.textContent = 'Memuat...';
        });
    }

    /* Render awal. */
    renderBarChart();

    /* Render ulang secara ringan ketika ukuran layar berubah. */
    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer);

        resizeTimer = window.setTimeout(
            renderBarChart,
            150
        );
    });
})();
