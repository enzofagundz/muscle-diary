<?php

namespace App\Models;

use App\Enums\LoadUnit;
use App\Models\Concerns\Syncable;
use Database\Factories\SessionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'user_id',
    'workout_session_id',
    'exercise_id',
    'position',
    'planned_sets',
    'rep_min',
    'rep_max',
    'rest_seconds',
    'notes',
])]
class SessionItem extends Model
{
    /** @use HasFactory<SessionItemFactory> */
    use HasFactory, HasUlids, SoftDeletes, Syncable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'planned_sets' => 'integer',
            'rep_min' => 'integer',
            'rep_max' => 'integer',
            'rest_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkoutSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * @return HasMany<SessionSet, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(SessionSet::class)->orderBy('set_number')->orderBy('part');
    }

    /**
     * A set made of several segments (drop set, cluster, combined) still
     * counts as one set, so the count is the highest set number reached.
     */
    public function setsCount(): int
    {
        return (int) $this->sets()->max('set_number');
    }

    /**
     * The sets recorded the last time this exercise was performed, whatever
     * template it was performed in, leaving the current session out.
     *
     * @return Collection<int, SessionSet>
     */
    public function lastPerformance(): Collection
    {
        $previous = static::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'session_items.workout_session_id')
            ->where('session_items.exercise_id', $this->exercise_id)
            ->where('session_items.user_id', $this->user_id)
            ->where('session_items.workout_session_id', '!=', $this->workout_session_id)
            ->whereNull('workout_sessions.deleted_at')
            ->orderByDesc('workout_sessions.performed_on')
            ->orderByDesc('workout_sessions.created_at')
            ->select('session_items.*')
            ->first();

        if ($previous === null) {
            return collect();
        }

        return $previous->sets
            ->where('is_warmup', false)
            ->values();
    }

    /**
     * How the best set of this workout compares with the best set of the last
     * one: 1 above, 0 the same, -1 below, null when there is nothing to
     * compare or the units do not match.
     */
    public function trend(): ?int
    {
        return self::compare($this->bestSet(), $this->lastPerformance()->pipe(
            fn (Collection $sets) => $sets->filter(fn (SessionSet $set): bool => $set->load !== null)
                ->sortByDesc(fn (SessionSet $set): array => [(float) $set->load, $set->reps ?? 0])
                ->first(),
        ));
    }

    /**
     * The difference in load and reps between the best set here and the best
     * set of another item: null when there is nothing to compare or the units
     * do not match.
     *
     * @return array{load: float, reps: int}|null
     */
    public function deltaAgainst(?self $other): ?array
    {
        $current = $this->bestSet();
        $previous = $other?->bestSet();

        if ($current === null || $previous === null || $current->unit !== $previous->unit) {
            return null;
        }

        return [
            'load' => round((float) $current->load - (float) $previous->load, 2),
            'reps' => ($current->reps ?? 0) - ($previous->reps ?? 0),
        ];
    }

    /**
     * Volume of the working sets, in the item's own unit: load times reps,
     * converted to kilograms when the exercise says how much a plate weighs.
     *
     * @return array{value: float, unit: string}|null
     */
    public function volume(): ?array
    {
        $sets = $this->sets
            ->where('is_warmup', false)
            ->filter(fn (SessionSet $set): bool => $set->load !== null && $set->reps !== null);

        if ($sets->isEmpty()) {
            return null;
        }

        $unit = $sets->first()->unit;

        if ($sets->contains(fn (SessionSet $set): bool => $set->unit !== $unit)) {
            return null;
        }

        $value = $sets->sum(fn (SessionSet $set): float => (float) $set->load * $set->reps);

        if ($unit === LoadUnit::Kilograms) {
            return ['value' => round($value, 2), 'unit' => 'kg'];
        }

        $perPlate = $this->exercise?->kg_per_plate;

        if ($unit === LoadUnit::Plates && $perPlate !== null) {
            return ['value' => round($value * (float) $perPlate, 2), 'unit' => 'kg'];
        }

        return ['value' => round($value, 2), 'unit' => $unit->label()];
    }

    public function bestSet(): ?SessionSet
    {
        return $this->sets
            ->where('is_warmup', false)
            ->filter(fn (SessionSet $set): bool => $set->load !== null)
            ->sortByDesc(fn (SessionSet $set): array => [(float) $set->load, $set->reps ?? 0])
            ->first();
    }

    private static function compare(?SessionSet $current, ?SessionSet $previous): ?int
    {
        if ($current === null || $previous === null || $current->unit !== $previous->unit) {
            return null;
        }

        return [(float) $current->load, $current->reps ?? 0] <=> [(float) $previous->load, $previous->reps ?? 0];
    }
}
