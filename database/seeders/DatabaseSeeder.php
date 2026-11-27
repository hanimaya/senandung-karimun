<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::updateOrCreate(
            ['email' => 'superadmin@sitakar.com'],
            [
                'name'     => 'Super Administrator',
                'password' => bcrypt('password'),
                'role'     => 'super_admin',
            ]
        );

        // Admin biasa
        User::updateOrCreate(
            ['email' => 'admin@sitakar.com'],
            [
                'name'     => 'Administrator',
                'password' => bcrypt('password'),
                'role'     => 'admin',
            ]
        );
    }
}
