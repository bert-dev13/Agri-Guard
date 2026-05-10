<?php

namespace App\Services;

use App\Models\HistoricalWeather;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class HistoricalWeatherCsvImporter
{
    /**
     * CSV schema required by AgriGuard historical weather import.
     *
     * @var array<int, string>
     */
    private const REQUIRED_COLUMNS = [
        'date',
        'year',
        'month',
        'day',
        'rainfall',
        'wind_speed',
        'wind_direction',
    ];

    /**
     * @param  array{truncate?: bool}  $options
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
            $headerRow = fgetcsv($handle, 0, $delimiter);
            $lineNo = 1;
            if ($headerRow === false) {
                throw new RuntimeException('Unable to read header row.');
            }
            $headerRow = $this->trimTrailingEmptyColumns($headerRow);

            $map = $this->columnMapFromHeader($headerRow);
            if ($map === null) {
                throw new RuntimeException(
                    'Invalid column count in CSV input. Required headers only: date, year, month, day, rainfall, wind_speed, wind_direction.'
                );
            }

            $expectedColumnCount = count($headerRow);

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

                $row = $this->trimTrailingEmptyColumns($row);
                if (count($row) !== $expectedColumnCount) {
                    $skipped++;
                    $errors[] = "Line {$lineNo}: invalid column count in CSV input (expected {$expectedColumnCount}, got ".count($row).').';

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

            HistoricalWeather::clearRainfallUnitMultiplierCache();

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
        if (count($headerRow) !== count(self::REQUIRED_COLUMNS)) {
            return null;
        }

        $mapped = [];
        foreach ($headerRow as $index => $cell) {
            $key = $this->normalizeHeaderKey((string) $cell);
            if (in_array($key, self::REQUIRED_COLUMNS, true)) {
                $mapped[$key] = $index;
            }
        }

        foreach (self::REQUIRED_COLUMNS as $name) {
            if (! array_key_exists($name, $mapped)) {
                return null;
            }
        }

        if (count($mapped) !== count(self::REQUIRED_COLUMNS)) {
            return null;
        }

        return $mapped;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $map
     * @return array{date:string,year:int,month:int,day:int,rainfall:float|null,wind_speed:float|null,wind_direction:string|null,created_at:string,updated_at:string}|null
     */
    private function normalizeRow(array $row, array $map): ?array
    {
        $dateText = $this->stringOrNull($row[$map['date']] ?? null);
        if ($dateText === null) {
            return null;
        }

        $parsedDate = $this->parseDate($dateText);
        if ($parsedDate === null) {
            return null;
        }

        $year = $this->intOrNull($row[$map['year']] ?? null);
        $month = $this->intOrNull($row[$map['month']] ?? null);
        $day = $this->intOrNull($row[$map['day']] ?? null);

        if ($year === null || $month === null || $day === null) {
            return null;
        }

        if ($year < 1900 || $year > 2100 || ! checkdate($month, $day, $year)) {
            return null;
        }

        if (
            $year !== (int) $parsedDate->format('Y')
            || $month !== (int) $parsedDate->format('m')
            || $day !== (int) $parsedDate->format('d')
        ) {
            return null;
        }

        $rainfall = $this->floatOrNull($row[$map['rainfall']] ?? null);
        $windSpeed = $this->floatOrNull($row[$map['wind_speed']] ?? null);
        $windDirection = $this->stringOrNull($row[$map['wind_direction']] ?? null);

        if ($rainfall !== null && $rainfall < 0) {
            $rainfall = null;
        }
        if ($windSpeed !== null && $windSpeed < 0) {
            $windSpeed = null;
        }
        $now = now()->toDateTimeString();

        return [
            'date' => $parsedDate->toDateString(),
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'rainfall' => $rainfall,
            'wind_speed' => $windSpeed,
            'wind_direction' => $windDirection,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<int, array{date:string,year:int,month:int,day:int,rainfall:float|null,wind_speed:float|null,wind_direction:string|null,created_at:string,updated_at:string}>  $batch
     */
    private function persistBatch(array $batch): int
    {
        DB::transaction(function () use ($batch): void {
            HistoricalWeather::query()->upsert(
                $batch,
                ['date'],
                ['year', 'month', 'day', 'rainfall', 'wind_speed', 'wind_direction', 'updated_at']
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

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        return $raw === '' ? null : $raw;
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeHeaderKey(string $raw): string
    {
        $key = strtolower(trim($raw));
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;

        return str_replace([' ', '-'], '_', $key);
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<int, string|null>
     */
    private function trimTrailingEmptyColumns(array $row): array
    {
        while ($row !== []) {
            $last = end($row);
            if (trim((string) $last) !== '') {
                break;
            }

            array_pop($row);
        }

        return array_values($row);
    }
}
