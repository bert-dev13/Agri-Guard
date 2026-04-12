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
     * Seed 30 realistic user accounts (28 farmers, 2 admins).
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

        $faker = fake('en_PH');

        // 2 admins (real-looking, non-demo).
        $seed = [
            [
                'role' => 'admin',
                'name' => 'Maria Theresa Dela Cruz',
                'email' => 'mariatheresa.delacruz@agriguard.ph',
            ],
            [
                'role' => 'admin',
                'name' => 'Jose Miguel Santos',
                'email' => 'josemiguel.santos@agriguard.ph',
            ],
        ];

        // 28 farmers (real-looking Filipino names + realistic farm data).
        for ($i = 0; $i < 28; $i++) {
            $seed[] = [
                'role' => 'farmer',
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
            ];
        }

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

            // Some admins may not need farm profile, but we still keep records "usable" and not null-heavy.
            $isAdmin = ($row['role'] ?? 'farmer') === 'admin';
            $userCrop = $isAdmin ? null : $cropType;
            $userStage = $isAdmin ? null : $stage;

            User::create([
                'name' => $row['name'],
                'email' => $email,
                'password' => $password,
                'role' => $row['role'] ?? 'farmer',

                'farm_municipality' => self::MUNICIPALITY,
                'farm_barangay' => $barangayName,
                'farm_barangay_code' => $barangayId,

                'crop_type' => $userCrop,
                'farming_stage' => $userStage,
                'planting_date' => $isAdmin ? null : now()->subDays(random_int(7, 140))->toDateString(),
                'farm_area' => $isAdmin ? null : (string) (round(random_int(50, 350) / 100, 2)), // 0.50–3.50 ha
                'field_condition' => $isAdmin ? null : $fieldConditions[array_rand($fieldConditions)],

                'email_verified_at' => now(),
                'verification_attempts' => 0,

                'created_at' => now()->subDays(random_int(0, 120)),
                'updated_at' => now()->subDays(random_int(0, 30)),
            ]);
        }
    }
}
