<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\NotionWorkoutImporter;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->file = sys_get_temp_dir().'/diario-notion-'.uniqid().'.md';
    $this->importer = new NotionWorkoutImporter($this->user);
});

afterEach(function () {
    @unlink($this->file);
});

function writeNotes(string $path, string $contents): void
{
    file_put_contents($path, $contents);
}

it('turns free text notes into structured workouts', function () {
    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-29 — Upper 1 — Sky

    ### Supino máquina — Peito
    14 placas — 12
    14 placas — 7
    obs: aguentava mais

    ### Remada curvada — Costas
    65 kg — 8
    65 kg — 8
    obs: leve roubo na última
    NOTES);

    $report = $this->importer->import($this->file);

    expect($report->created)->toHaveCount(1)
        ->and($report->problems)->toBeEmpty();

    $session = WorkoutSession::query()->sole();

    expect($session->name)->toBe('Upper 1')
        ->and($session->location)->toBe('Sky')
        ->and($session->performed_on->toDateString())->toBe('2026-09-29')
        ->and($session->finished_at)->not->toBeNull()
        ->and($session->items)->toHaveCount(2);

    $bench = $session->items->firstWhere('exercise.name', 'Supino máquina');

    expect($bench->notes)->toBe('aguentava mais')
        ->and($bench->sets)->toHaveCount(2)
        ->and($bench->sets->first()->load)->toBe(14.0)
        ->and($bench->sets->first()->unit->value)->toBe('plate')
        ->and($bench->sets->first()->reps)->toBe(12)
        ->and($bench->sets->last()->reps)->toBe(7);

    $row = $session->items->firstWhere('exercise.name', 'Remada curvada');

    expect($row->sets->first()->load)->toBe(65.0)
        ->and($row->sets->first()->unit->value)->toBe('kg')
        ->and($row->notes)->toBe('leve roubo na última');
});

it('handles the hard formats: combined sets, bodyweight and sets without reps', function () {
    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-20 — Costas — Sky

    ### Puxada frente — Costas
    40 kg — 4 + 35 kg — 4
    14 placas — 12
    60 kg —
    — 10
    NOTES);

    $report = $this->importer->import($this->file);

    expect($report->problems)->toBeEmpty();

    $sets = WorkoutSession::query()->sole()->items->first()->sets;

    expect($sets)->toHaveCount(5)
        ->and($sets[0]->part)->toBe(0)
        ->and($sets[0]->load)->toBe(40.0)
        ->and($sets[0]->reps)->toBe(4)
        ->and($sets[1]->part)->toBe(1)
        ->and($sets[1]->load)->toBe(35.0)
        ->and($sets[1]->reps)->toBe(4)
        ->and($sets[1]->set_number)->toBe($sets[0]->set_number)
        ->and($sets[2]->load)->toBe(14.0)
        ->and($sets[2]->unit->value)->toBe('plate')
        ->and($sets[3]->load)->toBe(60.0)
        ->and($sets[3]->reps)->toBeNull();

    $bodyweight = $sets[4];

    expect($bodyweight->load)->toBeNull()
        ->and($bodyweight->reps)->toBe(10)
        ->and($bodyweight->unit->value)->toBe('bodyweight')
        ->and($sets->first()->item->setsCount())->toBe(4);
});

it('matches an exercise that already exists in the catalog', function () {
    $catalog = Exercise::factory()->create(['name' => 'Supino máquina', 'muscle_group' => 'Peito']);

    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-29 — Upper 1

    ### Supino máquina
    14 placas — 12
    NOTES);

    $this->importer->import($this->file);

    expect(Exercise::query()->count())->toBe(1)
        ->and(WorkoutSession::query()->sole()->items->first()->exercise_id)->toBe($catalog->id);
});

it('creates an exercise that is not in the catalog yet', function () {
    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-29 — Upper 1

    ### Remada cavalinho — Costas
    14 placas — 10
    NOTES);

    $this->importer->import($this->file);

    $created = Exercise::query()->where('name', 'Remada cavalinho')->sole();

    expect($created->user_id)->toBe($this->user->id)
        ->and($created->muscle_group)->toBe('Costas');
});

it('reports what it cannot read instead of guessing', function () {
    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-29 — Upper 1

    ### Exercício misterioso
    umas três séries de leve

    ### Supino máquina — Peito
    14 placas — 12
    NOTES);

    $report = $this->importer->import($this->file);

    expect($report->problems)->toHaveCount(1)
        ->and($report->problems[0])->toContain('Exercício misterioso')
        ->and(Exercise::query()->where('name', 'Exercício misterioso')->exists())->toBeFalse()
        ->and(WorkoutSession::query()->sole()->items)->toHaveCount(1);
});

it('runs again without duplicating what was already imported', function () {
    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-29 — Upper 1 — Sky

    ### Supino máquina — Peito
    14 placas — 12
    NOTES);

    $this->importer->import($this->file);
    $second = $this->importer->import($this->file);

    expect(WorkoutSession::query()->count())->toBe(1)
        ->and($second->created)->toBeEmpty()
        ->and($second->skipped)->toHaveCount(1)
        ->and(WorkoutSession::query()->sole()->items->first()->sets)->toHaveCount(1);
});

it('imports through the command', function () {
    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-29 — Upper 1

    ### Supino máquina — Peito
    14 placas — 12
    NOTES);

    $this->artisan('app:import-notion', ['file' => $this->file, '--user' => $this->user->email])
        ->assertSuccessful();

    expect(WorkoutSession::query()->count())->toBe(1);
});

it('shows what it imported in the history and in the exercise progression', function () {
    writeNotes($this->file, <<<'NOTES'
    ## 2026-09-29 — Upper 1 — Sky

    ### Supino máquina — Peito
    14 placas — 12
    NOTES);

    $this->importer->import($this->file);

    $session = WorkoutSession::query()->sole();

    expect($session->items->first()->sets)->toHaveCount(1)
        ->and($session->items->first()->setsCount())->toBe(1)
        ->and($session->volumeInKg())->toBe(0.0);
});
