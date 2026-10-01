<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\TemplateItem;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateItem>
 */
class TemplateItemFactory extends Factory
{
    protected $model = TemplateItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workout_template_id' => WorkoutTemplate::factory(),
            'exercise_id' => Exercise::factory(),
            'position' => 0,
            'sets' => 3,
            'rep_min' => 8,
            'rep_max' => 12,
            'rest_seconds' => null,
            'notes' => null,
        ];
    }

    public function forTemplate(WorkoutTemplate $template): static
    {
        return $this->state(fn (): array => [
            'user_id' => $template->user_id,
            'workout_template_id' => $template->id,
        ]);
    }
}
