<?php

use App\Enums\LoadUnit;
use App\Livewire\Sessions\Runner;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
use Livewire\Livewire;

function exercisePerformance(User $user, Exercise $exercise, array $sessionAttributes, array $sets): WorkoutSession
{
    $session = WorkoutSession::factory()->ownedBy($user)->create($sessionAttributes);
    $item = SessionItem::factory()->forSession($session)->create([
        'exercise_id' => $exercise->id,
        'position' => 1,
    ]);

    foreach ($sets as $index => $set) {
        SessionSet::factory()->forItem($item)->create([
            'set_number' => $index + 1,
            'load' => $set[0],
            'unit' => $set[1],
            'reps' => $set[2],
            'is_warmup' => $set[3] ?? false,
        ]);
    }

    return $session;
}

function runningExercise(): array
{
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create([
        'name' => 'Supino máquina',
        'unit_default' => LoadUnit::Plates,
    ]);

    $session = WorkoutSession::factory()->ownedBy($user)->create(['performed_on' => '2026-10-01']);
    $item = SessionItem::factory()->forSession($session)->create([
        'exercise_id' => $exercise->id,
        'position' => 1,
    ]);

    return [$user, $session, $item, $exercise];
}

it('shows the last performance of an exercise', function () {
    [$user, $session, $item, $exercise] = runningExercise();

    exercisePerformance($user, $exercise, ['performed_on' => '2026-09-24', 'finished_at' => '2026-09-24 19:00:00'], [
        [14, 'plate', 12],
        [14, 'plate', 7],
    ]);

    $reference = $item->lastPerformance();

    expect($reference)->toHaveCount(2)
        ->and($reference->first()->load)->toBe(14.0)
        ->and($reference->first()->reps)->toBe(12)
        ->and($reference->last()->reps)->toBe(7);

    Livewire::actingAs($user)
        ->test(Runner::class, ['session' => $session])
        ->assertSee('Última vez');
});

it('finds the reference by exercise, not by the workout it was done in', function () {
    [$user, $session, $item, $exercise] = runningExercise();

    exercisePerformance($user, $exercise, ['performed_on' => '2026-09-20'], [[10, 'plate', 15]]);
    exercisePerformance($user, $exercise, ['performed_on' => '2026-09-27'], [[12, 'plate', 10]]);

    expect($item->lastPerformance()->first()->load)->toBe(12.0);
});

it('ignores the workout that is still in progress', function () {
    [$user, $session, $item, $exercise] = runningExercise();

    exercisePerformance($user, $exercise, ['performed_on' => '2026-09-27'], [[12, 'plate', 10]]);

    SessionSet::factory()->forItem($item)->create(['set_number' => 1, 'load' => 99, 'unit' => 'plate', 'reps' => 1]);

    expect($item->lastPerformance()->first()->load)->toBe(12.0);
});

it('leaves warmup sets out of the reference', function () {
    [$user, $session, $item, $exercise] = runningExercise();

    exercisePerformance($user, $exercise, ['performed_on' => '2026-09-27'], [
        [4, 'plate', 20, true],
        [14, 'plate', 8],
    ]);

    $reference = $item->lastPerformance();

    expect($reference)->toHaveCount(1)
        ->and($reference->first()->load)->toBe(14.0);
});

it('has no reference for an exercise never performed before', function () {
    [$user, $session, $item] = runningExercise();

    expect($item->lastPerformance())->toBeEmpty();
});

it('never uses another user history as reference', function () {
    [$user, $session, $item, $exercise] = runningExercise();

    exercisePerformance(User::factory()->create(), $exercise, ['performed_on' => '2026-09-27'], [[12, 'plate', 10]]);

    expect($item->lastPerformance())->toBeEmpty();
});

it('tells whether the performance is above, equal or below the last time', function () {
    [$user, $session, $item, $exercise] = runningExercise();

    exercisePerformance($user, $exercise, ['performed_on' => '2026-09-27'], [[14, 'plate', 8]]);

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);

    $component->call('addSet', $item->id);
    $set = $item->sets()->sole();

    expect($item->fresh()->trend())->toBeNull();

    $component->set("setDrafts.{$set->id}.load", '14')->set("setDrafts.{$set->id}.reps", '8');
    expect($item->fresh()->trend())->toBe(0);

    $component->set("setDrafts.{$set->id}.load", '14')->set("setDrafts.{$set->id}.reps", '9');
    expect($item->fresh()->trend())->toBe(1);

    $component->set("setDrafts.{$set->id}.load", '12')->set("setDrafts.{$set->id}.reps", '10');
    expect($item->fresh()->trend())->toBe(-1);
});

it('does not compare performances recorded in different units', function () {
    [$user, $session, $item, $exercise] = runningExercise();

    exercisePerformance($user, $exercise, ['performed_on' => '2026-09-27'], [[14, 'plate', 8]]);

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.load", '60')
        ->set("setDrafts.{$set->id}.unit", 'kg')
        ->set("setDrafts.{$set->id}.reps", '8');

    expect($item->fresh()->trend())->toBeNull();
});
