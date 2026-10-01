<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionItem>
 */
class SessionItemFactory extends Factory
{
    protected $model = SessionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workout_session_id' => WorkoutSession::factory(),
            'exercise_id' => Exercise::factory(),
            'position' => 0,
            'planned_sets' => 3,
            'rep_min' => 8,
            'rep_max' => 12,
            'rest_seconds' => null,
            'notes' => null,
        ];
    }

    public function forSession(WorkoutSession $session): static
    {
        return $this->state(fn (): array => [
            'user_id' => $session->user_id,
            'workout_session_id' => $session->id,
        ]);
    }
}
