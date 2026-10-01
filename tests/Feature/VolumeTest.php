<?php

use App\Enums\LoadUnit;
use App\Livewire\Exercises as ExerciseCatalog;
use App\Livewire\Exercises\Show as ExerciseProgress;
use App\Livewire\History\Index as History;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
use Livewire\Livewire;

function volumeSession(User $user, array $attributes, array $exercises): WorkoutSession
{
    $session = WorkoutSession::factory()->ownedBy($user)->create(array_merge([
        'finished_at' => '2026-09-29 19:00:00',
    ], $attributes));

    foreach ($exercises as $position => $exercise) {
        $item = SessionItem::factory()->forSession($session)->create([
            'exercise_id' => $exercise['id'],
            'position' => $position + 1,
        ]);

        foreach ($exercise['sets'] as $index => $set) {
            SessionSet::factory()->forItem($item)->create([
                'set_number' => $index + 1,
                'load' => $set[0],
                'unit' => $set[1],
                'reps' => $set[2],
                'is_warmup' => $set[3] ?? false,
            ]);
        }
    }

    return $session;
}

it('sums the volume of a workout from load times reps', function () {
    $user = User::factory()->create();
    $bench = Exercise::factory()->create(['unit_default' => LoadUnit::Kilograms]);

    $session = volumeSession($user, ['performed_on' => '2026-09-29'], [
        ['id' => $bench->id, 'sets' => [[60, 'kg', 10], [60, 'kg', 8], [40, 'kg', 20, true]]],
    ]);

    expect($session->volumeInKg())->toBe(1080.0);
});

it('converts plates to kilograms when the exercise says how much a plate weighs', function () {
    $user = User::factory()->create();
    $machine = Exercise::factory()->create(['unit_default' => LoadUnit::Plates, 'kg_per_plate' => 5]);

    $session = volumeSession($user, ['performed_on' => '2026-09-29'], [
        ['id' => $machine->id, 'sets' => [[14, 'plate', 10]]],
    ]);

    expect($session->volumeInKg())->toBe(700.0);
});

it('reports plate volume in plates and keeps it out of the kilogram total', function () {
    $user = User::factory()->create();
    $machine = Exercise::factory()->create(['unit_default' => LoadUnit::Plates]);
    $bench = Exercise::factory()->create(['unit_default' => LoadUnit::Kilograms]);

    $session = volumeSession($user, ['performed_on' => '2026-09-29'], [
        ['id' => $machine->id, 'sets' => [[14, 'plate', 10]]],
        ['id' => $bench->id, 'sets' => [[60, 'kg', 10]]],
    ]);

    expect($session->volumeInKg())->toBe(600.0)
        ->and($session->items()->get()->first()->volume())->toBe(['value' => 140.0, 'unit' => 'placas']);
});

it('leaves warmup sets out of every volume', function () {
    $user = User::factory()->create();
    $bench = Exercise::factory()->create(['unit_default' => LoadUnit::Kilograms]);

    $session = volumeSession($user, ['performed_on' => '2026-09-29'], [
        ['id' => $bench->id, 'sets' => [[100, 'kg', 10, true], [60, 'kg', 10]]],
    ]);

    expect($session->volumeInKg())->toBe(600.0);
});

it('adds up the volume of the week in the history', function () {
    $user = User::factory()->create();
    $bench = Exercise::factory()->create(['unit_default' => LoadUnit::Kilograms]);

    $this->travelTo('2026-10-01 12:00:00');

    volumeSession($user, ['performed_on' => '2026-09-29'], [['id' => $bench->id, 'sets' => [[60, 'kg', 10]]]]);
    volumeSession($user, ['performed_on' => '2026-09-30'], [['id' => $bench->id, 'sets' => [[50, 'kg', 10]]]]);
    volumeSession($user, ['performed_on' => '2026-09-22'], [['id' => $bench->id, 'sets' => [[40, 'kg', 10]]]]);

    $weeks = Livewire::actingAs($user)->test(History::class)->viewData('weeks');

    expect($weeks[0]['volume'])->toBe(1100.0)
        ->and($weeks[1]['volume'])->toBe(400.0);
});

it('shows how each muscle group is going', function () {
    $user = User::factory()->create();
    $bench = Exercise::factory()->create(['muscle_group' => 'Peito', 'unit_default' => LoadUnit::Kilograms]);
    $squat = Exercise::factory()->create(['muscle_group' => 'Quadríceps', 'unit_default' => LoadUnit::Kilograms]);

    $this->travelTo('2026-10-01 12:00:00');

    volumeSession($user, ['performed_on' => '2026-09-29'], [['id' => $bench->id, 'sets' => [[60, 'kg', 10]]]]);
    volumeSession($user, ['performed_on' => '2026-09-10'], [['id' => $squat->id, 'sets' => [[80, 'kg', 5]]]]);

    $groups = Livewire::actingAs($user)->test(History::class)->viewData('groups');

    expect($groups->firstWhere('name', 'Peito')['volumeInKg'])->toBe(600.0)
        ->and($groups->firstWhere('name', 'Peito')['daysSince'])->toBe(2)
        ->and($groups->firstWhere('name', 'Quadríceps')['daysSince'])->toBe(21);
});

it('draws the volume of each execution on the exercise page', function () {
    $user = User::factory()->create();
    $bench = Exercise::factory()->create(['unit_default' => LoadUnit::Kilograms]);

    volumeSession($user, ['performed_on' => '2026-09-15'], [['id' => $bench->id, 'sets' => [[60, 'kg', 10]]]]);
    volumeSession($user, ['performed_on' => '2026-09-22'], [['id' => $bench->id, 'sets' => [[60, 'kg', 12]]]]);

    $chart = Livewire::actingAs($user)->test(ExerciseProgress::class, ['exercise' => $bench])->viewData('volumeChart');

    expect($chart['points'])->toHaveCount(2)
        ->and($chart['points'][0]['volume'])->toBe(600.0)
        ->and($chart['points'][1]['volume'])->toBe(720.0)
        ->and($chart['unit'])->toBe('kg');
});

it('lets the catalog say how much a plate weighs', function () {
    $user = User::factory()->create();
    $machine = Exercise::factory()->ownedBy($user)->create(['unit_default' => LoadUnit::Plates]);

    Livewire::actingAs($user)
        ->test(ExerciseCatalog::class)
        ->call('edit', $machine->id)
        ->set('kgPerPlate', '4,5')
        ->call('save')
        ->assertHasNoErrors();

    expect($machine->fresh()->kg_per_plate)->toBe('4.50');
});
