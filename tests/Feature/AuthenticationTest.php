<?php

use App\Livewire\Login;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests from the dashboard to the login screen', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('logs in a user with valid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('shows the dashboard to an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
});

it('renders the login screen for guests', function () {
    $this->get(route('login'))->assertOk()->assertSee('Entrar');
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('has no open registration route', function () {
    $this->get('/register')->assertNotFound();
});

it('throttles login after too many failed attempts', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, 5) as $attempt) {
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');
    }

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});
