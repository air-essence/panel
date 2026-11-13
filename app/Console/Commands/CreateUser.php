<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    protected $signature = 'panel:dodaj-uzytkownika {email} {--name=} {--role=pracownik : pracownik, kierownik lub admin}';

    protected $description = 'Dodaje konto użytkownika panelu (hasło podajesz w konsoli)';

    public function handle(): int
    {
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->option('name') ?: $this->ask('Imię i nazwisko'),
            'role' => $this->option('role'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = $this->secret('Hasło (min. 10 znaków)');
        if (strlen((string) $password) < 10) {
            $this->error('Hasło musi mieć co najmniej 10 znaków.');

            return self::FAILURE;
        }

        User::create([...$data, 'password' => $password, 'is_active' => true]);
        $this->info("Dodano użytkownika {$data['email']} z rolą {$data['role']}.");

        return self::SUCCESS;
    }
}
