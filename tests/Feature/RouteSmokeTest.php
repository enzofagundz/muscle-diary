<?php

use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\TemplateItem;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;

it('answers every screen without an error', function () {
    $user = User::factory()->create();

    $exercise = Exercise::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    TemplateItem::factory()->forTemplate($template)->create(['exercise_id' => $exercise->id]);

    $session = WorkoutSession::factory()->ownedBy($user)->create([
        'workout_template_id' => $template->id,
        'finished_at' => now(),
    ]);
    $item = SessionItem::factory()->forSession($session)->create(['exercise_id' => $exercise->id]);
    SessionSet::factory()->forItem($item)->create();

    $urls = [
        route('dashboard'),
        route('exercises.index'),
        route('exercises.show', $exercise),
        route('templates.index'),
        route('templates.edit', $template),
        route('sessions.run', $session),
        route('history.index'),
        route('history.show', $session),
        route('sync.index'),
    ];

    foreach ($urls as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

it('answers every screen inside the native shell too', function () {
    config(['nativephp-internal.running' => true]);

    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $template = WorkoutTemplate::factory()->ownedBy($user)->create();
    $session = WorkoutSession::factory()->ownedBy($user)->create(['finished_at' => now()]);

    $urls = [
        route('exercises.index'),
        route('exercises.show', $exercise),
        route('templates.index'),
        route('templates.edit', $template),
        route('history.index'),
        route('history.show', $session),
        route('sync.index'),
    ];

    foreach ($urls as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});
