<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        $password = config('bioimpedance.demo_password') ?: Str::password(16);

        if (! config('bioimpedance.demo_password')) {
            $this->command?->warn('RICOSTY_DEMO_PASSWORD não foi definido. Senha demo temporária gerada para este seed: '.$password);
        }

        foreach (config('bioimpedance.demo_users', []) as $demoUser) {
            User::query()->updateOrCreate([
                'email' => $demoUser['email'],
            ], [
                'name' => $demoUser['name'],
                'role' => $demoUser['role'],
                'password' => $password,
            ]);
        }

        $this->call(BioimpedanceDemoSeeder::class);
    }
}
