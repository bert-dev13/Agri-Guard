<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('barangays') || ! Schema::hasTable('users')) {
            return;
        }

        $users = DB::table('users')
            ->select(['id', 'farm_municipality', 'farm_barangay', 'farm_barangay_code'])
            ->whereNotNull('farm_barangay')
            ->where('farm_barangay', '!=', '')
            ->get();

        foreach ($users as $user) {
            $name = trim((string) $user->farm_barangay);
            $mun = trim((string) ($user->farm_municipality ?? ''));
            if ($name === '') {
                continue;
            }
            if ($mun === '') {
                $mun = 'Amulung';
            }

            $id = DB::table('barangays')
                ->where('municipality', $mun)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->value('id');

            if ($id === null) {
                continue;
            }

            $current = trim((string) ($user->farm_barangay_code ?? ''));
            if ($current !== '' && $current === (string) $id) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'farm_barangay_code' => (string) $id,
                'farm_barangay' => $name,
            ]);
        }
    }

    public function down(): void
    {
        // Non-reversible: cannot restore prior PSGC codes from barangay ids.
    }
};
