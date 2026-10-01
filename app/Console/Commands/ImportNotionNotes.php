<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\NotionWorkoutImporter;
use Illuminate\Console\Command;

class ImportNotionNotes extends Command
{
    protected $signature = 'app:import-notion
                            {file : Arquivo com as anotações}
                            {--user= : E-mail de quem vai receber os treinos}';

    protected $description = 'Importa os treinos das anotações antigas para o diário';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("Arquivo não encontrado: {$file}");

            return self::FAILURE;
        }

        $email = (string) ($this->option('user') ?: '');
        $user = $email === ''
            ? User::query()->oldest('id')->first()
            : User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error('Não achei o usuário de destino.');

            return self::FAILURE;
        }

        $report = (new NotionWorkoutImporter($user))->import($file);

        $this->components->info(count($report->created).' treino(s) importado(s) para '.$user->email);

        foreach ($report->skipped as $line) {
            $this->components->warn('Pulado: '.$line);
        }

        foreach ($report->problems as $line) {
            $this->components->error('Não entendi: '.$line);
        }

        return self::SUCCESS;
    }
}
