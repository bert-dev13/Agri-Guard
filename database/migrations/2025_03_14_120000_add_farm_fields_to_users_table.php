<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('crop_type')->nullable()->after('farm_barangay');
            $table->date('planting_date')->nullable()->after('crop_type');
            $table->string('farm_area')->nullable()->after('planting_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['crop_type', 'planting_date', 'farm_area']);
        });
    }
};
