<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup
                            {--keep=14 : Quantos backups manter}
                            {--restore= : Restaura o backup informado por cima do banco atual}
                            {--list : Lista os backups guardados}
                            {--force : Restaura sem perguntar}';

    protected $description = 'Copia o banco do servidor e mantém os backups recentes';

    public function handle(): int
    {
        $backup = new DatabaseBackup(
            (string) config('database.connections.'.config('database.default').'.database'),
            (string) config('database.backups.path'),
        );

        if ($this->option('list')) {
            $files = $backup->all();

            if ($files->isEmpty()) {
                $this->components->warn('Nenhum backup guardado.');

                return self::SUCCESS;
            }

            $this->table(
                ['Backup', 'Tamanho', 'Quando'],
                $files->map(fn (\SplFileInfo $file): array => [
                    $file->getPathname(),
                    number_format($file->getSize() / 1024, 0).' KB',
                    date('d/m/Y H:i', $file->getMTime()),
                ])->all(),
            );

            return self::SUCCESS;
        }

        $restore = (string) $this->option('restore');

        if ($restore !== '') {
            return $this->restore($backup, $restore);
        }

        $path = $backup->create();
        $removed = $backup->prune((int) $this->option('keep'));

        $this->components->info('Backup em '.$path);

        if ($removed > 0) {
            $this->components->info("{$removed} backup(s) antigo(s) removido(s).");
        }

        return self::SUCCESS;
    }

    private function restore(DatabaseBackup $backup, string $file): int
    {
        if (! $this->option('force') && ! $this->confirm("Substituir o banco atual por {$file}?")) {
            $this->components->warn('Nada foi restaurado.');

            return self::FAILURE;
        }

        $backup->restore($file);

        $this->components->info('Banco restaurado de '.$file);

        return self::SUCCESS;
    }
}
