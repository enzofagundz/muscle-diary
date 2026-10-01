<?php

use App\Support\DatabaseBackup;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->workspace = sys_get_temp_dir().'/diario-backup-'.uniqid();
    File::makeDirectory($this->workspace, recursive: true);

    $this->database = $this->workspace.'/database.sqlite';
    $this->backups = $this->workspace.'/backups';

    $this->backup = new DatabaseBackup($this->database, $this->backups);
});

afterEach(function () {
    File::deleteDirectory($this->workspace);
});

it('writes a copy of the database that can be opened on its own', function () {
    $connection = new PDO('sqlite:'.$this->database);
    $connection->exec('create table workouts (name text)');
    $connection->exec("insert into workouts values ('Upper 1')");
    $connection->exec("insert into workouts values ('Lower 1')");
    unset($connection);

    $path = $this->backup->create();

    expect(File::exists($path))->toBeTrue()
        ->and($path)->toStartWith($this->backups);

    $restored = new PDO('sqlite:'.$path);

    expect($restored->query('select count(*) from workouts')->fetchColumn())->toBe(2)
        ->and($restored->query('select name from workouts order by name')->fetchAll(PDO::FETCH_COLUMN))
        ->toBe(['Lower 1', 'Upper 1']);
});

it('keeps only the newest backups', function () {
    File::makeDirectory($this->backups, recursive: true);
    File::put($this->database, 'x');

    foreach (range(1, 5) as $index) {
        $path = $this->backups.'/2026-09-0'.$index.'-120000.sqlite';
        File::put($path, 'old');
        touch($path, now()->subDays(10 - $index)->timestamp);
    }

    $fresh = $this->backup->create();
    $this->backup->prune(keep: 3);

    expect(File::files($this->backups))->toHaveCount(3)
        ->and(File::exists($fresh))->toBeTrue()
        ->and($this->backup->all()->first()->getFilename())->toBe(basename($fresh));
});

it('restores a backup over the live database', function () {
    File::makeDirectory($this->backups, recursive: true);

    $source = new PDO('sqlite:'.$this->database);
    $source->exec('create table workouts (name text)');
    $source->exec("insert into workouts values ('Treino antigo')");
    unset($source);

    $path = $this->backup->create();

    $live = new PDO('sqlite:'.$this->database);
    $live->exec('delete from workouts');
    $live->exec("insert into workouts values ('Treino novo')");
    unset($live);

    $this->backup->restore($path);

    $after = new PDO('sqlite:'.$this->database);

    expect($after->query('select name from workouts')->fetchAll(PDO::FETCH_COLUMN))
        ->toBe(['Treino antigo']);
});

it('refuses to back up a database that is not a file', function () {
    $backup = new DatabaseBackup(':memory:', $this->backups);

    expect(fn () => $backup->create())->toThrow(RuntimeException::class);
});

it('backs up through the command and schedules it', function () {
    File::put($this->database, 'conteudo');

    config([
        'database.connections.sqlite.database' => $this->database,
        'database.backups.path' => $this->backups,
    ]);

    $this->artisan('app:backup')->assertSuccessful();

    expect(File::files($this->backups))->toHaveCount(1);

    $this->artisan('app:backup', ['--keep' => 1])->assertSuccessful();

    expect(File::files($this->backups))->toHaveCount(1);

    $this->artisan('app:backup', ['--list' => true])
        ->expectsOutputToContain('2026-')
        ->assertSuccessful();
});
