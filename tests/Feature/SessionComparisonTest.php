<?php

use App\Enums\LoadUnit;
use App\Livewire\History\Index as History;
use App\Livewire\History\Show as SessionDetail;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Livewire\Livewire;

function recordedSession(User $user, array $attributes = [], array $exercises = []): WorkoutSession
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

it('finds the previous workout of the same template', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();

    $older = recordedSession($user, ['performed_on' => '2026-09-22', 'workout_template_id' => $template->id]);
    $newer = recordedSession($user, ['performed_on' => '2026-09-29', 'workout_template_id' => $template->id]);

    expect($newer->previousForSameTemplate()->id)->toBe($older->id)
        ->and($older->previousForSameTemplate())->toBeNull();
});

it('has no comparison when there is no previous workout', function () {
    $user = User::factory()->create();
    $session = recordedSession($user);

    $component = Livewire::actingAs($user)->test(SessionDetail::class, ['session' => $session]);

    expect($component->viewData('comparison'))->toBeEmpty()
        ->and($component->viewData('comparingWith'))->toBeNull();

    $component->assertSee('Sem treino anterior');
});

it('writes the difference in load and reps for each exercise', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $bench = Exercise::factory()->create(['name' => 'Supino máquina', 'unit_default' => LoadUnit::Plates]);

    $previous = recordedSession($user, ['performed_on' => '2026-09-22', 'workout_template_id' => $template->id], [
        ['id' => $bench->id, 'sets' => [[14, 'plate', 10]]],
    ]);

    $current = recordedSession($user, ['performed_on' => '2026-09-29', 'workout_template_id' => $template->id], [
        ['id' => $bench->id, 'sets' => [[16, 'plate', 8]]],
    ]);

    $row = Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $current])
        ->viewData('comparison')
        ->sole();

    expect($row['previous']->id)->toBe($previous->items()->sole()->id)
        ->and($row['delta']['load'])->toBe(2.0)
        ->and($row['delta']['reps'])->toBe(-2);

    Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $current])
        ->assertSee('+2')
        ->assertSee('-2');
});

it('leaves warmup sets out of the comparison', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $bench = Exercise::factory()->create(['unit_default' => LoadUnit::Plates]);

    recordedSession($user, ['performed_on' => '2026-09-22', 'workout_template_id' => $template->id], [
        ['id' => $bench->id, 'sets' => [[4, 'plate', 20, true], [14, 'plate', 10]]],
    ]);

    $current = recordedSession($user, ['performed_on' => '2026-09-29', 'workout_template_id' => $template->id], [
        ['id' => $bench->id, 'sets' => [[4, 'plate', 20, true], [14, 'plate', 10]]],
    ]);

    $row = Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $current])
        ->viewData('comparison')
        ->sole();

    expect($row['previousSets'])->toHaveCount(1)
        ->and($row['currentSets'])->toHaveCount(1)
        ->and($row['delta']['load'])->toBe(0.0)
        ->and($row['delta']['reps'])->toBe(0);
});

it('compares against a session chosen by hand', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $bench = Exercise::factory()->create(['unit_default' => LoadUnit::Plates]);

    recordedSession($user, ['performed_on' => '2026-09-22', 'workout_template_id' => $template->id], [
        ['id' => $bench->id, 'sets' => [[14, 'plate', 10]]],
    ]);
    $chosen = recordedSession($user, ['name' => 'Escolhido', 'performed_on' => '2026-09-15'], [
        ['id' => $bench->id, 'sets' => [[10, 'plate', 12]]],
    ]);
    $current = recordedSession($user, ['performed_on' => '2026-09-29', 'workout_template_id' => $template->id], [
        ['id' => $bench->id, 'sets' => [[16, 'plate', 8]]],
    ]);

    $component = Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $current])
        ->set('compareWithId', $chosen->id);

    expect($component->viewData('comparingWith')->id)->toBe($chosen->id)
        ->and($component->viewData('comparison')->sole()['delta']['load'])->toBe(6.0);
});

it('does not compare performances recorded in different units', function () {
    $user = User::factory()->create();
    $bench = Exercise::factory()->create();

    $previous = recordedSession($user, ['performed_on' => '2026-09-22'], [['id' => $bench->id, 'sets' => [[60, 'kg', 10]]]]);
    $current = recordedSession($user, ['performed_on' => '2026-09-29'], [['id' => $bench->id, 'sets' => [[14, 'plate', 10]]]]);

    $row = Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $current])
        ->set('compareWithId', $previous->id)
        ->viewData('comparison')
        ->sole();

    expect($row['delta'])->toBeNull();
});

it('filters the history by template, exercise, muscle group and location', function () {
    $user = User::factory()->create();
    $upper = WorkoutTemplate::factory()->ownedBy($user)->create(['name' => 'Upper 1']);
    $lower = WorkoutTemplate::factory()->ownedBy($user)->create(['name' => 'Lower 1']);
    $bench = Exercise::factory()->create(['name' => 'Supino máquina', 'muscle_group' => 'Peito']);
    $squat = Exercise::factory()->create(['name' => 'Agachamento livre', 'muscle_group' => 'Quadríceps']);

    recordedSession($user, ['name' => 'Treino A', 'performed_on' => '2026-09-29', 'workout_template_id' => $upper->id, 'location' => 'Sky'], [
        ['id' => $bench->id, 'sets' => [[14, 'plate', 10]]],
    ]);
    recordedSession($user, ['name' => 'Treino B', 'performed_on' => '2026-09-28', 'workout_template_id' => $lower->id, 'location' => 'Casa'], [
        ['id' => $squat->id, 'sets' => [[80, 'kg', 8]]],
    ]);

    $names = fn ($component) => collect($component->viewData('weeks'))
        ->flatMap(fn (array $week) => $week['sessions']->pluck('name'))
        ->all();

    $component = Livewire::actingAs($user)->test(History::class);

    expect($names($component))->toBe(['Treino A', 'Treino B']);

    expect($names($component->set('templateId', $upper->id)))->toBe(['Treino A']);
    expect($names($component->set('templateId', '')->set('exerciseId', $squat->id)))->toBe(['Treino B']);
    expect($names($component->set('exerciseId', '')->set('muscleGroup', 'Peito')))->toBe(['Treino A']);
    expect($names($component->set('muscleGroup', '')->set('location', 'Casa')))->toBe(['Treino B']);
    expect($names($component->set('location', '')))->toBe(['Treino A', 'Treino B']);
});

it('does not let another user compare against his workouts', function () {
    $user = User::factory()->create();
    $bench = Exercise::factory()->create();
    $current = recordedSession($user, ['performed_on' => '2026-09-29'], [['id' => $bench->id, 'sets' => [[14, 'plate', 10]]]]);
    $foreign = recordedSession(User::factory()->create(), ['performed_on' => '2026-09-22'], [
        ['id' => $bench->id, 'sets' => [[99, 'plate', 1]]],
    ]);

    $component = Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $current])
        ->set('compareWithId', $foreign->id);

    expect($component->viewData('comparingWith'))->toBeNull();
});
