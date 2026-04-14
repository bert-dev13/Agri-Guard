<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HistoricalWeatherCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class HistoricalWeatherImportController extends Controller
{
    public function index(): View
    {
        return view('admin.historical-weather.index');
    }

    public function store(Request $request, HistoricalWeatherCsvImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'truncate' => ['nullable', 'boolean'],
        ], [
            'csv_file.required' => 'Please select a CSV file to import.',
            'csv_file.mimes' => 'Only CSV or TXT files are allowed.',
        ]);

        $path = $validated['csv_file']->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['csv_file' => 'Unable to read uploaded file.'])->withInput();
        }

        try {
            $result = $importer->importFromPath($path, [
                'truncate' => (bool) ($validated['truncate'] ?? false),
                'has_header' => true,
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()])->withInput();
        }

        $message = "Imported {$result['imported']} row(s), skipped {$result['skipped']}.";
        if ($result['errors'] !== []) {
            $message .= ' Some rows were skipped due to invalid format/date values.';
        }

        return redirect()
            ->route('admin.historical-weather.index')
            ->with('success', $message)
            ->with('import_errors', array_slice($result['errors'], 0, 10));
    }
}
