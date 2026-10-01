<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSession>
 */
class WorkoutSessionFactory extends Factory
{
    protected $model = WorkoutSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workout_template_id' => null,
            'name' => 'Treino',
            'performed_on' => now()->toDateString(),
            'location' => null,
            'rest_seconds' => 90,
            'notes' => null,
            'finished_at' => null,
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    public function fromTemplate(WorkoutTemplate $template): static
    {
        return $this->state(fn (): array => [
            'user_id' => $template->user_id,
            'workout_template_id' => $template->id,
            'name' => $template->name,
            'rest_seconds' => $template->rest_seconds,
        ]);
    }
}
