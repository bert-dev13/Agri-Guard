(function () {
    'use strict';

    function readPageData() {
        var el = document.getElementById('rainfall-page-data');
        if (!el || !el.textContent) {
            return { daily: [], weekly: [], monthly: [], yearly: [] };
        }
        try {
            var d = JSON.parse(el.textContent);
            return {
                daily: Array.isArray(d.daily) ? d.daily : [],
                weekly: Array.isArray(d.weekly) ? d.weekly : [],
                monthly: Array.isArray(d.monthly) ? d.monthly : [],
                yearly: Array.isArray(d.yearly) ? d.yearly : [],
            };
        } catch (e) {
            return { daily: [], weekly: [], monthly: [], yearly: [] };
        }
    }

    function init() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        if (typeof Chart === 'undefined') {
            return;
        }

        var data = readPageData();
        var chartEl = document.getElementById('rainfallTrendChart');
        if (!chartEl) return;

        var trends = {
            daily: { label: 'Rain per day (mm)', type: 'bar', color: 'rgba(52, 211, 153, 0.35)', border: '#059669' },
            weekly: { label: 'Rain per week (mm)', type: 'line', color: 'rgba(167, 139, 250, 0.28)', border: '#7c3aed' },
            monthly: { label: 'Average monthly rain (mm)', type: 'bar', color: 'rgba(52, 211, 153, 0.35)', border: '#059669' },
            yearly: { label: 'Yearly rain total (mm)', type: 'line', color: 'rgba(167, 139, 250, 0.3)', border: '#6d28d9' },
        };

        var buttons = Array.prototype.slice.call(document.querySelectorAll('.rainfall-page__tab'));
        var activeKey = 'daily';
        var chartInstance = null;

        function setActiveButton(key) {
            buttons.forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-trend') === key);
            });
        }

        function renderChart(key) {
            var config = trends[key];
            var points = Array.isArray(data[key]) ? data[key] : [];

            var labels = points.map(function (item) { return item.label; });
            var values = points.map(function (item) { return item.value; });

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(chartEl, {
                type: config.type,
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: config.label,
                            data: values,
                            borderColor: config.border,
                            backgroundColor: config.color,
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: config.type === 'line' ? 3 : 0,
                            fill: config.type === 'line',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, labels: { boxWidth: 14, color: '#334155', font: { size: 11, weight: '600' } } },
                        tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.9)', padding: 10 },
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b', font: { size: 11 } }, grid: { color: '#e2e8f0' } },
                        x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { display: false } },
                    },
                },
            });
        }

        function activateTrend(key) {
            activeKey = key;
            setActiveButton(key);
            renderChart(key);
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var key = button.getAttribute('data-trend');
                if (!key || key === activeKey) return;
                activateTrend(key);
            });
        });

        if (!data.daily.length && data.monthly.length) activeKey = 'monthly';
        if (!data.monthly.length && data.yearly.length) activeKey = 'yearly';
        activateTrend(activeKey);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
