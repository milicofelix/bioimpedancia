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
        if (app()->isProduction()) {
            $this->command?->warn('Seeders demo ignorados em produção. Use php artisan ricosty:create-admin para criar o primeiro administrador.');

            return;
        }

        User::query()->updateOrCreate([
            'email' => 'milicofelix@gmail.com',
        ], [
            'name' => 'Milico Félix',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password'),
        ]);

        User::query()->updateOrCreate([
            'email' => 'profissional@ricosty.local',
        ], [
            'name' => 'Profissional Ricosty',
            'role' => User::ROLE_PROFESSIONAL,
            'password' => Hash::make('password'),
        ]);

        User::query()->updateOrCreate([
            'email' => 'recepcao@ricosty.local',
        ], [
            'name' => 'Recepção Ricosty',
            'role' => User::ROLE_RECEPTION,
            'password' => Hash::make('password'),
        ]);

        $this->call(BioimpedanceDemoSeeder::class);
    }
}
