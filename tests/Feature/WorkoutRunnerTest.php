<?php

use App\Enums\LoadUnit;
use App\Livewire\Sessions\Runner;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
use Livewire\Livewire;

function runningSession(): array
{
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->ownedBy($user)->create(['rest_seconds' => 90]);
    $exercise = Exercise::factory()->create(['unit_default' => LoadUnit::Kilograms]);
    $item = SessionItem::factory()->forSession($session)->create([
        'exercise_id' => $exercise->id,
        'position' => 1,
        'planned_sets' => 3,
        'rep_min' => 8,
        'rep_max' => 12,
    ]);

    return [$user, $session, $item, $exercise];
}

it('records load and reps for a set, saving as it goes', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);

    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.load", '65')
        ->set("setDrafts.{$set->id}.reps", '8');

    expect($set->fresh()->load)->toBe(65.0)
        ->and($set->fresh()->reps)->toBe(8);
});

it('keeps what was recorded after the screen is loaded again', function () {
    [$user, $session, $item] = runningSession();

    Livewire::actingAs($user)->test(Runner::class, ['session' => $session])
        ->call('addSet', $item->id)
        ->set("setDrafts.{$item->sets()->sole()->id}.load", '62,5')
        ->set("setDrafts.{$item->sets()->sole()->id}.reps", '10');

    $set = $item->sets()->sole();

    expect($set->fresh()->load)->toBe(62.5)
        ->and($set->fresh()->reps)->toBe(10)
        ->and(Livewire::actingAs($user)->test(Runner::class, ['session' => $session])->get('setDrafts')[$set->id]['load'])
        ->toBe(62.5);
});

it('starts a set with the unit of the exercise and lets it change', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    expect($set->unit)->toBe(LoadUnit::Kilograms);

    $component->set("setDrafts.{$set->id}.unit", LoadUnit::Plates->value);

    expect($set->fresh()->unit)->toBe(LoadUnit::Plates);
});

it('accepts a set with no reps recorded', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.load", '80')
        ->set("setDrafts.{$set->id}.reps", '');

    expect($set->fresh()->reps)->toBeNull()
        ->and($set->fresh()->load)->toBe(80.0);
});

it('accepts a bodyweight set with no load at all', function () {
    [$user, $session, $item, $exercise] = runningSession();
    $exercise->update(['unit_default' => LoadUnit::Bodyweight]);

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.load", '')
        ->set("setDrafts.{$set->id}.reps", '12');

    expect($set->fresh()->load)->toBeNull()
        ->and($set->fresh()->reps)->toBe(12)
        ->and($set->fresh()->unit)->toBe(LoadUnit::Bodyweight);
});

it('refuses a load that is not a number', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.load", 'oitenta')->assertHasErrors("setDrafts.{$set->id}.load");

    expect($set->fresh()->load)->toBeNull();
});

it('adds sets beyond the planned ones and removes one recorded by mistake', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);

    $component->call('addSet', $item->id);
    $component->call('addSet', $item->id);
    $component->call('addSet', $item->id);

    expect($item->sets()->count())->toBe(3)
        ->and($item->sets()->reorder('set_number', 'desc')->first()->set_number)->toBe(3);

    $component->call('removeSet', $item->sets()->orderBy('set_number')->first()->id);

    expect($item->sets()->count())->toBe(2);
});

it('copies the values of the previous set', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);

    $component->call('addSet', $item->id);
    $first = $item->sets()->sole();

    $component->set("setDrafts.{$first->id}.load", '70')
        ->set("setDrafts.{$first->id}.reps", '9')
        ->set("setDrafts.{$first->id}.unit", LoadUnit::Plates->value);

    $component->call('addSet', $item->id);
    $second = $item->sets()->reorder('set_number', 'desc')->first();

    $component->call('copyPrevious', $second->id);

    expect($second->fresh()->load)->toBe(70.0)
        ->and($second->fresh()->reps)->toBe(9)
        ->and($second->fresh()->unit)->toBe(LoadUnit::Plates);
});

it('marks a set as warmup', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.is_warmup", true);

    expect($set->fresh()->is_warmup)->toBeTrue();
});

it('keeps notes on the set, on the exercise and on the session', function () {
    [$user, $session, $item] = runningSession();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);
    $component->call('addSet', $item->id);

    $set = $item->sets()->sole();

    $component->set("setDrafts.{$set->id}.notes", 'Leve roubo na última')
        ->set("itemNotes.{$item->id}", 'Sem strap')
        ->set('sessionNotes', 'Preguiça');

    expect($set->fresh()->notes)->toBe('Leve roubo na última')
        ->and($item->fresh()->notes)->toBe('Sem strap')
        ->and($session->fresh()->notes)->toBe('Preguiça');
});

it('adds an exercise in the middle of the workout and removes one', function () {
    [$user, $session, $item] = runningSession();
    $extra = Exercise::factory()->create();

    $component = Livewire::actingAs($user)->test(Runner::class, ['session' => $session]);

    $component->call('addItem')
        ->set('exerciseId', $extra->id)
        ->set('plannedSets', 4)
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($session->items()->count())->toBe(2)
        ->and($session->items()->reorder('position', 'desc')->first()->exercise_id)->toBe($extra->id);

    $component->call('removeItem', $item->id);

    expect($session->items()->count())->toBe(1);
});

it('refuses to remove a set that belongs to another session', function () {
    [$user, $session] = runningSession();

    $otherItem = SessionItem::factory()
        ->forSession(WorkoutSession::factory()->ownedBy($user)->create())
        ->create();
    $foreign = SessionSet::factory()->forItem($otherItem)->create();

    Livewire::actingAs($user)->test(Runner::class, ['session' => $session])->call('removeSet', $foreign->id);

    expect($foreign->fresh()->trashed())->toBeFalse();
});

it('offers a rest button with the rest set on the exercise', function () {
    [$user, $session, $item] = runningSession();

    $item->update(['rest_seconds' => 120]);
    SessionSet::factory()->forItem($item)->create();

    Livewire::actingAs($user)->test(Runner::class, ['session' => $session])
        ->assertSeeHtml('data-rest-seconds="120"')
        ->assertSeeHtml('data-rest-bar');
});

it('falls back to the rest of the session when the exercise does not set one', function () {
    [$user, $session, $item] = runningSession();

    $session->update(['rest_seconds' => 45]);
    $item->update(['rest_seconds' => null]);
    SessionSet::factory()->forItem($item)->create();

    Livewire::actingAs($user)->test(Runner::class, ['session' => $session])
        ->assertSeeHtml('data-rest-seconds="45"');
});

it('hides the rest button when the rest is zero', function () {
    [$user, $session, $item] = runningSession();

    $item->update(['rest_seconds' => 0]);
    SessionSet::factory()->forItem($item)->create();

    Livewire::actingAs($user)->test(Runner::class, ['session' => $session])
        ->assertDontSeeHtml('data-rest-seconds');
});

it('hides the rest button and the bar on a finished session', function () {
    [$user, $session, $item] = runningSession();

    $session->update(['finished_at' => now()]);
    SessionSet::factory()->forItem($item)->create();

    Livewire::actingAs($user)->test(Runner::class, ['session' => $session])
        ->assertDontSeeHtml('data-rest-seconds')
        ->assertDontSeeHtml('data-rest-bar');
});

it('renders the rest controls in the bar', function () {
    [$user, $session, $item] = runningSession();

    SessionSet::factory()->forItem($item)->create();

    Livewire::actingAs($user)->test(Runner::class, ['session' => $session])
        ->assertSeeHtml('data-rest-control="pause"')
        ->assertSeeHtml('data-rest-control="minus"')
        ->assertSeeHtml('data-rest-control="plus"')
        ->assertSeeHtml('data-rest-control="close"');
});
