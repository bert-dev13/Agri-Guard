const root = document.getElementById('admin-analytics-root');

if (root) {
    const dataNode = document.getElementById('admin-analytics-chart-data');
    const charts = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};

    const palette = ['#0f766e', '#14b8a6', '#0ea5e9', '#6366f1', '#f59e0b', '#f97316', '#84cc16', '#64748b'];

    const showEmpty = (key, message) => {
        const emptyEl = document.getElementById(`analytics-${key}-empty`);
        const canvas = document.getElementById(`analytics-${key}-chart`);
        if (emptyEl) {
            emptyEl.hidden = false;
            emptyEl.textContent = message;
        }
        if (canvas) {
            canvas.hidden = true;
        }
    };

    const renderChart = (key, configBuilder) => {
        const chartData = charts?.[key];
        const labels = Array.isArray(chartData?.labels) ? chartData.labels : [];
        const values = Array.isArray(chartData?.values) ? chartData.values : [];
        if (!window.Chart || labels.length === 0 || values.length === 0) {
            showEmpty(key.replaceAll('_', '-'), chartData?.empty || 'No data available.');
            return;
        }

        const emptyEl = document.getElementById(`analytics-${key.replaceAll('_', '-')}-empty`);
        const canvas = document.getElementById(`analytics-${key.replaceAll('_', '-')}-chart`);
        if (!canvas) {
            return;
        }

        if (emptyEl) {
            emptyEl.hidden = true;
        }
        canvas.hidden = false;

        new Chart(canvas.getContext('2d'), configBuilder(labels, values));
    };

    renderChart('farmers_barangay', (labels, values) => ({
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Farmers',
                    data: values,
                    backgroundColor: '#14b8a6',
                    borderColor: '#0f766e',
                    borderWidth: 1,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    }));

    renderChart('crop_distribution', (labels, values) => ({
        type: 'doughnut',
        data: {
            labels,
            datasets: [
                {
                    data: values,
                    backgroundColor: labels.map((_, index) => palette[index % palette.length]),
                    borderWidth: 1,
                    borderColor: '#ffffff',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
            },
        },
    }));

    renderChart('stage_distribution', (labels, values) => ({
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Farms',
                    data: values,
                    backgroundColor: '#0ea5e9',
                    borderColor: '#0284c7',
                    borderWidth: 1,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    }));

    renderChart('rainfall_trend', (labels, values) => ({
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Rainfall (mm)',
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.15)',
                    fill: true,
                    tension: 0.28,
                    pointRadius: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
            scales: {
                y: { beginAtZero: true },
            },
        },
    }));
}

