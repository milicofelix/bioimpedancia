<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
