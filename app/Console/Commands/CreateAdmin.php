<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Crea un amministratore per autorizzare ChatGPT (nessuna registrazione pubblica)';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Nome'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Password (almeno 12 caratteri)'),
            'password_confirmation' => $this->secret('Ripeti password'),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|confirmed',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User($validator->safe()->except('password_confirmation'));
        $user->is_admin = true;
        $user->save();
        $this->info('Amministratore creato. Accedi a /login per collegare ChatGPT.');

        return self::SUCCESS;
    }
}
