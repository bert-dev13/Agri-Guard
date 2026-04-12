<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Default admin for AGRIGUARD (password is hashed via User model cast).
     * admin@agriguard.com / admin123
     */
    public function run(): void
    {
        // Do not overwrite existing accounts; only create if missing.
        if (User::query()->where('email', 'admin@agriguard.com')->exists()) {
            return;
        }

        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@agriguard.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'verification_attempts' => 0,
            'verification_locked_until' => null,
        ]);
    }
}
