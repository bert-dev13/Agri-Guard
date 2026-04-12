<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;

class BarangaySeeder extends Seeder
{
    private const DEFAULT_MUNICIPALITY = 'Amulung';

    public function run(): void
    {
        $names = require database_path('data/amulung_barangay_names.php');
        if (! is_array($names)) {
            return;
        }

        foreach ($names as $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            Barangay::query()->firstOrCreate(
                [
                    'name' => $name,
                    'municipality' => self::DEFAULT_MUNICIPALITY,
                ],
                []
            );
        }
    }
}
