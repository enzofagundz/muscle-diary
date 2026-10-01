<?php

use App\Livewire\History\Index as History;
use App\Livewire\History\Show as SessionDetail;
use App\Livewire\Sessions\Runner;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
use Livewire\Livewire;

function historySession(User $user, array $attributes = []): WorkoutSession
{
    return WorkoutSession::factory()->ownedBy($user)->create($attributes);
}

it('finishes a workout and shows how long it took', function () {
    $user = User::factory()->create();
    $session = historySession($user, [
        'performed_on' => '2026-09-29',
        'created_at' => '2026-09-29 18:00:00',
    ]);

    $this->travelTo('2026-09-29 19:12:00');

    Livewire::actingAs($user)
        ->test(Runner::class, ['session' => $session])
        ->call('finish')
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->finished_at)->not->toBeNull()
        ->and($session->durationInMinutes())->toBe(72);
});

it('discards a workout that was never finished', function () {
    $user = User::factory()->create();
    $session = historySession($user);
    $item = SessionItem::factory()->forSession($session)->create();
    SessionSet::factory()->forItem($item)->create();

    Livewire::actingAs($user)
        ->test(Runner::class, ['session' => $session])
        ->call('discard');

    expect($session->fresh()->trashed())->toBeTrue()
        ->and($item->fresh()->trashed())->toBeTrue()
        ->and(SessionSet::withTrashed()->where('session_item_id', $item->id)->first()->trashed())->toBeTrue();
});

it('groups the history by week with a count of workouts', function () {
    $user = User::factory()->create();

    $this->travelTo('2026-10-01 12:00:00');

    historySession($user, ['name' => 'Upper 1', 'performed_on' => '2026-09-29', 'finished_at' => '2026-09-29 19:00:00']);
    historySession($user, ['name' => 'Lower 1', 'performed_on' => '2026-09-30', 'finished_at' => '2026-09-30 19:00:00']);
    historySession($user, ['name' => 'Upper 2', 'performed_on' => '2026-09-22', 'finished_at' => '2026-09-22 19:00:00']);

    $component = Livewire::actingAs($user)->test(History::class);

    $weeks = $component->viewData('weeks');

    expect($weeks)->toHaveCount(2)
        ->and($weeks[0]['sessions'])->toHaveCount(2)
        ->and($weeks[0]['sessions']->pluck('name')->all())->toBe(['Lower 1', 'Upper 1'])
        ->and($weeks[1]['sessions'])->toHaveCount(1)
        ->and($weeks[1]['sessions']->first()->name)->toBe('Upper 2');
});

it('shows weeks without any workout', function () {
    $user = User::factory()->create();

    $this->travelTo('2026-10-01 12:00:00');

    historySession($user, ['performed_on' => '2026-09-15', 'finished_at' => '2026-09-15 19:00:00']);

    $weeks = Livewire::actingAs($user)->test(History::class)->viewData('weeks');

    expect($weeks)->toHaveCount(3)
        ->and($weeks[0]['sessions'])->toHaveCount(0)
        ->and($weeks[1]['sessions'])->toHaveCount(0)
        ->and($weeks[2]['sessions'])->toHaveCount(1);
});

it('keeps unfinished workouts apart from the history', function () {
    $user = User::factory()->create();

    $this->travelTo('2026-10-01 12:00:00');

    historySession($user, ['name' => 'Terminado', 'performed_on' => '2026-09-29', 'finished_at' => '2026-09-29 19:00:00']);
    historySession($user, ['name' => 'Pela metade', 'performed_on' => '2026-09-29']);

    $component = Livewire::actingAs($user)->test(History::class);

    expect($component->viewData('unfinished'))->toHaveCount(1)
        ->and($component->viewData('weeks')[0]['sessions'])->toHaveCount(1);

    $component->assertSee('Pela metade')->assertSee('Retomar');
});

it('shows every set of a recorded workout with unit and notes', function () {
    $user = User::factory()->create();
    $session = historySession($user, ['name' => 'Upper 1', 'performed_on' => '2026-09-29', 'finished_at' => '2026-09-29 19:00:00']);
    $item = SessionItem::factory()->forSession($session)->create([
        'exercise_id' => Exercise::factory()->create(['name' => 'Supino máquina'])->id,
        'notes' => 'Ombro fadigado',
    ]);

    SessionSet::factory()->forItem($item)->create(['set_number' => 1, 'load' => 60, 'unit' => 'plate', 'reps' => 12, 'notes' => 'Aguentava mais']);
    SessionSet::factory()->forItem($item)->create(['set_number' => 2, 'load' => 60, 'unit' => 'plate', 'reps' => null]);

    Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $session])
        ->assertSee('Supino máquina')
        ->assertSee('Aguentava mais')
        ->assertSee('Ombro fadigado')
        ->assertSee('60')
        ->assertSee('12');
});

it('corrects a recorded workout', function () {
    $user = User::factory()->create();
    $session = historySession($user, ['name' => 'Upper 1', 'performed_on' => '2026-09-29', 'location' => 'Sky']);

    Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $session])
        ->call('edit')
        ->set('name', 'Upper A')
        ->set('performedOn', '2026-09-28')
        ->set('location', 'Casa')
        ->set('notes', 'Preguiça')
        ->call('save')
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->name)->toBe('Upper A')
        ->and($session->performed_on->toDateString())->toBe('2026-09-28')
        ->and($session->location)->toBe('Casa')
        ->and($session->notes)->toBe('Preguiça');
});

it('deletes a recorded workout', function () {
    $user = User::factory()->create();
    $session = historySession($user, ['finished_at' => '2026-09-29 19:00:00']);
    $item = SessionItem::factory()->forSession($session)->create();

    Livewire::actingAs($user)
        ->test(SessionDetail::class, ['session' => $session])
        ->call('delete');

    expect($session->fresh()->trashed())->toBeTrue()
        ->and($item->fresh()->trashed())->toBeTrue();
});

it('never shows another user his history', function () {
    $user = User::factory()->create();
    $foreign = historySession(User::factory()->create(), [
        'name' => 'Treino alheio',
        'finished_at' => '2026-09-29 19:00:00',
    ]);

    Livewire::actingAs($user)->test(History::class)->assertDontSee('Treino alheio');

    $this->actingAs($user)->get(route('history.show', $foreign))->assertNotFound();
});
