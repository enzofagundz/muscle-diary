<?php

namespace Database\Factories;

use App\Enums\LoadUnit;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'based_on_id' => null,
            'name' => fake()->unique()->words(2, true),
            'muscle_group' => fake()->randomElement(MuscleGroup::values()),
            'unit_default' => LoadUnit::Kilograms,
            'kg_per_plate' => null,
            'notes' => null,
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }
}
