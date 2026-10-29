<?php

use App\Models\User;
use App\Services\Screening\ScreeningPrivacyService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ricosty:create-admin', function () {
    $name = $this->ask('Nome do administrador');
    $email = $this->ask('E-mail do administrador');
    $password = $this->secret('Senha do administrador');
    $confirmation = $this->secret('Confirme a senha');

    if ($password !== $confirmation) {
        $this->error('As senhas não conferem.');

        return 1;
    }

    if (strlen((string) $password) < 12) {
        $this->error('Use uma senha com pelo menos 12 caracteres.');

        return 1;
    }

    User::query()->updateOrCreate([
        'email' => $email,
    ], [
        'name' => $name,
        'role' => User::ROLE_ADMIN,
        'password' => $password,
        'inactivated_at' => null,
    ]);

    $this->info('Administrador criado/atualizado com sucesso.');

    return 0;
})->purpose('Create or update the first Ricosty administrator securely');

Artisan::command('screening:apply-retention', function () {
    $processed = app(ScreeningPrivacyService::class)->applyExpiredRetention();
    $this->info("Triagens anonimizadas pela política de retenção: {$processed}.");

    return 0;
})->purpose('Anonymize screening sessions whose retention period has expired');

Schedule::command('screening:apply-retention')->dailyAt('02:30')->withoutOverlapping();
