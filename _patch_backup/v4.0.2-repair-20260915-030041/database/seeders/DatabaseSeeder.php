<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@santovate.com'],
            [
                'name' => 'Santovate Admin',
                'password' => 'Santovate123!',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'sales@santovate.com'],
            [
                'name' => 'Santovate Sales',
                'password' => 'Sales123!',
                'role' => 'sales',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'ahmadmazkur@santovate.com'],
            [
                'name' => 'Ahmad Mazkur',
                'password' => 'Mazkur!@#',
                'role' => 'sales',
                'is_active' => true,
            ]
        );
    }
}