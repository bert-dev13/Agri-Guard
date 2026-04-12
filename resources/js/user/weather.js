/**
 * AGRIGUARD Weather Page – dedicated scripts
 * Lucide icons + Chart.js (temperature, rain, wind)
 * Path: resources/js/user/
 */

(function () {
    'use strict';

    var PRIMARY = '#2E7D32';
    var PRIMARY_LIGHT = 'rgba(46, 125, 50, 0.25)';
    var SECONDARY = '#66BB6A';
    var SECONDARY_LIGHT = 'rgba(102, 187, 106, 0.25)';

    function initLucide() {
        if (typeof window.lucide !== 'undefined') {
            window.lucide.createIcons();
        }
    }

    function getChartData() {
        var el = document.getElementById('weather-chart-data');
        if (!el || !el.textContent) return {};
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return {};
        }
    }

    function createTemperatureChart(canvas, data) {
        if (!canvas || !data.labels || !data.labels.length || !data.tempMin || !data.tempMin.length) return;
        if (typeof Chart === 'undefined') return;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Low',
                        data: data.tempMin,
                        borderColor: PRIMARY,
                        backgroundColor: PRIMARY_LIGHT,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'High',
                        data: data.tempMax,
                        borderColor: SECONDARY,
                        backgroundColor: SECONDARY_LIGHT,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: { precision: 0, font: { size: 10 } }
                    },
                    x: {
                        ticks: { font: { size: 9 }, maxRotation: 0 }
                    }
                }
            }
        });
    }

    function createRainfallChart(canvas, data) {
        if (!canvas || !data.labels || !data.labels.length || !data.pop || !data.pop.length) return;
        if (typeof Chart === 'undefined') return;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Rain %',
                        data: data.pop,
                        backgroundColor: PRIMARY_LIGHT,
                        borderColor: PRIMARY,
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { font: { size: 10 } }
                    },
                    x: { ticks: { font: { size: 9 } } }
                }
            }
        });
    }

    function createWindChart(canvas, data) {
        if (!canvas || !data.labels || !data.labels.length || !data.wind || !data.wind.length) return;
        if (typeof Chart === 'undefined') return;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Wind km/h',
                        data: data.wind,
                        borderColor: PRIMARY,
                        backgroundColor: PRIMARY_LIGHT,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 } }
                    },
                    x: { ticks: { font: { size: 9 } } }
                }
            }
        });
    }

    function initCharts() {
        var data = getChartData();
        var tempEl = document.getElementById('chartTemperature');
        var rainEl = document.getElementById('chartRainfall');
        var windEl = document.getElementById('chartWind');

        createTemperatureChart(tempEl, data);
        createRainfallChart(rainEl, data);
        createWindChart(windEl, data);
    }

    function init() {
        initLucide();
        initCharts();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
