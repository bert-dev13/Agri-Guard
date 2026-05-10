<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for common filter and aggregate queries (admin lists, barangay lookups, historical stats).
     */
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table): void {
            $table->index('municipality', 'barangays_municipality_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index('farm_municipality', 'users_farm_municipality_index');
        });

        Schema::table('historical_weather', function (Blueprint $table): void {
            $table->index('year', 'historical_weather_year_index');
            $table->index(['year', 'rainfall'], 'historical_weather_year_rainfall_index');
        });
    }

    public function down(): void
    {
        Schema::table('historical_weather', function (Blueprint $table): void {
            $table->dropIndex('historical_weather_year_rainfall_index');
            $table->dropIndex('historical_weather_year_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_farm_municipality_index');
        });

        Schema::table('barangays', function (Blueprint $table): void {
            $table->dropIndex('barangays_municipality_index');
        });
    }
};
