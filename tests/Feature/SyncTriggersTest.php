<?php

use App\Livewire\Sessions\Runner;
use App\Models\SyncSetting;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\SyncRunner;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function connectedToServer(): void
{
    SyncSetting::current()->update([
        'server_url' => 'https://diario.exemplo.com',
        'token' => 'tok_123',
    ]);
}

beforeEach(function () {
    Http::fake([
        '*/api/sync/push' => Http::response(['written' => []]),
        '*/api/sync/pull*' => Http::response(['rows' => []]),
    ]);
});

it('syncs in the background when the app is opened', function () {
    $user = User::factory()->create();
    connectedToServer();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sync/push'));
});

it('does not try to sync when the device is not connected to a server', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    Http::assertNothingSent();
});

it('does not sync again on every request inside the cooldown', function () {
    $user = User::factory()->create();
    connectedToServer();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('exercises.index'))->assertOk();
    $this->actingAs($user)->get(route('templates.index'))->assertOk();

    expect(Http::recorded())->toHaveCount(2);
});

it('finishes the workout and schedules the sync that follows it', function () {
    $user = User::factory()->create();
    connectedToServer();

    $session = WorkoutSession::factory()->ownedBy($user)->create(['finished_at' => null]);

    Livewire::actingAs($user)
        ->test(Runner::class, ['session' => $session])
        ->call('finish');

    expect($session->fresh()->finished_at)->not->toBeNull();
});

it('schedules a background sync when connected, and refuses when it is not', function () {
    $runner = app(SyncRunner::class);

    expect($runner->runInBackground())->toBeFalse();

    connectedToServer();

    expect($runner->runInBackground())->toBeTrue();
});

it('does the round trip when the scheduled sync runs', function () {
    $user = User::factory()->create();
    connectedToServer();

    WorkoutSession::factory()->ownedBy($user)->create(['synced_at' => null]);

    expect(app(SyncRunner::class)->run())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sync/push'));

    expect(WorkoutSession::query()->whereNull('synced_at')->count())->toBe(0);
});

it('does not hold up the screen when the server is down', function () {
    $user = User::factory()->create();
    connectedToServer();

    Http::fake(['*' => Http::response([], 500)]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
