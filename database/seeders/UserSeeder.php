<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const MUNICIPALITY = 'Amulung';

    /** Fixed farmer accounts for staging / Render smoke tests (skipped if email already exists). */
    public const DEPLOY_TEST_FARMER_1_EMAIL = 'juan.delacruz@agriguard.test';

    public const DEPLOY_TEST_FARMER_2_EMAIL = 'maria.santos@agriguard.test';

    /** Password for both deploy test farmers (plain; stored via same hashing as other seeded users). */
    public const DEPLOY_TEST_FARMER_PASSWORD = 'Test1234!';

    /**
     * Seed realistic user accounts (2 admins, 2 deploy-test farmers, 28 demo farmers).
     *
     * - Does not overwrite existing users (skips if email exists)
     * - Uses real Amulung barangays (from DB if seeded; fallback to data file)
     */
    public function run(): void
    {
        $password = Hash::make('AgriGuard123!');
        $deployTestPassword = Hash::make(self::DEPLOY_TEST_FARMER_PASSWORD);

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
            // Deploy / QA: log in on production after migrate + db:seed (see Dockerfile CMD).
            [
                'role' => 'farmer',
                'name' => 'Juan Dela Cruz',
                'email' => self::DEPLOY_TEST_FARMER_1_EMAIL,
            ],
            [
                'role' => 'farmer',
                'name' => 'Maria Santos',
                'email' => self::DEPLOY_TEST_FARMER_2_EMAIL,
            ],
        ];

        // 28 farmers — no Faker here so `composer install --no-dev` (Render Docker) can still seed.
        $seed = array_merge($seed, self::demoFarmerSeedRows());

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

            $rowPassword = in_array($email, [self::DEPLOY_TEST_FARMER_1_EMAIL, self::DEPLOY_TEST_FARMER_2_EMAIL], true)
                ? $deployTestPassword
                : $password;

            User::create([
                'name' => $row['name'],
                'email' => $email,
                'password' => $rowPassword,
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

    /**
     * @return list<array{role: string, name: string, email: string}>
     */
    private static function demoFarmerSeedRows(): array
    {
        $names = [
            'Ana Lucia Reyes', 'Marco Antonio Flores', 'Teresa Villanueva', 'Ricardo Bautista',
            'Lourdes Mendoza', 'Fernando Aquino', 'Carmela Santiago', 'Eduardo Navarro',
            'Rosario Delgado', 'Antonio Ramos', 'Imelda Cortez', 'Roberto Salazar',
            'Gloria Espinoza', 'Manuel Pascual', 'Corazon Ignacio', 'Alberto Morales',
            'Fe Torres', 'Danilo Castillo', 'Marites Ocampo', 'Ramon Herrera',
            'Divina Cruz', 'Nestor Villarin', 'Yolanda Fabian', 'Orlando Mercado',
            'Soledad Ramos', 'Felipe Domingo', 'Esperanza Bautista', 'Cesar Agustin',
        ];

        $rows = [];
        foreach ($names as $index => $name) {
            $n = $index + 1;
            $rows[] = [
                'role' => 'farmer',
                'name' => $name,
                'email' => sprintf('farmer-demo-%02d@agriguard.seed', $n),
            ];
        }

        return $rows;
    }
}
