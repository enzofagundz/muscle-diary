<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Database\Factories\WorkoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'workout_template_id',
    'name',
    'performed_on',
    'location',
    'rest_seconds',
    'notes',
    'finished_at',
])]
class WorkoutSession extends Model
{
    /** @use HasFactory<WorkoutSessionFactory> */
    use HasFactory, HasUlids, SoftDeletes, Syncable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'finished_at' => 'datetime',
            'rest_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkoutTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id');
    }

    /**
     * @return HasMany<SessionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SessionItem::class)->orderBy('position');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function previousForSameTemplate(): ?self
    {
        if ($this->workout_template_id === null) {
            return null;
        }

        return static::query()
            ->where('user_id', $this->user_id)
            ->where('workout_template_id', $this->workout_template_id)
            ->whereKeyNot($this->id)
            ->whereNotNull('finished_at')
            ->where(function (Builder $query): void {
                $query->where('performed_on', '<', $this->performed_on)
                    ->orWhere(function (Builder $query): void {
                        $query->where('performed_on', $this->performed_on)
                            ->where('created_at', '<', $this->created_at);
                    });
            })
            ->orderByDesc('performed_on')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Volume of the whole workout in kilograms. Sets that cannot be converted
     * — plates without a known weight — stay out instead of being counted as
     * if they were kilograms.
     */
    public function volumeInKg(): float
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->with(['exercise', 'sets'])->get();

        return round($items
            ->map(fn (SessionItem $item): ?array => $item->volume())
            ->filter(fn (?array $volume): bool => $volume !== null && $volume['unit'] === 'kg')
            ->sum(fn (array $volume): float => $volume['value']), 2);
    }

    public function durationInMinutes(): ?int
    {
        if ($this->finished_at === null) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->finished_at);
    }
}
