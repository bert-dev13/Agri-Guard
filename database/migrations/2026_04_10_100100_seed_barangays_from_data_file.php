<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULT_MUNICIPALITY = 'Amulung';

    public function up(): void
    {
        if (DB::table('barangays')->count() > 0) {
            return;
        }

        $names = require database_path('data/amulung_barangay_names.php');
        if (! is_array($names)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($names as $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'municipality' => self::DEFAULT_MUNICIPALITY,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('barangays')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('barangays')->where('municipality', self::DEFAULT_MUNICIPALITY)->delete();
    }
};
