document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('predictionFilterForm');
    const submitButton = form ? form.querySelector('button[type="submit"]') : null;

    if (form && submitButton) {
        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span aria-hidden="true">⏳</span> Memuat Data...';
        });
    }

    const chartContainer = document.getElementById('predictionChart');
    const chartDataElement = document.getElementById('predictionChartData');

    if (!chartContainer || !chartDataElement) {
        return;
    }

    let data = [];

    try {
        data = JSON.parse(chartDataElement.textContent || '[]');
    } catch (error) {
        console.error('Data grafik tidak valid:', error);
        data = [];
    }

    if (!Array.isArray(data) || data.length < 2) {
        chartContainer.innerHTML = '<div class="chart-empty">Data belum cukup untuk membentuk grafik.</div>';
        return;
    }

    const width = 860;
    const height = 330;
    const margin = { top: 38, right: 34, bottom: 58, left: 62 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    const values = data.map((item) => Number(item.persen));
    let minValue = Math.min(...values);
    let maxValue = Math.max(...values);

    const padding = Math.max(3, (maxValue - minValue) * 0.22);
    minValue = Math.max(0, minValue - padding);
    maxValue = Math.min(105, maxValue + padding);

    if (maxValue <= minValue) {
        maxValue = minValue + 10;
    }

    const xForIndex = (index) => {
        if (data.length === 1) {
            return margin.left + plotWidth / 2;
        }

        return margin.left + (plotWidth * index) / (data.length - 1);
    };

    const yForValue = (value) => {
        return margin.top + ((maxValue - value) / (maxValue - minValue)) * plotHeight;
    };

    const points = data.map((item, index) => ({
        ...item,
        x: xForIndex(index),
        y: yForValue(Number(item.persen))
    }));

    const actualPoints = points.filter((item) => item.status === 'Aktual');
    const projectionPoint = points.find((item) => item.status === 'Proyeksi');

    const actualPath = actualPoints
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
        .join(' ');

    let projectionPath = '';
    if (projectionPoint && actualPoints.length > 0) {
        const lastActual = actualPoints[actualPoints.length - 1];
        projectionPath = `M ${lastActual.x.toFixed(2)} ${lastActual.y.toFixed(2)} L ${projectionPoint.x.toFixed(2)} ${projectionPoint.y.toFixed(2)}`;
    }

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatDecimal = (value, digits = 2) => new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
    }).format(Number(value));

    const formatInteger = (value) => new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0
    }).format(Math.round(Number(value)));

    let svg = `
        <svg viewBox="0 0 ${width} ${height}" class="prediction-svg" role="img" aria-labelledby="chartTitle chartDesc">
            <title id="chartTitle">Tren persentase kelompok kesejahteraan rendah</title>
            <desc id="chartDesc">Garis aktual tahun 2022 sampai 2025 dan titik proyeksi tahun 2026.</desc>
    `;

    for (let index = 0; index <= 4; index += 1) {
        const ratio = index / 4;
        const y = margin.top + plotHeight * ratio;
        const value = maxValue - (maxValue - minValue) * ratio;

        svg += `
            <line x1="${margin.left}" y1="${y}" x2="${width - margin.right}" y2="${y}" class="chart-grid-line"></line>
            <text x="${margin.left - 12}" y="${y + 4}" text-anchor="end" class="chart-axis-label">${formatDecimal(value, 1)}%</text>
        `;
    }

    svg += `
        <line x1="${margin.left}" y1="${margin.top}" x2="${margin.left}" y2="${height - margin.bottom}" class="chart-axis-line"></line>
        <line x1="${margin.left}" y1="${height - margin.bottom}" x2="${width - margin.right}" y2="${height - margin.bottom}" class="chart-axis-line"></line>
    `;

    if (actualPath) {
        svg += `<path d="${actualPath}" class="chart-line-actual"></path>`;
    }

    if (projectionPath) {
        svg += `<path d="${projectionPath}" class="chart-line-projection"></path>`;
    }

    points.forEach((point) => {
        const isProjection = point.status === 'Proyeksi';
        const pointClass = isProjection ? 'chart-point-projection' : 'chart-point-actual';
        const labelClass = isProjection ? 'chart-label-projection' : 'chart-label-actual';
        const tooltip = `${point.status} ${point.tahun}: ${formatDecimal(point.persen, 2)}%, ${formatInteger(point.jumlah)} individu`;

        svg += `
            <g class="chart-point-group" tabindex="0" aria-label="${escapeHtml(tooltip)}">
                <circle cx="${point.x}" cy="${point.y}" r="6" class="${pointClass}"></circle>
                <text x="${point.x}" y="${point.y - 14}" text-anchor="middle" class="${labelClass}">${formatDecimal(point.persen, 2)}%</text>
                <text x="${point.x}" y="${height - 27}" text-anchor="middle" class="chart-year-label">${escapeHtml(point.tahun)}</text>
                <title>${escapeHtml(tooltip)}</title>
            </g>
        `;
    });

    svg += `
            <text x="${width / 2}" y="${height - 5}" text-anchor="middle" class="chart-axis-title">Tahun</text>
            <text x="18" y="${height / 2}" text-anchor="middle" transform="rotate(-90 18 ${height / 2})" class="chart-axis-title">Persentase</text>
        </svg>
    `;

    chartContainer.innerHTML = svg;
});
