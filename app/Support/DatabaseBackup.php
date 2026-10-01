<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseBackup
{
    public function __construct(
        private readonly string $databasePath,
        private readonly string $backupDirectory,
    ) {}

    /**
     * Copy the database into the backup directory and return the new file.
     * The copy is written aside and moved into place, so an interrupted run
     * never leaves a half-written backup looking usable.
     */
    public function create(): string
    {
        if ($this->databasePath === ':memory:' || ! File::exists($this->databasePath)) {
            throw new RuntimeException("Não há banco em arquivo para copiar: {$this->databasePath}");
        }

        File::ensureDirectoryExists($this->backupDirectory);

        $target = $this->backupDirectory.'/'.now()->format('Y-m-d-His').'.sqlite';
        $partial = $target.'.partial';

        File::copy($this->databasePath, $partial);
        File::move($partial, $target);

        return $target;
    }

    /**
     * @return Collection<int, \SplFileInfo>
     */
    public function all(): Collection
    {
        if (! File::isDirectory($this->backupDirectory)) {
            return collect();
        }

        return collect(File::files($this->backupDirectory))
            ->filter(fn (\SplFileInfo $file): bool => $file->getExtension() === 'sqlite')
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
            ->values();
    }

    public function prune(int $keep): int
    {
        $doomed = $this->all()->slice($keep);

        $doomed->each(fn (\SplFileInfo $file) => File::delete($file->getPathname()));

        return $doomed->count();
    }

    public function restore(string $file): void
    {
        if (! File::exists($file)) {
            throw new RuntimeException("Backup não encontrado: {$file}");
        }

        $partial = $this->databasePath.'.restoring';

        File::copy($file, $partial);
        File::move($partial, $this->databasePath);
    }
}
