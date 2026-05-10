<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HistoricalWeather extends Model
{
    protected $table = 'historical_weather';

    /** Ignore junk rows (e.g. CSV headers imported as 0,0,0). */
    private const MIN_YEAR = 1900;

    private const MAX_YEAR = 2100;

    protected $primaryKey = 'id';

    public $timestamps = true;

    private static ?float $rainfallUnitMultiplierCache = null;

    protected $fillable = [
        'year',
        'month',
        'day',
        'rainfall',
        'wind_speed',
        'wind_direction',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'rainfall' => 'float',
            'wind_speed' => 'float',
            'wind_direction' => 'string',
            'date' => 'date',
        ];
    }

    /**
     * Realistic calendar rows only (excludes mis-imported headers like year=0).
     */
    public static function scopeValidCalendarRows(Builder $query): Builder
    {
        return $query
            ->whereBetween('year', [self::MIN_YEAR, self::MAX_YEAR])
            ->whereBetween('month', [1, 12])
            ->whereBetween('day', [1, 31]);
    }

    /**
     * Get average rainfall per month (all years). Negative values are treated as 0.
     */
    public static function monthlyRainfallTrend(): \Illuminate\Database\Eloquent\Collection
    {
        $multiplier = static::rainfallUnitMultiplier();

        return static::query()
            ->validCalendarRows()
            ->selectRaw('month, AVG(GREATEST(COALESCE(rainfall, 0), 0) * ?) as avg_rain', [$multiplier])
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Get total rainfall per year. Negative values are treated as 0 so totals are never negative.
     */
    public static function totalRainfallByYear(): \Illuminate\Database\Eloquent\Collection
    {
        $multiplier = static::rainfallUnitMultiplier();

        return static::query()
            ->validCalendarRows()
            ->selectRaw('year, SUM(GREATEST(COALESCE(rainfall, 0), 0) * ?) as total_rainfall', [$multiplier])
            ->groupBy('year')
            ->orderBy('year')
            ->get();
    }

    /**
     * Count of days with heavy rainfall (>= 50 mm).
     */
    public static function heavyRainfallCount(): int
    {
        $threshold = static::rawHeavyRainThreshold();

        return (int) static::query()
            ->validCalendarRows()
            ->where('rainfall', '>=', $threshold)
            ->count();
    }

    /**
     * Heavy rainfall frequency grouped (e.g. by year or month) for charts.
     */
    public static function heavyRainfallByYear(): \Illuminate\Database\Eloquent\Collection
    {
        $threshold = static::rawHeavyRainThreshold();

        return static::query()
            ->validCalendarRows()
            ->where('rainfall', '>=', $threshold)
            ->selectRaw('year, COUNT(*) as count')
            ->groupBy('year')
            ->orderBy('year')
            ->get();
    }

    /**
     * Historical average rainfall for a given month (1-12). Negative values are treated as 0.
     */
    public static function averageRainfallForMonth(int $month): ?float
    {
        $multiplier = static::rainfallUnitMultiplier();

        $row = static::query()
            ->validCalendarRows()
            ->where('month', $month)
            ->selectRaw('AVG(GREATEST(COALESCE(rainfall, 0), 0) * ?) as avg_rain', [$multiplier])
            ->first();

        return $row && $row->avg_rain !== null ? (float) $row->avg_rain : null;
    }

    private static function rainfallUnitMultiplier(): float
    {
        if (self::$rainfallUnitMultiplierCache !== null) {
            return self::$rainfallUnitMultiplierCache;
        }

        $maxRainfall = (float) (static::query()
            ->validCalendarRows()
            ->max('rainfall') ?? 0.0);

        // Heuristic: datasets with max <= 2 are typically stored in meters, convert to millimeters.
        self::$rainfallUnitMultiplierCache = $maxRainfall > 0 && $maxRainfall <= 2.0 ? 1000.0 : 1.0;

        return self::$rainfallUnitMultiplierCache;
    }

    /** Reset unit heuristic cache after bulk CSV imports (long-lived PHP workers). */
    public static function clearRainfallUnitMultiplierCache(): void
    {
        self::$rainfallUnitMultiplierCache = null;
    }

    private static function rawHeavyRainThreshold(): float
    {
        return 50.0 / static::rainfallUnitMultiplier();
    }
}
