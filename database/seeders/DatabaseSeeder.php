<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@santovate.local'],
            ['name' => 'Santovate Admin', 'password' => 'Santovate123!', 'role' => 'admin', 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'sales@santovate.local'],
            ['name' => 'Santovate Sales', 'password' => 'Sales123!', 'role' => 'sales', 'is_active' => true]
        );
    }
}
