/**
 * GradVision — Centralized Professional Line Graph Configuration (Chart.js)
 * Shared across Developer, Head Admin, and Coordinator dashboards.
 *
 * Visual Standards:
 *  - Clean smooth lines (borderWidth: 2.5, tension: 0.32)
 *  - Clearly visible white-cored data point markers
 *  - Subtle horizontal gridlines, no vertical grid clutter
 *  - Plus Jakarta Sans typography matching Typography.css
 *  - Informative dark-slate tooltips with exact period, series, value & unit
 *  - Responsive container sizing
 */
(function (global) {
    'use strict';

    if (global.GradVisionLineChart) {
        return;
    }

    const FONT_FAMILY = "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    const PALETTE = {
        blue:    { stroke: '#2563eb', fill: 'rgba(37, 99, 235, 0.10)' },
        emerald: { stroke: '#10b981', fill: 'rgba(16, 185, 129, 0.10)' },
        rose:    { stroke: '#e11d48', fill: 'rgba(225, 29, 72, 0.08)' },
        slate:   { stroke: '#64748b', fill: 'rgba(100, 116, 139, 0.08)' },
        indigo:  { stroke: '#4f46e5', fill: 'rgba(79, 70, 229, 0.10)' },
        amber:   { stroke: '#d97706', fill: 'rgba(217, 119, 6, 0.10)' },
        teal:    { stroke: '#0d9488', fill: 'rgba(13, 148, 136, 0.10)' }
    };

    const PALETTE_ORDER = ['blue', 'emerald', 'rose', 'slate', 'indigo', 'amber', 'teal'];
    const instances = new WeakMap();

    function resolveColor(colorKey, index) {
        if (colorKey && PALETTE[colorKey]) {
            return PALETTE[colorKey];
        }
        if (typeof colorKey === 'string' && colorKey.startsWith('#')) {
            return { stroke: colorKey, fill: 'rgba(37, 99, 235, 0.08)' };
        }
        const fallbackKey = PALETTE_ORDER[index % PALETTE_ORDER.length];
        return PALETTE[fallbackKey];
    }

    function formatValueWithUnit(value, unit) {
        const num = Number(value ?? 0);
        if (unit === '%') {
            return `${num.toFixed(1)}%`;
        }
        const formatted = Number.isInteger(num) ? num.toLocaleString() : num.toFixed(1);
        return unit ? `${formatted} ${unit}` : formatted;
    }

    function buildDataset(series, index, totalSeries) {
        const colors = resolveColor(series.color, index);
        const shouldFill = series.fill !== undefined ? Boolean(series.fill) : totalSeries === 1;

        return {
            label: String(series.label || `Series ${index + 1}`),
            data: Array.isArray(series.data) ? series.data : [],
            borderColor: colors.stroke,
            backgroundColor: colors.fill,
            borderWidth: series.borderWidth ?? 2.5,
            tension: series.tension ?? 0.32,
            fill: shouldFill,
            pointRadius: series.pointRadius ?? 4.5,
            pointHoverRadius: series.pointHoverRadius ?? 6.5,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: colors.stroke,
            pointBorderWidth: 2.5,
            pointHoverBackgroundColor: colors.stroke,
            pointHoverBorderColor: '#ffffff',
            pointHoverBorderWidth: 2,
            spanGaps: true
        };
    }

    function destroyChart(canvas) {
        if (!canvas) return;
        const existing = instances.get(canvas);
        if (existing && typeof existing.destroy === 'function') {
            existing.destroy();
            instances.delete(canvas);
        }
        if (typeof global.Chart !== 'undefined' && typeof global.Chart.getChart === 'function') {
            const chartJsInst = global.Chart.getChart(canvas);
            if (chartJsInst) {
                chartJsInst.destroy();
            }
        }
    }

    /**
     * Render a standardized professional Chart.js line graph on the given canvas.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {Object} config
     * @param {string[]} config.labels - Chronologically ordered X-axis labels
     * @param {Array<{label: string, data: number[], color?: string, fill?: boolean, tension?: number}>} config.series
     * @param {string} [config.unit='alumni'] - '%', 'alumni', 'responses', etc.
     * @param {string} [config.xTitle='']
     * @param {string} [config.yTitle='']
     * @param {boolean} [config.showLegend] - Defaults to true when series.length > 1
     * @param {Function} [config.onPointClick] - Callback(index, label) when a data point is clicked
     */
    function createLineChart(canvas, config = {}) {
        if (!canvas || typeof global.Chart === 'undefined') {
            return null;
        }

        destroyChart(canvas);

        const labels = Array.isArray(config.labels) && config.labels.length > 0
            ? config.labels
            : ['No Data'];
        const seriesList = Array.isArray(config.series) ? config.series : [];
        const unit = config.unit !== undefined ? config.unit : 'alumni';
        const isPercent = unit === '%';
        const showLegend = config.showLegend !== undefined ? Boolean(config.showLegend) : seriesList.length > 1;

        const datasets = seriesList.map((s, idx) => buildDataset(s, idx, seriesList.length));

        const ctx = canvas.getContext('2d');
        const chart = new global.Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 450, easing: 'easeOutQuart' },
                layout: {
                    padding: { top: 8, right: 12, bottom: 4, left: 4 }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                onClick: (_event, elements) => {
                    if (typeof config.onPointClick === 'function' && elements && elements.length > 0) {
                        const idx = elements[0].index;
                        config.onPointClick(idx, labels[idx]);
                    }
                },
                onHover: (event, elements) => {
                    if (typeof config.onPointClick === 'function' && event?.native?.target) {
                        event.native.target.style.cursor = elements && elements.length > 0 ? 'pointer' : 'default';
                    }
                },
                plugins: {
                    legend: {
                        display: showLegend,
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            boxHeight: 8,
                            padding: 16,
                            color: '#334155',
                            font: {
                                family: FONT_FAMILY,
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: '#0f172a',
                        titleColor: '#f8fafc',
                        bodyColor: '#e2e8f0',
                        borderColor: 'rgba(148, 163, 184, 0.25)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        usePointStyle: true,
                        boxPadding: 5,
                        titleFont: { family: FONT_FAMILY, size: 13, weight: '700' },
                        bodyFont: { family: FONT_FAMILY, size: 12.5, weight: '500' },
                        callbacks: {
                            label: (ctxItem) => {
                                const seriesLabel = ctxItem.dataset?.label || '';
                                const val = ctxItem.parsed?.y ?? 0;
                                return ` ${seriesLabel}: ${formatValueWithUnit(val, unit)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        title: {
                            display: Boolean(config.xTitle),
                            text: config.xTitle || '',
                            color: '#64748b',
                            font: { family: FONT_FAMILY, size: 12, weight: '600' },
                            padding: { top: 8 }
                        },
                        ticks: {
                            color: '#475569',
                            maxRotation: 0,
                            autoSkipPadding: 14,
                            font: { family: FONT_FAMILY, size: 12, weight: '600' }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        min: isPercent ? 0 : undefined,
                        max: isPercent ? 100 : undefined,
                        border: { display: false },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.20)',
                            drawTicks: false
                        },
                        title: {
                            display: Boolean(config.yTitle),
                            text: config.yTitle || '',
                            color: '#64748b',
                            font: { family: FONT_FAMILY, size: 12, weight: '600' },
                            padding: { bottom: 6 }
                        },
                        ticks: {
                            color: '#64748b',
                            padding: 8,
                            precision: isPercent ? undefined : 0,
                            stepSize: isPercent ? 20 : undefined,
                            font: { family: FONT_FAMILY, size: 12, weight: '500' },
                            callback: (val) => isPercent ? `${val}%` : Number(val).toLocaleString()
                        }
                    }
                }
            }
        });

        instances.set(canvas, chart);
        return chart;
    }

    global.GradVisionLineChart = {
        PALETTE,
        createLineChart,
        destroyChart
    };
})(window);
