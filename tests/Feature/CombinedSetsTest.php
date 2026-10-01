<?php

use App\Enums\LoadUnit;
use App\Livewire\Sessions\Runner;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\User;
use App\Models\WorkoutSession;
use Livewire\Livewire;

function itemInProgress(): array
{
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->ownedBy($user)->create();
    $item = SessionItem::factory()->forSession($session)->create([
        'exercise_id' => Exercise::factory()->create(['unit_default' => LoadUnit::Kilograms])->id,
        'position' => 1,
    ]);

    return [$user, $session, $item];
}

it('adds a segment to a set and keeps it as one set', function () {
    [$user, $session, $item] = itemInProgress();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);

    $component->call('addSet', $item->id);
    $first = $item->sets()->sole();

    $component->set("setDrafts.{$first->id}.load", '40')
        ->set("setDrafts.{$first->id}.reps", '4');

    $component->call('addSegment', $first->id);

    $segments = $item->sets()->reorder('part')->get();

    expect($segments)->toHaveCount(2)
        ->and($segments->pluck('set_number')->all())->toBe([1, 1])
        ->and($segments->pluck('part')->all())->toBe([0, 1])
        ->and($segments->first()->load)->toBe(40.0);

    $second = $segments->last();

    $component->set("setDrafts.{$second->id}.load", '35')
        ->set("setDrafts.{$second->id}.reps", '4');

    expect($second->fresh()->load)->toBe(35.0)
        ->and($second->fresh()->reps)->toBe(4)
        ->and($second->fresh()->set_number)->toBe(1)
        ->and($item->fresh()->setsCount())->toBe(1);
});

it('starts the new segment with the unit of the exercise', function () {
    [$user, $session, $item] = itemInProgress();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $component->call('addSegment', $item->sets()->sole()->id);

    expect($item->sets()->reorder('part')->get()->last()->unit)->toBe(LoadUnit::Kilograms);
});

it('removes a segment from a set with several segments', function () {
    [$user, $session, $item] = itemInProgress();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $first = $item->sets()->sole();

    $component->call('addSegment', $first->id);
    $component->call('addSegment', $first->id);

    $last = $item->sets()->reorder('part', 'desc')->first();

    $component->call('removeSegment', $last->id);

    expect($item->sets()->reorder('part')->get()->pluck('part')->all())->toBe([0, 1])
        ->and($last->fresh()->trashed())->toBeTrue();
});

it('removes every segment when the whole set is removed', function () {
    [$user, $session, $item] = itemInProgress();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $first = $item->sets()->sole();

    $component->call('addSegment', $first->id);

    $component->call('removeSet', $first->id);

    expect($item->sets()->count())->toBe(0);
});

it('counts combined sets as single sets and plain sets as before', function () {
    [$user, $session, $item] = itemInProgress();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);

    $component->call('addSet', $item->id);
    $component->call('addSegment', $item->sets()->sole()->id);

    expect($item->fresh()->setsCount())->toBe(1);

    $component->call('addSet', $item->id);

    expect($item->fresh()->setsCount())->toBe(2);
});

it('keeps a single segment set working exactly as before', function () {
    [$user, $session, $item] = itemInProgress();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.load", '80')
        ->set("setDrafts.{$set->id}.reps", '6')
        ->set("setDrafts.{$set->id}.is_warmup", true);

    expect($set->fresh()->part)->toBe(0)
        ->and($set->fresh()->load)->toBe(80.0)
        ->and($set->fresh()->reps)->toBe(6)
        ->and($set->fresh()->is_warmup)->toBeTrue()
        ->and($item->fresh()->setsCount())->toBe(1);
});
