<?php

use App\Livewire\Dashboard;
use App\Livewire\Sessions\Runner;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\TemplateItem;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Livewire\Livewire;

function templateFor(User $user, string $name, int $position, bool $active = true): WorkoutTemplate
{
    return WorkoutTemplate::factory()->ownedBy($user)->create([
        'name' => $name,
        'position' => $position,
        'is_active' => $active,
    ]);
}

function finishedSessionFor(User $user, WorkoutTemplate $template, string $date): WorkoutSession
{
    return WorkoutSession::factory()->ownedBy($user)->create([
        'workout_template_id' => $template->id,
        'name' => $template->name,
        'performed_on' => $date,
        'finished_at' => $date.' 10:00:00',
    ]);
}

it('suggests the first template in the rotation when nothing has been trained yet', function () {
    $user = User::factory()->create();
    $upper = templateFor($user, 'Upper 1', 1);
    templateFor($user, 'Lower 1', 2);

    expect(WorkoutTemplate::nextInRotationFor($user)->id)->toBe($upper->id);
});

it('suggests the template after the last one trained', function () {
    $user = User::factory()->create();
    $upper = templateFor($user, 'Upper 1', 1);
    $lower = templateFor($user, 'Lower 1', 2);
    templateFor($user, 'Upper 2', 3);

    finishedSessionFor($user, $upper, '2026-09-28');

    expect(WorkoutTemplate::nextInRotationFor($user)->id)->toBe($lower->id);
});

it('wraps around to the first template after the last one', function () {
    $user = User::factory()->create();
    $upper = templateFor($user, 'Upper 1', 1);
    $lower = templateFor($user, 'Lower 1', 2);

    finishedSessionFor($user, $lower, '2026-09-28');

    expect(WorkoutTemplate::nextInRotationFor($user)->id)->toBe($upper->id);
});

it('skips templates that are out of the rotation', function () {
    $user = User::factory()->create();
    $upper = templateFor($user, 'Upper 1', 1);
    $lower = templateFor($user, 'Lower 1', 2, active: false);
    $upperTwo = templateFor($user, 'Upper 2', 3);

    finishedSessionFor($user, $upper, '2026-09-28');

    expect(WorkoutTemplate::nextInRotationFor($user)->id)->toBe($upperTwo->id);
});

it('falls back to the first template when the last one left the rotation', function () {
    $user = User::factory()->create();
    $upper = templateFor($user, 'Upper 1', 1);
    $lower = templateFor($user, 'Lower 1', 2);
    $upperTwo = templateFor($user, 'Upper 2', 3, active: false);

    finishedSessionFor($user, $upperTwo, '2026-09-28');

    expect(WorkoutTemplate::nextInRotationFor($user)->id)->toBe($upper->id)
        ->and(WorkoutTemplate::nextInRotationFor($user)->id)->not->toBe($lower->id);
});

it('has no suggestion when there is no template at all', function () {
    expect(WorkoutTemplate::nextInRotationFor(User::factory()->create()))->toBeNull();
});

it('shows the next workout on the dashboard', function () {
    $user = User::factory()->create();
    $upper = templateFor($user, 'Upper 1', 1);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Upper 1')
        ->assertSet('templateId', $upper->id);
});

it('starts a session from a template with the planned exercises copied in', function () {
    $user = User::factory()->create();
    $template = templateFor($user, 'Upper 1', 1);
    $template->update(['rest_seconds' => 120]);

    $exercise = Exercise::factory()->create();
    TemplateItem::factory()->forTemplate($template)->create([
        'exercise_id' => $exercise->id,
        'position' => 1,
        'sets' => 4,
        'rep_min' => 8,
        'rep_max' => 12,
        'notes' => 'Pegada neutra',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('location', 'Sky')
        ->set('performedOn', '2026-09-29')
        ->call('start')
        ->assertHasNoErrors()
        ->assertRedirect();

    $session = WorkoutSession::query()->where('user_id', $user->id)->sole();

    expect($session->name)->toBe('Upper 1')
        ->and($session->workout_template_id)->toBe($template->id)
        ->and($session->location)->toBe('Sky')
        ->and($session->performed_on->toDateString())->toBe('2026-09-29')
        ->and($session->rest_seconds)->toBe(120)
        ->and($session->finished_at)->toBeNull();

    $item = $session->items()->sole();

    expect($item->exercise_id)->toBe($exercise->id)
        ->and($item->position)->toBe(1)
        ->and($item->planned_sets)->toBe(4)
        ->and($item->rep_min)->toBe(8)
        ->and($item->rep_max)->toBe(12)
        ->and($item->notes)->toBe('Pegada neutra')
        ->and($item->sets()->count())->toBe(0);
});

it('starts an empty session when no template is chosen', function () {
    $user = User::factory()->create();
    templateFor($user, 'Upper 1', 1);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('templateId', '')
        ->set('name', 'Treino livre')
        ->call('start')
        ->assertHasNoErrors();

    $session = WorkoutSession::query()->where('user_id', $user->id)->sole();

    expect($session->workout_template_id)->toBeNull()
        ->and($session->name)->toBe('Treino livre')
        ->and($session->items()->count())->toBe(0);
});

it('offers to resume a session that is still in progress', function () {
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->ownedBy($user)->create(['name' => 'Upper 1', 'finished_at' => null]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Upper 1')
        ->assertSee('Retomar')
        ->assertSee(route('sessions.run', $session));
});

it('offers the locations already used', function () {
    $user = User::factory()->create();
    WorkoutSession::factory()->ownedBy($user)->create(['location' => 'Sky', 'finished_at' => '2026-09-28 10:00:00']);
    WorkoutSession::factory()->ownedBy($user)->create(['location' => 'Sky', 'finished_at' => '2026-09-27 10:00:00']);
    WorkoutSession::factory()->ownedBy($user)->create(['location' => 'Casa', 'finished_at' => '2026-09-26 10:00:00']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('locations', fn ($locations) => $locations->all() === ['Casa', 'Sky']);
});

it('does not change a session that was already started when the template changes', function () {
    $user = User::factory()->create();
    $template = templateFor($user, 'Upper 1', 1);
    $item = TemplateItem::factory()->forTemplate($template)->create(['sets' => 3]);

    Livewire::actingAs($user)->test(Dashboard::class)->call('start');

    $sessionItem = SessionItem::query()->sole();

    $item->update(['sets' => 5]);
    $item->delete();
    $template->update(['name' => 'Upper A']);

    expect($sessionItem->fresh()->planned_sets)->toBe(3)
        ->and($sessionItem->fresh()->trashed())->toBeFalse()
        ->and(WorkoutSession::query()->sole()->name)->toBe('Upper 1');
});

it('runs a session showing the planned exercises', function () {
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->ownedBy($user)->create(['name' => 'Upper 1']);
    $item = SessionItem::factory()->forSession($session)->create(['position' => 1]);

    Livewire::actingAs($user)
        ->test(Runner::class, ['session' => $session])
        ->assertSee($item->exercise->name);
});

it('does not let another user run a session', function () {
    $session = WorkoutSession::factory()->ownedBy(User::factory()->create())->create();

    $this->actingAs(User::factory()->create())
        ->get(route('sessions.run', $session))
        ->assertNotFound();
});
