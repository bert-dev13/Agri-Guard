<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Treat -999 (missing-data sentinel) and any other negative rainfall as 0.
     */
    public function up(): void
    {
        DB::table('historical_weather')
            ->where('rainfall', '<', 0)
            ->update(['rainfall' => 0]);
    }

    /**
     * Reverse the migrations.
     * Cannot restore original -999 values; down() is a no-op.
     */
    public function down(): void
    {
        // Data fix: no reversible change
    }
};
