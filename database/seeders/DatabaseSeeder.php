<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'zeffali7@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('secret123'),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'rahul@123'],
            [
                'name' => 'Rahul',
                'password' => Hash::make('secret123'),
                'role' => 'user',
            ]
        );

        $this->call(CategoryAndProductSeeder::class);
    }
}
