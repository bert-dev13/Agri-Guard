<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const MUNICIPALITY = 'Amulung';

    /**
     * Seed 30 realistic farmer accounts.
     *
     * - Does not overwrite existing users (skips if email exists)
     * - Uses real Amulung barangays (from DB if seeded; fallback to data file)
     */
    public function run(): void
    {
        $password = Hash::make('AgriGuard123!');

        $cropTypes = ['Rice', 'Corn'];
        // Keep values consistent with CropTimelineService::STAGE_ORDER (snake_case).
        $stages = ['planting', 'early_growth', 'vegetative', 'flowering', 'harvest'];
        $fieldConditions = ['Normal', 'Dry', 'Waterlogged', 'Pest risk', 'Nutrient stress'];

        $barangays = Barangay::query()
            ->where('municipality', self::MUNICIPALITY)
            ->orderBy('name')
            ->get(['id', 'name']);

        $fallbackBarangayNames = [];
        $fallbackPath = database_path('data/amulung_barangay_names.php');
        if (is_file($fallbackPath)) {
            $loaded = require $fallbackPath;
            if (is_array($loaded)) {
                $fallbackBarangayNames = array_values(array_filter($loaded, static fn ($v) => is_string($v) && trim($v) !== ''));
            }
        }

        $seed = self::farmerSeedRows();

        foreach ($seed as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '') {
                continue;
            }

            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $barangayId = null;
            $barangayName = null;

            if ($barangays->isNotEmpty()) {
                $pick = $barangays->random();
                $barangayId = (string) $pick->id;
                $barangayName = (string) $pick->name;
            } elseif (count($fallbackBarangayNames) > 0) {
                $barangayName = $fallbackBarangayNames[array_rand($fallbackBarangayNames)];
            }

            $cropType = $cropTypes[array_rand($cropTypes)];
            $stage = $stages[array_rand($stages)];

            User::create([
                'name' => $row['name'],
                'email' => $email,
                'password' => $password,
                'role' => 'farmer',

                'farm_municipality' => self::MUNICIPALITY,
                'farm_barangay' => $barangayName,
                'farm_barangay_code' => $barangayId,

                'crop_type' => $cropType,
                'farming_stage' => $stage,
                'planting_date' => now()->subDays(random_int(7, 140))->toDateString(),
                'farm_area' => (string) (round(random_int(50, 350) / 100, 2)), // 0.50–3.50 ha
                'field_condition' => $fieldConditions[array_rand($fieldConditions)],

                'email_verified_at' => now(),
                'verification_attempts' => 0,

                'created_at' => now()->subDays(random_int(0, 120)),
                'updated_at' => now()->subDays(random_int(0, 30)),
            ]);
        }
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    private static function farmerSeedRows(): array
    {
        $names = [
            'Juan Dela Cruz', 'Maria Santos', 'Ana Lucia Reyes', 'Marco Antonio Flores',
            'Teresa Villanueva', 'Ricardo Bautista', 'Lourdes Mendoza', 'Fernando Aquino',
            'Carmela Santiago', 'Eduardo Navarro', 'Rosario Delgado', 'Antonio Ramos',
            'Imelda Cortez', 'Roberto Salazar', 'Gloria Espinoza', 'Manuel Pascual',
            'Corazon Ignacio', 'Alberto Morales', 'Fe Torres', 'Danilo Castillo',
            'Marites Ocampo', 'Ramon Herrera', 'Divina Cruz', 'Nestor Villarin',
            'Yolanda Fabian', 'Orlando Mercado', 'Soledad Ramos', 'Felipe Domingo',
            'Esperanza Bautista', 'Cesar Agustin',
        ];

        $rows = [];
        foreach ($names as $index => $name) {
            $n = $index + 1;
            $rows[] = [
                'name' => $name,
                'email' => sprintf('farmer%02d@agriguard.ph', $n),
            ];
        }

        return $rows;
    }
}
