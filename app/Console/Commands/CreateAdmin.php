<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'arcade:create-admin {email} {--name=Admin}';

    protected $description = 'Crée (ou promeut) un compte administrateur avec un mot de passe saisi de façon masquée';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email invalide.');

            return self::FAILURE;
        }

        $password = $this->secret('Mot de passe (12 caractères minimum)');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Mot de passe trop court (12 caractères minimum).');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $user->name ?: $this->option('name'),
            'password' => $password,
            'role' => 'admin',
        ])->save();

        $this->info("Administrateur prêt : {$email}");

        return self::SUCCESS;
    }
}
