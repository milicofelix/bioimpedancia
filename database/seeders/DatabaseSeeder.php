<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'milicofelix@gmail.com',
        ], [
            'name' => 'Milico Félix',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password'),
        ]);

        User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'role' => User::ROLE_PROFESSIONAL,
            'password' => Hash::make('password'),
        ]);
    }
}
