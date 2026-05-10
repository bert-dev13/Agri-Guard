<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical weather records for CSV imports, filtering, and ML forecasting features.
     */
    public function up(): void
    {
        if (Schema::hasTable('historical_weather')) {
            return;
        }

        Schema::create('historical_weather', function (Blueprint $table): void {
            $table->id();

            $table->date('date')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');

            $table->decimal('rainfall', 10, 2)->default(0);
            $table->decimal('wind_speed', 10, 2)->nullable();
            $table->string('wind_direction', 32)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_weather');
    }
};
