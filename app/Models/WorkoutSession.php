<?php

namespace App\Models;

use Database\Factories\WorkoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    use HasFactory, HasUlids, SoftDeletes;

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
}
