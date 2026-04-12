<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align stored farming_stage values with the five canonical growth stages.
     */
    public function up(): void
    {
        $map = [
            'land_preparation' => 'planting',
            'growing' => 'vegetative',
            'flowering_fruiting' => 'flowering',
            'harvesting' => 'harvest',
        ];

        foreach ($map as $from => $to) {
            DB::table('users')->where('farming_stage', $from)->update(['farming_stage' => $to]);
        }
    }

    public function down(): void
    {
        $reverse = [
            'vegetative' => 'growing',
            'flowering' => 'flowering_fruiting',
            'harvest' => 'harvesting',
        ];

        foreach ($reverse as $from => $to) {
            DB::table('users')->where('farming_stage', $from)->update(['farming_stage' => $to]);
        }
    }
};
