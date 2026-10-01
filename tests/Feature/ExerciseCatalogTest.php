<?php

use App\Enums\LoadUnit;
use App\Enums\MuscleGroup;
use App\Livewire\Exercises;
use App\Models\Exercise;
use App\Models\User;
use Livewire\Livewire;

it('creates an exercise of your own', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Exercises::class)
        ->call('create')
        ->set('name', 'Remada baixa unilateral')
        ->set('muscleGroup', MuscleGroup::Costas->value)
        ->set('unitDefault', LoadUnit::Plates->value)
        ->call('save')
        ->assertHasNoErrors();

    $exercise = Exercise::query()->where('user_id', $user->id)->sole();

    expect($exercise->name)->toBe('Remada baixa unilateral')
        ->and($exercise->muscle_group)->toBe(MuscleGroup::Costas->value)
        ->and($exercise->unit_default)->toBe(LoadUnit::Plates);
});

it('refuses a name already used by an exercise of yours', function () {
    $user = User::factory()->create();
    Exercise::factory()->ownedBy($user)->create(['name' => 'Rosca direta']);

    Livewire::actingAs($user)
        ->test(Exercises::class)
        ->call('create')
        ->set('name', 'Rosca direta')
        ->set('muscleGroup', MuscleGroup::Biceps->value)
        ->set('unitDefault', LoadUnit::Kilograms->value)
        ->call('save')
        ->assertHasErrors('name');

    expect(Exercise::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('searches by name and filters by muscle group', function () {
    $user = User::factory()->create();
    Exercise::factory()->ownedBy($user)->create(['name' => 'Rosca direta', 'muscle_group' => MuscleGroup::Biceps->value]);
    Exercise::factory()->ownedBy($user)->create(['name' => 'Supino reto', 'muscle_group' => MuscleGroup::Peito->value]);

    Livewire::actingAs($user)
        ->test(Exercises::class)
        ->set('search', 'rosca')
        ->assertSee('Rosca direta')
        ->assertDontSee('Supino reto')
        ->set('search', '')
        ->set('group', MuscleGroup::Peito->value)
        ->assertSee('Supino reto')
        ->assertDontSee('Rosca direta');
});

it('edits an exercise of your own', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->ownedBy($user)->create(['name' => 'Rosca direta']);

    Livewire::actingAs($user)
        ->test(Exercises::class)
        ->call('edit', $exercise->id)
        ->set('name', 'Rosca direta com barra W')
        ->set('unitDefault', LoadUnit::Pounds->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($exercise->fresh()->name)->toBe('Rosca direta com barra W')
        ->and($exercise->fresh()->unit_default)->toBe(LoadUnit::Pounds);
});

it('edits a catalog exercise by copying it into your own catalog', function () {
    $user = User::factory()->create();
    $catalog = Exercise::factory()->create(['name' => 'Supino máquina', 'muscle_group' => MuscleGroup::Peito->value]);

    Livewire::actingAs($user)
        ->test(Exercises::class)
        ->call('edit', $catalog->id)
        ->assertSet('name', 'Supino máquina')
        ->assertSee('catálogo base')
        ->set('name', 'Supino máquina articulada')
        ->call('save')
        ->assertHasNoErrors();

    $own = Exercise::query()->where('user_id', $user->id)->sole();

    expect($own->name)->toBe('Supino máquina articulada')
        ->and($own->based_on_id)->toBe($catalog->id)
        ->and($catalog->fresh()->name)->toBe('Supino máquina')
        ->and($catalog->fresh()->deleted_at)->toBeNull();
});

it('replaces a catalog entry with your own version when the name matches', function () {
    $user = User::factory()->create();
    $catalog = Exercise::factory()->create(['name' => 'Leg press']);

    $component = Livewire::actingAs($user)->test(Exercises::class);

    $component->assertSee('Leg press')
        ->call('create')
        ->set('name', 'Leg press')
        ->set('muscleGroup', MuscleGroup::Quadriceps->value)
        ->set('unitDefault', LoadUnit::Plates->value)
        ->call('save')
        ->assertHasNoErrors();

    $own = Exercise::query()->where('user_id', $user->id)->sole();

    expect($own->based_on_id)->toBe($catalog->id)
        ->and($component->viewData('exercises')->pluck('id')->all())->toBe([$own->id]);
});

it('archives an exercise out of the list and restores it later', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->ownedBy($user)->create(['name' => 'Rosca direta']);

    $component = Livewire::actingAs($user)->test(Exercises::class);

    $component->assertSee('Rosca direta')
        ->call('archive', $exercise->id)
        ->assertDontSee('Rosca direta');

    expect($exercise->fresh()->trashed())->toBeTrue();

    $component->set('showArchived', true)
        ->assertSee('Rosca direta')
        ->call('restore', $exercise->id)
        ->assertDontSee('Rosca direta');

    expect($exercise->fresh()->trashed())->toBeFalse();
});

it('refuses to touch an exercise that belongs to another user', function () {
    $user = User::factory()->create();
    $foreign = Exercise::factory()->ownedBy(User::factory()->create())->create();

    Livewire::actingAs($user)->test(Exercises::class)->call('archive', $foreign->id);

    expect($foreign->fresh()->deleted_at)->toBeNull();
});

it('refuses to archive a catalog exercise', function () {
    $user = User::factory()->create();
    $catalog = Exercise::factory()->create();

    Livewire::actingAs($user)->test(Exercises::class)->call('archive', $catalog->id);

    expect($catalog->fresh()->deleted_at)->toBeNull();
});

it('never shows another user his exercises', function () {
    $user = User::factory()->create();
    Exercise::factory()->ownedBy(User::factory()->create())->create(['name' => 'Exercício alheio']);

    Livewire::actingAs($user)->test(Exercises::class)->assertDontSee('Exercício alheio');
});
