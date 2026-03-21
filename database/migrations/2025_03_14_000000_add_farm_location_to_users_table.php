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
            $table->string('farm_municipality')->nullable()->after('password');
            $table->string('farm_barangay')->nullable()->after('farm_municipality');
            $table->string('farm_purok_zone', 255)->nullable()->after('farm_barangay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['farm_municipality', 'farm_barangay', 'farm_purok_zone']);
        });
    }
};
