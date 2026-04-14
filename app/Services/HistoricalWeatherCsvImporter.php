<?php

namespace App\Services;

use App\Models\HistoricalWeather;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class HistoricalWeatherCsvImporter
{
    /**
     * @param  array{truncate?: bool, has_header?: bool}  $options
     * @return array{imported:int, skipped:int, errors:array<int, string>}
     */
    public function importFromPath(string $path, array $options = []): array
    {
        if (! File::isReadable($path)) {
            throw new RuntimeException('File is not readable.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open uploaded CSV file.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new RuntimeException('CSV file is empty.');
            }

            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? '';
            rewind($handle);
            if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
                fread($handle, 3);
            }

            $delimiter = $this->detectDelimiter($firstLine);
            $hasHeader = $options['has_header'] ?? true;

            $map = null;
            $lineNo = 0;
            if ($hasHeader) {
                $headerRow = fgetcsv($handle, 0, $delimiter);
                $lineNo = 1;
                if ($headerRow === false) {
                    throw new RuntimeException('Unable to read header row.');
                }

                $map = $this->columnMapFromHeader($headerRow);
                if ($map === null) {
                    throw new RuntimeException(
                        'Invalid CSV header. Required columns: year, month, day, rainfall, wind_speed, wind_direction.'
                    );
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

            if (($options['truncate'] ?? false) === true) {
                HistoricalWeather::query()->delete();
            }

            $batch = [];
            $imported = 0;
            $skipped = 0;
            $errors = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNo++;
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $normalized = $this->normalizeRow($row, $map);
                if ($normalized === null) {
                    $skipped++;
                    $errors[] = "Line {$lineNo}: invalid row format or date.";
                    continue;
                }

                $batch[] = $normalized;

                if (count($batch) >= 500) {
                    $imported += $this->persistBatch($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $imported += $this->persistBatch($batch);
            }

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $sample): string
    {
        $commaCount = substr_count($sample, ',');
        $tabCount = substr_count($sample, "\t");

        return $tabCount > $commaCount ? "\t" : ',';
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @return array<string, int>|null
     */
    private function columnMapFromHeader(array $headerRow): ?array
    {
        $aliases = [
            'year' => 'year',
            'month' => 'month',
            'day' => 'day',
            'rainfall' => 'rainfall',
            'wind_speed' => 'wind_speed',
            'wind_spe' => 'wind_speed',
            'windspeed' => 'wind_speed',
            'wind_direction' => 'wind_direction',
            'winddirection' => 'wind_direction',
        ];

        $mapped = [];
        foreach ($headerRow as $index => $cell) {
            $key = strtolower(trim((string) $cell));
            $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
            $key = str_replace([' ', '-'], '_', $key);

            if (isset($aliases[$key])) {
                $mapped[$aliases[$key]] = $index;
            }
        }

        $required = ['year', 'month', 'day', 'rainfall', 'wind_speed', 'wind_direction'];
        foreach ($required as $name) {
            if (! array_key_exists($name, $mapped)) {
                return null;
            }
        }

        return $mapped;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $map
     * @return array{year:int,month:int,day:int,rainfall:float|null,wind_speed:float|null,wind_direction:int|null}|null
     */
    private function normalizeRow(array $row, array $map): ?array
    {
        $year = $this->intOrNull($row[$map['year']] ?? null);
        $month = $this->intOrNull($row[$map['month']] ?? null);
        $day = $this->intOrNull($row[$map['day']] ?? null);

        if ($year === null || $month === null || $day === null) {
            return null;
        }

        if ($year < 1900 || $year > 2100 || ! checkdate($month, $day, $year)) {
            return null;
        }

        $rainfall = $this->floatOrNull($row[$map['rainfall']] ?? null);
        $windSpeed = $this->floatOrNull($row[$map['wind_speed']] ?? null);
        $windDirection = $this->intOrNull($row[$map['wind_direction']] ?? null);

        if ($rainfall !== null && $rainfall < 0) {
            $rainfall = null;
        }
        if ($windSpeed !== null && $windSpeed < 0) {
            $windSpeed = null;
        }
        if ($windDirection !== null && $windDirection < 0) {
            $windDirection = null;
        }

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'rainfall' => $rainfall,
            'wind_speed' => $windSpeed,
            'wind_direction' => $windDirection,
        ];
    }

    /**
     * @param  array<int, array{year:int,month:int,day:int,rainfall:float|null,wind_speed:float|null,wind_direction:int|null}>  $batch
     */
    private function persistBatch(array $batch): int
    {
        DB::transaction(function () use ($batch): void {
            HistoricalWeather::query()->upsert(
                $batch,
                ['year', 'month', 'day'],
                ['rainfall', 'wind_speed', 'wind_direction']
            );
        });

        return count($batch);
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

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }
}
