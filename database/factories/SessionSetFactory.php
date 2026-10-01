<?php

namespace Database\Factories;

use App\Enums\LoadUnit;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionSet>
 */
class SessionSetFactory extends Factory
{
    protected $model = SessionSet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_item_id' => SessionItem::factory(),
            'set_number' => 1,
            'part' => 0,
            'load' => 60,
            'unit' => LoadUnit::Kilograms,
            'reps' => 10,
            'is_warmup' => false,
            'notes' => null,
        ];
    }

    public function forItem(SessionItem $item): static
    {
        return $this->state(fn (): array => [
            'user_id' => $item->user_id,
            'session_item_id' => $item->id,
        ]);
    }
}
