<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'app:user
                            {email : E-mail do usuário}
                            {--name= : Nome exibido}
                            {--password= : Senha, pedida se ausente}';

    protected $description = 'Cria um usuário do diário de treino';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = (string) ($this->option('name') ?: Str::before($email, '@'));
        $password = (string) ($this->option('password') ?: $this->secret('Senha'));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'string', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->components->info("Usuário {$user->email} criado.");

        return self::SUCCESS;
    }
}
