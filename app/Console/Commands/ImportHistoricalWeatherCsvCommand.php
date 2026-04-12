<?php

namespace App\Console\Commands;

use App\Models\HistoricalWeather;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportHistoricalWeatherCsvCommand extends Command
{
    protected $signature = 'historical-weather:import
                            {path : Path to the CSV file}
                            {--truncate : Remove all existing historical_weather rows before import}
                            {--delimiter=, : Single character field delimiter}
                            {--no-header : Treat the first row as data (fixed order: YEAR,MONTH,DAY,RAINFALL,WIND_SPE|SPEED,WIND_DIR)}';

    protected $description = 'Import historical_weather from CSV (headers case-insensitive: year/Year/YEAR). Negative rainfall (e.g. -1) is stored as 0.';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! File::isReadable($path)) {
            $this->error('File not readable: '.$path);

            return self::FAILURE;
        }

        $delimiter = (string) $this->option('delimiter');
        if ($delimiter === '') {
            $delimiter = ',';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('Could not open file.');

            return self::FAILURE;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            $this->error('CSV is empty.');

            return self::FAILURE;
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        rewind($handle);
        if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
            fread($handle, 3);
        }

        $noHeader = (bool) $this->option('no-header');
        $map = null;
        if (! $noHeader) {
            $headerRow = fgetcsv($handle, 0, $delimiter);
            if ($headerRow === false) {
                fclose($handle);
                $this->error('Could not read header row.');

                return self::FAILURE;
            }
            $map = $this->columnMapFromHeader($headerRow);
            if ($map === null) {
                fclose($handle);
                $this->error('Header row must include YEAR, MONTH, DAY, RAINFALL, and WIND_SPE (or WIND_SPEED). WIND_DIRECTION is optional.');
                $this->line('Tip: if line 1 is data (not labels), run with --no-header');

                return self::FAILURE;
            }
        } else {
            $map = [
                'year' => 0,
                'month' => 1,
                'day' => 2,
                'rainfall' => 3,
                'wind_speed' => 4,
                'wind_direction' => 5,
            ];
        }

        if ($this->option('truncate')) {
            HistoricalWeather::query()->delete();
            $this->info('Truncated historical_weather.');
        }

        $batch = [];
        $imported = 0;
        $skipped = 0;
        $lineNo = $noHeader ? 0 : 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNo++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $indices = [
                $map['year'],
                $map['month'],
                $map['day'],
                $map['rainfall'],
                $map['wind_speed'],
            ];
            if (isset($map['wind_direction']) && $map['wind_direction'] !== null) {
                $indices[] = $map['wind_direction'];
            }
            $expectedCols = max($indices) + 1;
            if (count($row) < $expectedCols) {
                $this->warn("Line {$lineNo}: expected at least {$expectedCols} columns, got ".count($row).' — skipped.');
                $skipped++;

                continue;
            }

            $year = $this->intCell($row[$map['year']] ?? null);
            $month = $this->intCell($row[$map['month']] ?? null);
            $day = $this->intCell($row[$map['day']] ?? null);
            if ($year === null || $month === null || $day === null) {
                $this->warn("Line {$lineNo}: invalid year/month/day — skipped.");
                $skipped++;

                continue;
            }

            if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
                $this->warn("Line {$lineNo}: date out of range ({$year}-{$month}-{$day}) — skipped.");
                $skipped++;

                continue;
            }

            if (! checkdate($month, $day, $year)) {
                $this->warn("Line {$lineNo}: invalid calendar date {$year}-{$month}-{$day} — skipped.");
                $skipped++;

                continue;
            }

            try {
                Carbon::create($year, $month, $day);
            } catch (\Throwable) {
                $this->warn("Line {$lineNo}: invalid date {$year}-{$month}-{$day} — skipped.");
                $skipped++;

                continue;
            }

            $rainRaw = trim((string) ($row[$map['rainfall']] ?? ''));
            $rainfall = $rainRaw === '' ? 0.0 : (float) $rainRaw;
            // Match DB fix migration: treat missing/sentinel negatives (e.g. -1, -999) as no rain
            if ($rainfall < 0) {
                $rainfall = 0.0;
            }

            $windSpeedVal = $row[$map['wind_speed']] ?? null;
            $windSpeed = $windSpeedVal === null || trim((string) $windSpeedVal) === ''
                ? null
                : round((float) $windSpeedVal, 2);

            $windDirVal = null;
            if (isset($map['wind_direction']) && $map['wind_direction'] !== null) {
                $windDirVal = $row[$map['wind_direction']] ?? null;
            }
            $windDir = $windDirVal === null || trim((string) $windDirVal) === ''
                ? null
                : mb_substr(trim((string) $windDirVal), 0, 32);

            if ($this->looksLikeCsvHeaderToken($windDir)) {
                $this->warn("Line {$lineNo}: looks like a header row — skipped.");
                $skipped++;

                continue;
            }

            $batch[] = [
                'city' => '',
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'rainfall' => round($rainfall, 2),
                'wind_speed' => $windSpeed,
                'wind_direction' => $windDir,
            ];

            if (count($batch) >= 500) {
                HistoricalWeather::query()->upsert(
                    $batch,
                    ['city', 'year', 'month', 'day'],
                    ['rainfall', 'wind_speed', 'wind_direction']
                );
                $imported += count($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            HistoricalWeather::query()->upsert(
                $batch,
                ['city', 'year', 'month', 'day'],
                ['rainfall', 'wind_speed', 'wind_direction']
            );
            $imported += count($batch);
        }

        $this->info("Imported {$imported} row(s). Skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @return array<string, int>|null
     */
    private function columnMapFromHeader(array $headerRow): ?array
    {
        $norm = [];
        foreach ($headerRow as $i => $cell) {
            $key = strtoupper(trim((string) $cell, " \t\n\r\0\x0B"));
            $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
            if ($key === 'WIND_SPE') {
                $key = 'WIND_SPEED';
            }
            $norm[$key] = $i;
        }

        $required = ['YEAR', 'MONTH', 'DAY', 'RAINFALL'];
        foreach ($required as $r) {
            if (! isset($norm[$r])) {
                return null;
            }
        }

        $windIdx = $norm['WIND_SPEED'] ?? null;
        if ($windIdx === null) {
            return null;
        }

        return [
            'year' => $norm['YEAR'],
            'month' => $norm['MONTH'],
            'day' => $norm['DAY'],
            'rainfall' => $norm['RAINFALL'],
            'wind_speed' => $windIdx,
            'wind_direction' => $norm['WIND_DIRECTION'] ?? null,
        ];
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function intCell(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (int) $s;
    }

    private function looksLikeCsvHeaderToken(?string $windDir): bool
    {
        if ($windDir === null || $windDir === '') {
            return false;
        }

        $tokens = ['YEAR', 'MONTH', 'DAY', 'RAINFALL', 'WIND_SPEED', 'WIND_SPE', 'WIND_DIRECTION'];

        return in_array(strtoupper(trim($windDir)), $tokens, true);
    }
}
