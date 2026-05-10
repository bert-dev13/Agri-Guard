<?php

namespace App\Console\Commands;

use App\Services\HistoricalWeatherCsvImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ImportHistoricalWeatherCsvCommand extends Command
{
    protected $signature = 'historical-weather:import
                            {path : Path to the CSV file}
                            {--truncate : Remove all existing historical_weather rows before import}';

    protected $description = 'Import historical_weather CSV with strict 7-column schema (id/timestamps auto-managed).';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        try {
            $result = app(HistoricalWeatherCsvImporter::class)->importFromPath($path, [
                'truncate' => (bool) $this->option('truncate'),
            ]);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (array_slice($result['errors'], 0, 10) as $error) {
            $this->warn($error);
        }
        if (count($result['errors']) > 10) {
            $this->warn('Additional row errors hidden to keep output concise.');
        }

        $this->info("Imported {$result['imported']} row(s). Skipped {$result['skipped']}.");

        Cache::forget('barangay_flood_hist_agg:v1');

        return self::SUCCESS;
    }
}
