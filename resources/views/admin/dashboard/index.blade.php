@extends('layouts.admin')

@section('title', 'Admin Dashboard - AGRIGUARD')
@section('body-class', 'admin-dashboard-page')

@section('content')
    <div class="admin-page">
        <section class="admin-dash-header">
            <div class="admin-dash-header__text">
                <h1 class="admin-dash-header__title">
                    <span class="admin-dash-header__title-icon" aria-hidden="true"><i data-lucide="layout-dashboard"></i></span>
                    <span>Admin Dashboard</span>
                </h1>
                <p class="admin-dash-header__subtitle">Overview of system users and activity</p>
            </div>
            <div class="admin-dash-header__meta">
                <p class="admin-dash-header__date">
                    <span class="admin-dash-header__date-icon" aria-hidden="true"><i data-lucide="calendar-days"></i></span>
                    <span>{{ $dashboardDate->format('F j, Y') }}</span>
                </p>
            </div>
        </section>

        <section class="admin-dash-section">
            <h2 class="admin-dash-section__title">Overview</h2>
            <div class="admin-stat-grid admin-stat-grid--four">
                @foreach ($summaryCards as $card)
                    <article class="admin-stat-card admin-stat-card--{{ $card['style'] }}">
                        <div class="admin-stat-card__icon"><i data-lucide="{{ $card['icon'] }}"></i></div>
                        <div class="admin-stat-card__value">{{ number_format($card['value']) }}</div>
                        <div class="admin-stat-card__label">{{ $card['label'] }}</div>
                        @if (!empty($card['meta']))
                            <p class="admin-stat-card__meta">{{ $card['meta'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="admin-dash-section admin-dash-section--quick-actions" aria-labelledby="admin-quick-actions-heading">
            <header class="admin-dash-section__header">
                <h2 id="admin-quick-actions-heading" class="admin-dash-section__title">Quick Actions</h2>
                <p class="admin-dash-section__subtitle">Access key admin functions quickly</p>
            </header>
            <div class="admin-quick-actions">
                <a href="{{ route('admin.users.index') }}" class="admin-quick-action-card">
                    <span class="admin-quick-action-card__icon" aria-hidden="true"><i data-lucide="users"></i></span>
                    <span class="admin-quick-action-card__body">
                        <span class="admin-quick-action-card__title">Manage Users</span>
                        <span class="admin-quick-action-card__desc">View and update accounts</span>
                    </span>
                </a>
                <a href="{{ route('admin.farms.index') }}" class="admin-quick-action-card">
                    <span class="admin-quick-action-card__icon" aria-hidden="true"><i data-lucide="sprout"></i></span>
                    <span class="admin-quick-action-card__body">
                        <span class="admin-quick-action-card__title">Manage Farms</span>
                        <span class="admin-quick-action-card__desc">Monitor farm profiles</span>
                    </span>
                </a>
                <a href="{{ route('admin.analytics.index') }}" class="admin-quick-action-card">
                    <span class="admin-quick-action-card__icon" aria-hidden="true"><i data-lucide="bar-chart-3"></i></span>
                    <span class="admin-quick-action-card__body">
                        <span class="admin-quick-action-card__title">Analytics</span>
                        <span class="admin-quick-action-card__desc">Charts and insights</span>
                    </span>
                </a>
                <a href="{{ route('admin.account-settings.index') }}" class="admin-quick-action-card">
                    <span class="admin-quick-action-card__icon" aria-hidden="true"><i data-lucide="settings"></i></span>
                    <span class="admin-quick-action-card__body">
                        <span class="admin-quick-action-card__title">Settings</span>
                        <span class="admin-quick-action-card__desc">Admin profile &amp; security</span>
                    </span>
                </a>
            </div>
        </section>

        <section class="admin-dash-section">
            <article class="admin-dash-panel admin-dash-panel--chart">
                <div class="admin-dash-chart__header">
                    <div>
                        <h2 class="admin-dash-panel__title">User Activity (Last 7 Days)</h2>
                    </div>
                </div>
                <div class="admin-dash-chart__canvas-wrap">
                    <canvas id="admin-user-activity-chart" aria-label="User activity trends line chart" role="img"></canvas>
                </div>
            </article>
        </section>

    </div>

    <script id="admin-dashboard-chart-trends" type="application/json">@json($activityChart)</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            const trendDataNode = document.getElementById('admin-dashboard-chart-trends');
            const trendData = trendDataNode ? JSON.parse(trendDataNode.textContent || '{}') : {};
            const canvas = document.getElementById('admin-user-activity-chart');

            if (!canvas || !window.Chart || !Array.isArray(trendData?.labels)) {
                return;
            }

            const chartCtx = canvas.getContext('2d');

            const chart = new Chart(chartCtx, {
                type: 'line',
                data: {
                    labels: trendData.labels,
                    datasets: [
                        {
                            label: 'Active Users',
                            data: trendData.values || [],
                            borderColor: '#0f766e',
                            borderWidth: 2,
                            tension: 0.35,
                            fill: false,
                            pointRadius: 2,
                            pointHoverRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.94)',
                            titleColor: '#f8fafc',
                            bodyColor: '#e2e8f0',
                            borderColor: 'rgba(148, 163, 184, 0.25)',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(148, 163, 184, 0.14)'
                            },
                            ticks: {
                                color: '#64748b'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(148, 163, 184, 0.14)'
                            },
                            ticks: {
                                precision: 0,
                                color: '#64748b'
                            }
                        }
                    },
                    animation: {
                        duration: 600
                    }
                }
            });
        })();
    </script>
@endsection
