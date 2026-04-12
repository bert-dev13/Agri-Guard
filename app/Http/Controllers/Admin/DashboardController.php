<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();
        $rangeStart = $today->copy()->subDays(6);
        $rangeEnd = $today->copy()->endOfDay();

        $totalUsers = User::query()->count();
        $totalFarmers = User::query()->farmers()->count();
        $totalAdmins = User::query()->admins()->count();
        $activeUsers = User::query()
            ->where('updated_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $summaryCards = [
            [
                'icon' => 'users',
                'value' => $totalUsers,
                'label' => 'Total Users',
                'meta' => 'Updated today',
                'style' => 'users',
            ],
            [
                'icon' => 'tractor',
                'value' => $totalFarmers,
                'label' => 'Farmers',
                'meta' => 'Updated today',
                'style' => 'farmers',
            ],
            [
                'icon' => 'shield-check',
                'value' => $totalAdmins,
                'label' => 'Admins',
                'meta' => 'Updated today',
                'style' => 'admins',
            ],
            [
                'icon' => 'activity',
                'value' => $activeUsers,
                'label' => 'Active Users',
                'meta' => 'Last 30 days',
                'style' => 'active',
            ],
        ];

        $quickLinks = [
            [
                'label' => 'Manage Users',
                'hint' => 'View and update accounts',
                'icon' => 'users',
                'url' => route('admin.users.index'),
            ],
            [
                'label' => 'Weather Data',
                'hint' => 'Open weather details',
                'icon' => 'cloud-sun',
                'url' => route('weather-details'),
            ],
            [
                'label' => 'Rainfall Records',
                'hint' => 'Check rainfall trends',
                'icon' => 'cloud-rain',
                'url' => route('rainfall-trends'),
            ],
            [
                'label' => 'Advisories',
                'hint' => 'Open AI farm assistant',
                'icon' => 'bot',
                'url' => route('assistant.index'),
            ],
            [
                'label' => 'Settings',
                'hint' => 'Manage account settings',
                'icon' => 'settings',
                'url' => route('settings'),
            ],
        ];

        $activeUsersByDay = User::query()
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as total')
            ->whereBetween('updated_at', [$rangeStart->copy()->startOfDay(), $rangeEnd])
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $seriesActiveUsers = [];
        $cursor = $rangeStart->copy();
        while ($cursor->lte($today)) {
            $dayKey = $cursor->toDateString();

            $labels[] = $cursor->format('M j');
            $seriesActiveUsers[] = (int) ($activeUsersByDay[$dayKey] ?? 0);

            $cursor->addDay();
        }

        $activityChart = [
            'labels' => $labels,
            'values' => $seriesActiveUsers,
        ];

        return view('admin.dashboard.index', [
            'dashboardDate' => Carbon::now(),
            'summaryCards' => $summaryCards,
            'quickLinks' => $quickLinks,
            'activityChart' => $activityChart,
        ]);
    }
}
