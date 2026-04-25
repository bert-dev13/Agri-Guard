<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed 2 functional admin accounts.
     */
    public function run(): void
    {
        $password = Hash::make('Admin123!');
        $admins = [
            [
                'name' => 'Admin-1',
                'email' => 'Admin1@agriguard.ph',
            ],
            [
                'name' => 'Admin-2',
                'email' => 'Admin2@agriguard.ph',
            ],
        ];

        foreach ($admins as $admin) {
            $email = strtolower(trim($admin['email']));
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            User::create([
                'name' => $admin['name'],
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'email_verified_at' => now(),
                'email_verification_code' => null,
                'email_verification_expires_at' => null,
                'verification_attempts' => 0,
                'verification_locked_until' => null,
            ]);
        }
    }
}
