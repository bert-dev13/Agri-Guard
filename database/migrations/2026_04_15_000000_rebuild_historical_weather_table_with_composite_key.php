<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('historical_weather')) {
            Schema::create('historical_weather', function (Blueprint $table): void {
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedTinyInteger('day');
                $table->float('rainfall')->nullable();
                $table->float('wind_speed')->nullable();
                $table->unsignedSmallInteger('wind_direction')->nullable();
                $table->primary(['year', 'month', 'day']);
            });

            return;
        }

        $existingRows = DB::table('historical_weather')->get();
        $normalized = [];

        foreach ($existingRows as $row) {
            $year = isset($row->year) ? (int) $row->year : null;
            $month = isset($row->month) ? (int) $row->month : null;
            $day = isset($row->day) ? (int) $row->day : null;
            if ($year === null || $month === null || $day === null || ! checkdate($month, $day, $year)) {
                continue;
            }

            $key = $year.'-'.$month.'-'.$day;
            $rainfall = isset($row->rainfall) && is_numeric((string) $row->rainfall) ? (float) $row->rainfall : null;
            $windSpeed = isset($row->wind_speed) && is_numeric((string) $row->wind_speed) ? (float) $row->wind_speed : null;
            $windDirection = isset($row->wind_direction) && is_numeric((string) $row->wind_direction)
                ? (int) $row->wind_direction
                : null;

            if ($rainfall !== null && $rainfall < 0) {
                $rainfall = null;
            }
            if ($windSpeed !== null && $windSpeed < 0) {
                $windSpeed = null;
            }
            if ($windDirection !== null && $windDirection < 0) {
                $windDirection = null;
            }

            $normalized[$key] = [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'rainfall' => $rainfall,
                'wind_speed' => $windSpeed,
                'wind_direction' => $windDirection,
            ];
        }

        Schema::drop('historical_weather');

        Schema::create('historical_weather', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->float('rainfall')->nullable();
            $table->float('wind_speed')->nullable();
            $table->unsignedSmallInteger('wind_direction')->nullable();
            $table->primary(['year', 'month', 'day']);
            $table->index(['year', 'month']);
        });

        foreach (array_chunk(array_values($normalized), 500) as $chunk) {
            DB::table('historical_weather')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_weather');

        Schema::create('historical_weather', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->decimal('rainfall', 10, 2)->default(0);
            $table->decimal('wind_speed', 10, 2)->nullable();
            $table->string('wind_direction', 32)->nullable();
            $table->primary(['year', 'month', 'day']);
        });
    }
};
