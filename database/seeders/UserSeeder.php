<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed normal user accounts for AGRIGUARD.
     * farmer@agriguard.com = farmer123
     * maria@agriguard.com = maria123
     * demo@agriguard.com = demo123
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'John Farmer',
                'email' => 'farmer@agriguard.com',
                'password' => 'farmer123',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'maria@agriguard.com',
                'password' => 'maria123',
            ],
            [
                'name' => 'Demo User',
                'email' => 'demo@agriguard.com',
                'password' => 'demo123',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                ]
            );
        }
    }
}
