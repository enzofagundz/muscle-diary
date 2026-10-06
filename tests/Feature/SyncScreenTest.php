<?php

use App\Livewire\Sync;
use App\Models\SyncSetting;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('shows how much is waiting to be sent and when it last synced', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    WorkoutSession::factory()->ownedBy($user)->create(['synced_at' => null]);
    WorkoutSession::factory()->ownedBy($user)->create(['synced_at' => now()]);

    SyncSetting::current()->update(['last_synced_at' => '2026-09-29 21:00:00']);

    Livewire::test(Sync::class)
        ->assertViewHas('pending', 1)
        ->assertSee('29 set 2026')
        ->assertSee('Conectar');
});

it('stores the token when the server accepts the login', function () {
    Http::fake([
        '*/api/token' => Http::response(['token' => 'tok_123']),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(Sync::class)
        ->set('serverUrl', 'https://diario.exemplo.com')
        ->set('email', 'enzo@example.com')
        ->set('password', 'secret-password')
        ->call('connect')
        ->assertHasNoErrors()
        ->assertSee('Conectado');

    $settings = SyncSetting::current();

    expect($settings->token)->toBe('tok_123')
        ->and($settings->server_url)->toBe('https://diario.exemplo.com');
});

it('says so when the server refuses the login', function () {
    Http::fake(['*/api/token' => Http::response([], 422)]);

    Livewire::actingAs(User::factory()->create())
        ->test(Sync::class)
        ->set('serverUrl', 'https://diario.exemplo.com')
        ->set('email', 'enzo@example.com')
        ->set('password', 'errada')
        ->call('connect');

    expect(SyncSetting::current()->token)->toBeNull();
});

it('pushes what is pending, marks it sent and keeps the pull moment', function () {
    $user = User::factory()->create();

    WorkoutSession::factory()->ownedBy($user)->create(['synced_at' => null]);

    SyncSetting::current()->update([
        'server_url' => 'https://diario.exemplo.com',
        'token' => 'tok_123',
    ]);

    Http::fake([
        '*/api/sync/push' => Http::response(['written' => ['workout_sessions' => 1]]),
        '*/api/sync/pull*' => Http::response(['rows' => []]),
    ]);

    Livewire::actingAs($user)->test(Sync::class)->call('sync')->assertSee('Sincronizado');

    expect(SyncSetting::current()->last_synced_at)->not->toBeNull()
        ->and(WorkoutSession::query()->whereNull('synced_at')->count())->toBe(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sync/push')
        && $request['rows']['workout_sessions'][0]['name'] !== null);
});

it('leaves everything pending when the push fails', function () {
    $user = User::factory()->create();

    WorkoutSession::factory()->ownedBy($user)->create(['synced_at' => null]);

    SyncSetting::current()->update([
        'server_url' => 'https://diario.exemplo.com',
        'token' => 'tok_123',
    ]);

    Http::fake(['*' => Http::response([], 500)]);

    Livewire::actingAs($user)->test(Sync::class)->call('sync')->assertSee('A sincronização falhou');

    expect(WorkoutSession::query()->whereNull('synced_at')->count())->toBe(1)
        ->and(SyncSetting::current()->last_synced_at)->toBeNull();
});

it('resends the whole history on demand', function () {
    $user = User::factory()->create();

    $session = WorkoutSession::factory()->ownedBy($user)->create(['synced_at' => now()]);

    SyncSetting::current()->update([
        'server_url' => 'https://diario.exemplo.com',
        'token' => 'tok_123',
    ]);

    Http::fake([
        '*/api/sync/push' => Http::response(['written' => ['workout_sessions' => 1]]),
        '*/api/sync/pull*' => Http::response(['rows' => []]),
    ]);

    Livewire::actingAs($user)->test(Sync::class)->call('resendAll')->assertSee('Sincronizado');

    expect(WorkoutSession::query()->whereNull('synced_at')->count())->toBe(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sync/push')
        && collect($request['rows']['workout_sessions'] ?? [])->contains('id', $session->id));
});

it('keeps everything pending when resending fails', function () {
    $user = User::factory()->create();

    WorkoutSession::factory()->ownedBy($user)->create(['synced_at' => now()]);

    SyncSetting::current()->update([
        'server_url' => 'https://diario.exemplo.com',
        'token' => 'tok_123',
    ]);

    Http::fake(['*' => Http::response([], 500)]);

    Livewire::actingAs($user)->test(Sync::class)->call('resendAll')->assertSee('A sincronização falhou');

    expect(WorkoutSession::query()->whereNull('synced_at')->count())->toBe(1);
});

it('disconnects even when the server cannot be reached', function () {
    SyncSetting::current()->update([
        'server_url' => 'http://localhost:8000',
        'token' => 'tok_123',
    ]);

    Http::fake(['*' => fn () => throw new ConnectionException('Failed to connect')]);

    Livewire::actingAs(User::factory()->create())
        ->test(Sync::class)
        ->call('disconnect')
        ->assertSee('Desconectado');

    expect(SyncSetting::current()->isConnected())->toBeFalse();
});
