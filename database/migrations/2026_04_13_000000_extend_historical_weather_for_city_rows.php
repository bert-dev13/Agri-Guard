<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow multiple rows per calendar day (one per city) and store optional observation fields.
     * CSV imports continue to use city '' (empty string) as the default series for rainfall analytics.
     */
    public function up(): void
    {
        Schema::table('historical_weather', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('historical_weather', function (Blueprint $table) {
            // No ->after(): PostgreSQL compatibility (e.g. Render).
            $table->string('city', 64)->default('');
            $table->decimal('temperature', 6, 2)->nullable();
            $table->unsignedTinyInteger('humidity')->nullable();
            $table->string('weather_condition', 32)->nullable();
            $table->primary(['city', 'year', 'month', 'day']);
        });
    }

    public function down(): void
    {
        Schema::table('historical_weather', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('historical_weather', function (Blueprint $table) {
            $table->dropColumn(['city', 'temperature', 'humidity', 'weather_condition']);
            $table->primary(['year', 'month', 'day']);
        });
    }
};
