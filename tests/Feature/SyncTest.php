<?php

use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\TemplateItem;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Services\SyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->workspace = sys_get_temp_dir().'/diario-sync-'.uniqid();
    File::makeDirectory($this->workspace, recursive: true);

    foreach (['device', 'server'] as $name) {
        $path = $this->workspace."/{$name}.sqlite";
        File::put($path, '');

        config(["database.connections.{$name}" => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        Artisan::call('migrate', ['--database' => $name, '--force' => true]);
    }

    $this->device = new SyncService('device');
    $this->server = new SyncService('server');

    $this->user = User::factory()->create();
});

afterEach(function () {
    File::deleteDirectory($this->workspace);
});

/**
 * A workout recorded on the device, the way the app records one offline.
 */
function recordOnDevice(User $user, array $sets): WorkoutSession
{
    $session = WorkoutSession::on('device')->create([
        'user_id' => $user->id,
        'name' => 'Upper 1',
        'performed_on' => '2026-09-29',
        'location' => 'Sky',
        'rest_seconds' => 90,
    ]);

    $item = SessionItem::on('device')->create([
        'user_id' => $user->id,
        'workout_session_id' => $session->id,
        'exercise_id' => Exercise::factory()->create()->id,
        'position' => 1,
        'planned_sets' => count($sets),
        'notes' => 'Sem strap',
    ]);

    foreach ($sets as $index => $set) {
        SessionSet::on('device')->create([
            'user_id' => $user->id,
            'session_item_id' => $item->id,
            'set_number' => $index + 1,
            'load' => $set[0],
            'unit' => $set[1],
            'reps' => $set[2],
        ]);
    }

    return $session;
}

it('sends a workout recorded offline to the server with its sets and notes', function () {
    recordOnDevice($this->user, [[14, 'plate', 12], [14, 'plate', 7]]);

    $payload = $this->device->pending();

    expect($payload)->toHaveKeys(['workout_sessions', 'session_items', 'session_sets']);

    $this->server->push($payload, $this->user);
    $this->device->markSynced($payload);

    $session = WorkoutSession::on('server')->with(['items.sets'])->sole();

    expect($session->name)->toBe('Upper 1')
        ->and($session->location)->toBe('Sky')
        ->and($session->performed_on->toDateString())->toBe('2026-09-29')
        ->and($session->items)->toHaveCount(1)
        ->and($session->items->first()->notes)->toBe('Sem strap')
        ->and($session->items->first()->sets)->toHaveCount(2)
        ->and($session->items->first()->sets->first()->load)->toBe(14.0)
        ->and($session->items->first()->sets->last()->reps)->toBe(7);

    expect($this->device->pending())->toBeEmpty();
});

it('sends a workout created on the server back to the device', function () {
    $session = WorkoutSession::on('server')->create([
        'user_id' => $this->user->id,
        'name' => 'Lower 1',
        'performed_on' => '2026-09-28',
    ]);

    $this->device->apply($this->server->pull(null, $this->user));

    expect(WorkoutSession::on('device')->whereKey($session->id)->value('name'))->toBe('Lower 1');
});

it('settles a conflict on the same row by the newest change', function () {
    $session = recordOnDevice($this->user, [[14, 'plate', 12]]);

    $this->server->push($this->device->pending(), $this->user);

    WorkoutSession::on('device')->whereKey($session->id)->update([
        'name' => 'Nome do aparelho',
        'updated_at' => '2026-09-29 20:00:00',
    ]);

    WorkoutSession::on('server')->whereKey($session->id)->update([
        'name' => 'Nome do site',
        'updated_at' => '2026-09-29 21:00:00',
    ]);

    $this->server->push($this->device->pending(), $this->user);

    expect(WorkoutSession::on('server')->whereKey($session->id)->value('name'))->toBe('Nome do site');
});

it('never lets a pull overwrite a change that has not been sent yet', function () {
    $session = recordOnDevice($this->user, [[14, 'plate', 12]]);

    $this->server->push($this->device->pending(), $this->user);
    $this->device->markSynced($this->device->pending());

    WorkoutSession::on('server')->whereKey($session->id)->update([
        'name' => 'Nome do site',
        'updated_at' => '2026-09-29 21:00:00',
    ]);

    WorkoutSession::on('device')->whereKey($session->id)->update([
        'name' => 'Nome do aparelho',
        'updated_at' => '2026-09-29 22:00:00',
        'synced_at' => null,
    ]);

    $this->device->apply($this->server->pull(null, $this->user));

    expect(WorkoutSession::on('device')->whereKey($session->id)->value('name'))->toBe('Nome do aparelho');

    $this->server->push($this->device->pending(), $this->user);

    expect(WorkoutSession::on('server')->whereKey($session->id)->value('name'))->toBe('Nome do aparelho');
});

it('refuses a request without a token', function () {
    $this->getJson('/api/sync/pull')->assertUnauthorized();
    $this->postJson('/api/sync/push', ['rows' => []])->assertUnauthorized();
});

it('gives a token for a valid login and lets it be revoked', function () {
    $this->user->update(['password' => 'secret-password']);

    $token = $this->postJson('/api/token', [
        'email' => $this->user->email,
        'password' => 'secret-password',
        'device_name' => 'Celular do Enzo',
    ])->assertOk()->json('token');

    $this->withToken($token)->getJson('/api/sync/pull')->assertOk();

    $this->withToken($token)->deleteJson('/api/token')->assertOk();

    expect($this->user->tokens()->count())->toBe(0);
});

it('refuses a token request with the wrong password', function () {
    $this->postJson('/api/token', [
        'email' => $this->user->email,
        'password' => 'chute',
        'device_name' => 'Celular do Enzo',
    ])->assertStatus(422);
});

it('scopes what comes back to the user of the token', function () {
    $other = User::factory()->create();

    WorkoutSession::on('server')->create([
        'user_id' => $other->id,
        'name' => 'Treino alheio',
        'performed_on' => '2026-09-28',
    ]);

    $token = $this->user->createToken('teste')->plainTextToken;

    $rows = $this->withToken($token)->getJson('/api/sync/pull')->assertOk()->json('rows');

    expect($rows)->toBe([]);
});

it('takes a workout pushed through the endpoint', function () {
    $this->app->instance(SyncService::class, $this->server);

    recordOnDevice($this->user, [[14, 'plate', 12]]);

    $token = $this->user->createToken('teste')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/sync/push', ['rows' => $this->device->pending()])
        ->assertOk()
        ->assertJsonPath('written.workout_sessions', 1);

    expect(WorkoutSession::on('server')->count())->toBe(1);
});

it('sends a template created on the device with its items', function () {
    $user = User::factory()->create();

    $exercise = Exercise::on('device')->create([
        'user_id' => $user->id,
        'name' => 'Remada cavalinho',
        'muscle_group' => 'Costas',
        'unit_default' => 'plate',
    ]);

    $template = WorkoutTemplate::on('device')->create([
        'user_id' => $user->id,
        'name' => 'Upper 1',
        'rest_seconds' => 90,
        'position' => 1,
    ]);

    TemplateItem::on('device')->create([
        'user_id' => $user->id,
        'workout_template_id' => $template->id,
        'exercise_id' => $exercise->id,
        'position' => 1,
        'sets' => 4,
        'rep_min' => 8,
        'rep_max' => 12,
    ]);

    $this->server->push($this->device->pending(), $user);

    expect(WorkoutTemplate::on('server')->value('name'))->toBe('Upper 1')
        ->and(TemplateItem::on('server')->value('sets'))->toBe(4)
        ->and(Exercise::on('server')->where('name', 'Remada cavalinho')->value('muscle_group'))->toBe('Costas');
});

it('brings the shared catalog down to the device', function () {
    $user = User::factory()->create();

    Exercise::on('server')->create([
        'user_id' => null,
        'name' => 'Supino máquina',
        'muscle_group' => 'Peito',
        'unit_default' => 'plate',
    ]);

    $this->device->apply($this->server->pull(null, $user));

    expect(Exercise::on('device')->where('name', 'Supino máquina')->value('user_id'))->toBeNull();
});

it('never lets the device rewrite the shared catalog', function () {
    $user = User::factory()->create();

    $catalog = Exercise::on('server')->create([
        'user_id' => null,
        'name' => 'Supino máquina',
        'muscle_group' => 'Peito',
        'unit_default' => 'plate',
    ]);

    $this->server->push([
        'exercises' => [[
            'id' => $catalog->id,
            'user_id' => null,
            'name' => 'Nome trocado pelo aparelho',
            'muscle_group' => 'Peito',
            'unit_default' => 'plate',
            'updated_at' => '2030-01-01 00:00:00',
        ]],
    ], $user);

    expect(Exercise::on('server')->whereKey($catalog->id)->value('name'))->toBe('Supino máquina');
});

it('propagates a deletion in both directions', function () {
    $user = User::factory()->create();

    $session = recordOnDevice($user, [[14, 'plate', 12]]);

    $this->server->push($this->device->pending(), $user);
    $this->device->markSynced($this->device->pending());

    WorkoutSession::on('device')->whereKey($session->id)->first()->delete();

    $this->server->push($this->device->pending(), $user);
    $this->device->markSynced($this->device->pending());

    expect(WorkoutSession::on('server')->withTrashed()->whereKey($session->id)->first()->trashed())->toBeTrue()
        ->and(WorkoutSession::on('server')->count())->toBe(0);

    $serverSession = WorkoutSession::on('server')->withTrashed()->whereKey($session->id)->first();
    $serverSession->forceFill(['deleted_at' => null, 'updated_at' => now()->addDay()])->save();

    $this->device->apply($this->server->pull(null, $user));

    expect(WorkoutSession::on('device')->whereKey($session->id)->count())->toBe(1);
});

it('never brings another account down to the device', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    WorkoutTemplate::on('server')->create([
        'user_id' => $other->id,
        'name' => 'Modelo alheio',
        'rest_seconds' => 90,
        'position' => 1,
    ]);

    $this->device->apply($this->server->pull(null, $user));

    expect(WorkoutTemplate::on('device')->count())->toBe(0);
});
