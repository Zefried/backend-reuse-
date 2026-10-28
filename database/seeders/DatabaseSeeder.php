<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Niche;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create or update the Super Admin User
        User::updateOrCreate(
            ['email' => 'zeffali7@gmail.com'],
            [
                'name'     => 'zeffali7',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // 2. Create the "Interior Design" Niche if needed
        $interiorNiche = Niche::firstOrCreate(
            ['niche_name' => 'Interior Design'],
            ['is_active' => true]
        );

        // 3. Seed Business User (Abc interior)
        User::updateOrCreate(
            ['email' => 'test@123'],
            [
                'name'        => 'Abc interior',
                'phone'       => '9966554485',
                'password'    => Hash::make('password'),
                'role'        => 'business',
                'business_id' => 'BUS-QVTE1BKU',
                'niche_id'    => $interiorNiche->id,
                'bio'         => 'Interior design studio specializing in modular kitchens, living spaces, and turnkey home decor.',
            ]
        );
    }
}
