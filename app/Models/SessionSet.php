<?php

namespace App\Models;

use App\Enums\LoadUnit;
use App\Models\Concerns\Syncable;
use Database\Factories\SessionSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'session_item_id',
    'set_number',
    'part',
    'load',
    'unit',
    'reps',
    'is_warmup',
    'notes',
])]
class SessionSet extends Model
{
    /** @use HasFactory<SessionSetFactory> */
    use HasFactory, HasUlids, SoftDeletes, Syncable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'part' => 'integer',
            'load' => 'float',
            'unit' => LoadUnit::class,
            'reps' => 'integer',
            'is_warmup' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SessionItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SessionItem::class, 'session_item_id');
    }
}
