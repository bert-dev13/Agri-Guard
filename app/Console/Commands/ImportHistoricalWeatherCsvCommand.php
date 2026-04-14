<?php

namespace App\Console\Commands;

use App\Services\HistoricalWeatherCsvImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportHistoricalWeatherCsvCommand extends Command
{
    protected $signature = 'historical-weather:import
                            {path : Path to the CSV file}
                            {--truncate : Remove all existing historical_weather rows before import}
                            {--no-header : Treat the first row as data (fixed order: year,month,day,rainfall,wind_speed,wind_direction)}';

    protected $description = 'Import historical_weather CSV using composite key (year, month, day).';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        try {
            $result = app(HistoricalWeatherCsvImporter::class)->importFromPath($path, [
                'truncate' => (bool) $this->option('truncate'),
                'has_header' => ! (bool) $this->option('no-header'),
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

        return self::SUCCESS;
    }
}
