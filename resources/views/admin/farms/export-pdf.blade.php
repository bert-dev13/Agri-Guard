<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Farm Monitoring Report</title>
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
    <h1>Farm &amp; Crop Monitoring Report</h1>
    <div class="meta">Generated on {{ $generatedAt?->format('F d, Y h:i A') ?? now()->format('F d, Y h:i A') }}</div>
    <div class="meta">Total records: {{ $farms->count() }}</div>
</div>

<table>
    <thead>
    <tr>
        <th style="width: 22%;">Farmer Name</th>
        <th style="width: 22%;">Barangay</th>
        <th style="width: 14%;">Crop Type</th>
        <th style="width: 16%;">Farming Stage</th>
        <th style="width: 14%;">Planting Date</th>
        <th style="width: 12%;">Farm Size (ha)</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($farms as $farm)
        <tr>
            <td>{{ $farm->name }}</td>
            <td>{{ $farm->farm_barangay_name ?: '—' }}</td>
            <td>{{ $farm->crop_type ?: '—' }}</td>
            <td>{{ $farm->farming_stage ? app(\App\Services\CropTimelineService::class)->displayLabel($farm->farming_stage) : '—' }}</td>
            <td>{{ $farm->planting_date?->format('M d, Y') ?: '—' }}</td>
            <td>{{ $farm->farm_area !== null ? number_format((float) $farm->farm_area, 2) : '—' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="muted">No farm records found for the selected filters.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>

