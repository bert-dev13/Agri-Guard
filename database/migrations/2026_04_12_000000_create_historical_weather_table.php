<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily historical weather rows aligned with CSV imports (YEAR, MONTH, DAY, RAINFALL, WIND_SPEED, WIND_DIRECTION).
     * Composite primary key; no surrogate id or timestamps (see HistoricalWeather model).
     */
    public function up(): void
    {
        Schema::create('historical_weather', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->decimal('rainfall', 10, 2)->default(0);
            $table->decimal('wind_speed', 10, 2)->nullable();
            $table->string('wind_direction', 32)->nullable();

            $table->primary(['year', 'month', 'day']);
            $table->index('month');
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_weather');
    }
};
