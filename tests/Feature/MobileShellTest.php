<?php

use App\Livewire\Dashboard;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
use Livewire\Livewire;

function asNativeApp(): void
{
    config(['nativephp-internal.running' => true]);
}

it('opens straight into the workout that is still in progress', function () {
    asNativeApp();

    $user = User::factory()->create();
    $session = WorkoutSession::factory()->ownedBy($user)->create(['finished_at' => null]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertRedirect(route('sessions.run', $session));
});

it('goes to the home screen when nothing is in progress', function () {
    asNativeApp();

    $user = User::factory()->create();
    WorkoutSession::factory()->ownedBy($user)->create(['finished_at' => now()]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertNoRedirect();
});

it('does not jump into a workout when it is the browser', function () {
    $user = User::factory()->create();
    WorkoutSession::factory()->ownedBy($user)->create(['finished_at' => null]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertNoRedirect();
});

it('shows the bottom navigation inside the native shell', function () {
    asNativeApp();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Navegação principal')
        ->assertSee('Histórico')
        ->assertSee('Modelos')
        ->assertSee('Sync');
});

it('keeps the browser layout without the mobile tab bar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Navegação principal');
});

it('keeps the top navigation in the browser', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Exercícios');
});

it('takes the safe area into account only in the native shell', function () {
    asNativeApp();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('native-shell');
});

it('offers a numeric keyboard for load and reps on the workout screen', function () {
    $user = User::factory()->create();
    $session = WorkoutSession::factory()->ownedBy($user)->create(['finished_at' => null]);
    $item = SessionItem::factory()->forSession($session)->create();
    SessionSet::factory()->forItem($item)->create();

    $this->actingAs($user)
        ->get(route('sessions.run', $session))
        ->assertOk()
        ->assertSee('inputmode="decimal"', escape: false)
        ->assertSee('inputmode="numeric"', escape: false);
});
