<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>User Management Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 10px; color: #111827; line-height: 1.25; }
        h1 { font-size: 14px; margin: 0; line-height: 1.2; }
        .meta { margin: 3px 0 0; color: #6b7280; font-size: 9px; line-height: 1.25; }
        .header { margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 7px; vertical-align: top; word-break: break-word; }
        th { background: #f3f4f6; text-align: left; font-size: 9px; font-weight: 700; }
        .muted { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
<div class="header">
    <h1>User Management Report</h1>
    <div class="meta">Generated on {{ $generatedAt?->format('F d, Y h:i A') ?? now()->format('F d, Y h:i A') }}</div>
    <div class="meta">Total records: {{ $users->count() }}</div>
</div>

<table>
    <thead>
    <tr>
        <th style="width: 20%;">Name</th>
        <th style="width: 24%;">Email</th>
        <th style="width: 20%;">Location</th>
        <th style="width: 9%;">Role</th>
        <th style="width: 9%;">Crop</th>
        <th style="width: 10%;">Stage</th>
        <th style="width: 8%;">Status</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>
                @if ($user->role === 'admin')
                    <span class="muted">N/A</span>
                @else
                    @php
                        $municipality = $user->farm_municipality ?: '—';
                        $barangay = $user->farm_barangay_name ?: ($user->farm_barangay ?: '');
                    @endphp
                    {{ $municipality }}, {{ $barangay !== '' ? $barangay : '—' }}
                @endif
            </td>
            <td>{{ $user->role === 'admin' ? 'Admin' : 'Farmer' }}</td>
            <td>{{ $user->crop_type ?: '—' }}</td>
            <td>{{ $user->farming_stage ? str_replace('_', ' ', $user->farming_stage) : '—' }}</td>
            <td>
                @if (method_exists($user, 'isVerificationLocked') && $user->isVerificationLocked())
                    Locked
                @elseif ($user->email_verified_at)
                    Verified
                @else
                    Pending
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="muted">No users found for the selected filters.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
