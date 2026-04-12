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

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'year',
        'month',
        'day',
        'rainfall',
        'wind_speed',
        'wind_direction',
    ];

    protected function casts(): array
    {
        return [
            'rainfall' => 'decimal:2',
            'wind_speed' => 'decimal:2',
        ];
    }

    /**
     * Composite primary key (year, month, day) for update/delete queries.
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query->where('year', '=', $this->getAttribute('year'))
            ->where('month', '=', $this->getAttribute('month'))
            ->where('day', '=', $this->getAttribute('day'));
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
        return static::query()
            ->validCalendarRows()
            ->selectRaw('month, AVG(GREATEST(COALESCE(rainfall, 0), 0)) as avg_rain')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Get total rainfall per year. Negative values are treated as 0 so totals are never negative.
     */
    public static function totalRainfallByYear(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->validCalendarRows()
            ->selectRaw('year, SUM(GREATEST(COALESCE(rainfall, 0), 0)) as total_rainfall')
            ->groupBy('year')
            ->orderBy('year')
            ->get();
    }

    /**
     * Count of days with heavy rainfall (>= 50 mm).
     */
    public static function heavyRainfallCount(): int
    {
        return (int) static::query()
            ->validCalendarRows()
            ->where('rainfall', '>=', 50)
            ->count();
    }

    /**
     * Heavy rainfall frequency grouped (e.g. by year or month) for charts.
     */
    public static function heavyRainfallByYear(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->validCalendarRows()
            ->where('rainfall', '>=', 50)
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
        $row = static::query()
            ->validCalendarRows()
            ->where('month', $month)
            ->selectRaw('AVG(GREATEST(COALESCE(rainfall, 0), 0)) as avg_rain')
            ->first();

        return $row && $row->avg_rain !== null ? (float) $row->avg_rain : null;
    }
}
