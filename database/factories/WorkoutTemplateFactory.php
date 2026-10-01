<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutTemplate>
 */
class WorkoutTemplateFactory extends Factory
{
    protected $model = WorkoutTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'rest_seconds' => 90,
            'notes' => null,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }
}
