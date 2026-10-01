<?php

use App\Livewire\Templates\Index;
use App\Livewire\Templates\Show;
use App\Models\Exercise;
use App\Models\TemplateItem;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Livewire\Livewire;

it('creates a workout template', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('create')
        ->set('name', 'Upper 1')
        ->set('restSeconds', 90)
        ->set('notes', 'Foco em empurrar')
        ->call('save')
        ->assertHasNoErrors();

    $template = WorkoutTemplate::query()->where('user_id', $user->id)->sole();

    expect($template->name)->toBe('Upper 1')
        ->and($template->rest_seconds)->toBe(90)
        ->and($template->notes)->toBe('Foco em empurrar')
        ->and($template->is_active)->toBeTrue();
});

it('renames a template and changes its default rest', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create(['name' => 'Upper 1']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('edit', $template->id)
        ->set('name', 'Upper A')
        ->set('restSeconds', 120)
        ->call('save')
        ->assertHasNoErrors();

    expect($template->fresh()->name)->toBe('Upper A')
        ->and($template->fresh()->rest_seconds)->toBe(120);
});

it('keeps template names unique per user', function () {
    $user = User::factory()->create();
    WorkoutTemplate::factory()->ownedBy($user)->create(['name' => 'Upper 1']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('create')
        ->set('name', 'Upper 1')
        ->set('restSeconds', 90)
        ->call('save')
        ->assertHasErrors('name');

    expect(WorkoutTemplate::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('deactivates a template without deleting it', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('toggleActive', $template->id);

    expect($template->fresh()->is_active)->toBeFalse()
        ->and($template->fresh()->trashed())->toBeFalse();
});

it('duplicates a template together with its items', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create(['name' => 'Upper 1']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('duplicate', $template->id)
        ->assertHasNoErrors();

    $copy = WorkoutTemplate::query()
        ->where('user_id', $user->id)
        ->whereKeyNot($template->id)
        ->sole();

    expect($copy->name)->toBe('Upper 1 (cópia)')
        ->and($copy->rest_seconds)->toBe($template->rest_seconds)
        ->and($copy->items()->count())->toBe($template->items()->count());
});

it('deletes a template', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('delete', $template->id);

    expect(WorkoutTemplate::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($template->fresh()->trashed())->toBeTrue();
});

it('moves a template up and down the rotation', function () {
    $user = User::factory()->create();
    $upper = WorkoutTemplate::factory()->ownedBy($user)->create(['name' => 'Upper 1', 'position' => 0]);
    $lower = WorkoutTemplate::factory()->ownedBy($user)->create(['name' => 'Lower 1', 'position' => 1]);

    Livewire::actingAs($user)->test(Index::class)->call('moveDown', $upper->id);

    expect($upper->fresh()->position)->toBe(1)
        ->and($lower->fresh()->position)->toBe(0);

    Livewire::actingAs($user)->test(Index::class)->call('moveUp', $upper->id);

    expect($upper->fresh()->position)->toBe(0)
        ->and($lower->fresh()->position)->toBe(1);
});

it('never shows another user his templates', function () {
    $user = User::factory()->create();
    WorkoutTemplate::factory()->ownedBy(User::factory()->create())->create(['name' => 'Modelo alheio']);

    Livewire::actingAs($user)->test(Index::class)->assertDontSee('Modelo alheio');
});

it('refuses to touch a template that belongs to another user', function () {
    $user = User::factory()->create();
    $foreign = WorkoutTemplate::factory()->ownedBy(User::factory()->create())->create();

    Livewire::actingAs($user)->test(Index::class)->call('delete', $foreign->id);

    expect($foreign->fresh()->trashed())->toBeFalse();
});

it('adds an exercise to a template with its planned sets, reps and rest', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $exercise = Exercise::factory()->create();

    Livewire::actingAs($user)
        ->test(Show::class, ['template' => $template])
        ->call('addItem')
        ->set('exerciseId', $exercise->id)
        ->set('sets', 4)
        ->set('repMin', 8)
        ->set('repMax', 12)
        ->set('restSeconds', 45)
        ->set('notes', 'Pegada neutra')
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = $template->items()->sole();

    expect($item->exercise_id)->toBe($exercise->id)
        ->and($item->sets)->toBe(4)
        ->and($item->rep_min)->toBe(8)
        ->and($item->rep_max)->toBe(12)
        ->and($item->rest_seconds)->toBe(45)
        ->and($item->notes)->toBe('Pegada neutra')
        ->and($item->position)->toBe(1);
});

it('updates an exercise already planned in a template', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $item = TemplateItem::factory()->forTemplate($template)->create(['sets' => 3]);

    Livewire::actingAs($user)
        ->test(Show::class, ['template' => $template])
        ->call('editItem', $item->id)
        ->set('sets', 5)
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($item->fresh()->sets)->toBe(5);
});

it('reorders the exercises inside a template', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $first = TemplateItem::factory()->forTemplate($template)->create(['position' => 0]);
    $second = TemplateItem::factory()->forTemplate($template)->create(['position' => 1]);

    Livewire::actingAs($user)
        ->test(Show::class, ['template' => $template])
        ->call('moveDown', $first->id);

    expect($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(0);
});

it('removes an exercise from a template', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $item = TemplateItem::factory()->forTemplate($template)->create();

    Livewire::actingAs($user)
        ->test(Show::class, ['template' => $template])
        ->call('removeItem', $item->id);

    expect($template->items()->count())->toBe(0)
        ->and($item->fresh()->trashed())->toBeTrue();
});

it('refuses an exercise that is not visible to the user', function () {
    $user = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $foreign = Exercise::factory()->ownedBy(User::factory()->create())->create();

    Livewire::actingAs($user)
        ->test(Show::class, ['template' => $template])
        ->call('addItem')
        ->set('exerciseId', $foreign->id)
        ->set('sets', 3)
        ->call('saveItem')
        ->assertHasErrors('exerciseId');

    expect($template->items()->count())->toBe(0);
});

it('does not let another user open a template', function () {
    $owner = User::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($owner)->create();
    TemplateItem::factory()->forTemplate($template)->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('templates.edit', $template))
        ->assertNotFound();

    expect($template->items()->count())->toBe(1);
});
