<?php

namespace App\Models;

use Database\Factories\WorkoutTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'rest_seconds', 'notes', 'position', 'is_active'])]
class WorkoutTemplate extends Model
{
    /** @use HasFactory<WorkoutTemplateFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rest_seconds' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TemplateItem::class)->orderBy('position');
    }

    /**
     * The template the user should train next: the one after the last template
     * actually trained, wrapping around the end of the rotation.
     */
    public static function nextInRotationFor(User $user): ?self
    {
        $templates = $user->workoutTemplates()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            return null;
        }

        $lastTrainedId = $user->workoutSessions()
            ->whereNotNull('workout_template_id')
            ->orderByDesc('performed_on')
            ->orderByDesc('created_at')
            ->value('workout_template_id');

        $index = $templates->search(fn (self $template): bool => $template->id === $lastTrainedId);

        if ($index === false) {
            return $templates->first();
        }

        return $templates->get(($index + 1) % $templates->count());
    }
}
