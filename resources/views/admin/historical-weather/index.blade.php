@extends('layouts.admin')

@section('title', 'Historical Weather Import - AGRIGUARD')
@section('body-class', 'admin-historical-weather-page')

@section('content')
    <div class="admin-page">
        <section class="admin-dash-header">
            <div class="admin-dash-header__text">
                <h1 class="admin-dash-header__title">
                    <span class="admin-dash-header__title-icon" aria-hidden="true"><i data-lucide="cloud-upload"></i></span>
                    <span>Historical Weather Import</span>
                </h1>
                <p class="admin-dash-header__subtitle">Upload CSV data keyed by year, month, and day</p>
            </div>
        </section>

        <section class="admin-users-card">
            @if (session('success'))
                <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.historical-weather.store') }}" enctype="multipart/form-data" class="admin-users-filters">
                @csrf
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="historical-weather-csv">CSV File</label>
                    <input id="historical-weather-csv" type="file" name="csv_file" accept=".csv,.txt" class="admin-users-filters__input" required>
                    <p class="text-xs text-slate-500">Required columns: year, month, day, rainfall, wind_speed, wind_direction.</p>
                </div>
                <div class="admin-users-filters__field">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="truncate" value="1" @checked(old('truncate'))>
                        <span>Replace existing historical weather data before import</span>
                    </label>
                </div>
                <div class="admin-users-filters__actions">
                    <button type="submit" class="admin-users-filters__submit">Import CSV</button>
                </div>
            </form>

            @if (session('import_errors'))
                <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">Skipped row details</p>
                    <ul class="list-disc pl-5">
                        @foreach (session('import_errors') as $rowError)
                            <li>{{ $rowError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>
@endsection
