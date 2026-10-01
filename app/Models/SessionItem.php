<?php

namespace App\Models;

use Database\Factories\SessionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    use HasFactory, HasUlids, SoftDeletes;

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
}
