<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Database\Factories\TemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'workout_template_id',
    'exercise_id',
    'position',
    'sets',
    'rep_min',
    'rep_max',
    'rest_seconds',
    'notes',
])]
class TemplateItem extends Model
{
    /** @use HasFactory<TemplateItemFactory> */
    use HasFactory, HasUlids, SoftDeletes, Syncable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'sets' => 'integer',
            'rep_min' => 'integer',
            'rep_max' => 'integer',
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
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
