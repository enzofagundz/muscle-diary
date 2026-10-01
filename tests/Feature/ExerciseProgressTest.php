<?php

use App\Enums\LoadUnit;
use App\Livewire\Exercises\Show;
use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
use Livewire\Livewire;

function performance(User $user, Exercise $exercise, array $sessionAttributes, array $sets): WorkoutSession
{
    $session = WorkoutSession::factory()->ownedBy($user)->create(array_merge([
        'finished_at' => '2026-09-29 19:00:00',
    ], $sessionAttributes));

    $item = SessionItem::factory()->forSession($session)->create([
        'exercise_id' => $exercise->id,
        'position' => 1,
    ]);

    foreach ($sets as $index => $set) {
        SessionSet::factory()->forItem($item)->create([
            'set_number' => $index + 1,
            'load' => $set[0],
            'unit' => $set[1],
            'reps' => $set[2],
            'is_warmup' => $set[3] ?? false,
        ]);
    }

    return $session;
}

it('lists every execution of an exercise from the oldest to the newest', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Supino máquina', 'unit_default' => LoadUnit::Plates]);

    performance($user, $exercise, ['performed_on' => '2026-09-29', 'location' => 'Sky'], [[16, 'plate', 8]]);
    performance($user, $exercise, ['performed_on' => '2026-09-15', 'location' => 'Casa'], [[14, 'plate', 10]]);

    $component = Livewire::actingAs($user)->test(Show::class, ['exercise' => $exercise]);

    $executions = $component->viewData('executions');

    expect($executions)->toHaveCount(2)
        ->and($executions->first()['session']->performed_on->toDateString())->toBe('2026-09-15')
        ->and($executions->first()['session']->location)->toBe('Casa')
        ->and($executions->last()['session']->location)->toBe('Sky');

    $component->assertSee('Supino máquina')
        ->assertSee('Casa')
        ->assertSee('Sky')
        ->assertSee('2 execuções');
});

it('points out the best performance and how it is measured', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['unit_default' => LoadUnit::Plates]);

    performance($user, $exercise, ['performed_on' => '2026-09-15'], [[14, 'plate', 10], [16, 'plate', 6]]);
    performance($user, $exercise, ['performed_on' => '2026-09-22'], [[16, 'plate', 8]]);

    $component = Livewire::actingAs($user)->test(Show::class, ['exercise' => $exercise]);

    $best = $component->viewData('best');

    expect($best['load'])->toBe(16.0)
        ->and($best['reps'])->toBe(8)
        ->and($best['date'])->toBe('2026-09-22');

    $component->assertSee('maior carga');
});

it('leaves warmup sets out of the chart and of the best performance', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['unit_default' => LoadUnit::Plates]);

    performance($user, $exercise, ['performed_on' => '2026-09-15'], [[20, 'plate', 20, true], [12, 'plate', 10]]);

    $component = Livewire::actingAs($user)->test(Show::class, ['exercise' => $exercise]);

    expect($component->viewData('best')['load'])->toBe(12.0)
        ->and($component->viewData('chart')['points'])->toHaveCount(1);
});

it('draws the load chart with the points of each execution', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['unit_default' => LoadUnit::Plates]);

    performance($user, $exercise, ['performed_on' => '2026-09-15'], [[10, 'plate', 10]]);
    performance($user, $exercise, ['performed_on' => '2026-09-22'], [[14, 'plate', 10]]);
    performance($user, $exercise, ['performed_on' => '2026-09-29'], [[12, 'plate', 10]]);

    $chart = Livewire::actingAs($user)->test(Show::class, ['exercise' => $exercise])->viewData('chart');

    expect($chart['points'])->toHaveCount(3)
        ->and($chart['points'][0]['load'])->toBe(10.0)
        ->and($chart['points'][1]['load'])->toBe(14.0)
        ->and($chart['points'][2]['load'])->toBe(12.0)
        ->and($chart['polyline'])->toBeString()
        ->and($chart['unit'])->toBe('placas');

    Livewire::actingAs($user)->test(Show::class, ['exercise' => $exercise])->assertSee('polyline', escape: false);
});

it('leaves executions recorded in another unit out of the chart', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['unit_default' => LoadUnit::Plates]);

    performance($user, $exercise, ['performed_on' => '2026-09-15'], [[10, 'plate', 10]]);
    performance($user, $exercise, ['performed_on' => '2026-09-22'], [[60, 'kg', 10]]);

    $chart = Livewire::actingAs($user)->test(Show::class, ['exercise' => $exercise])->viewData('chart');

    expect($chart['points'])->toHaveCount(1);
});

it('shows an empty state for an exercise never performed', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    $component = Livewire::actingAs($user)->test(Show::class, ['exercise' => $exercise]);

    expect($component->viewData('executions'))->toBeEmpty()
        ->and($component->viewData('best'))->toBeNull()
        ->and($component->viewData('chart')['points'])->toBeEmpty();

    $component->assertSee('Nenhuma execução registrada');
});

it('never shows another user his performances', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    performance(User::factory()->create(), $exercise, ['performed_on' => '2026-09-15', 'location' => 'Academia alheia'], [[99, 'plate', 1]]);

    Livewire::actingAs($user)
        ->test(Show::class, ['exercise' => $exercise])
        ->assertDontSee('Academia alheia')
        ->assertSee('Nenhuma execução registrada');
});
