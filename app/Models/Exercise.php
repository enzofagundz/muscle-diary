<?php

namespace App\Models;

use App\Enums\LoadUnit;
use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'based_on_id', 'name', 'muscle_group', 'unit_default', 'kg_per_plate', 'notes'])]
class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_default' => LoadUnit::class,
            'kg_per_plate' => 'decimal:2',
        ];
    }

    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Exercises a user may pick from: their own, plus the shared catalog
     * minus the ones they already replaced with a copy of their own.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $replacedCatalogIds = static::query()
            ->where('user_id', $user->id)
            ->whereNotNull('based_on_id')
            ->select('based_on_id');

        $query->where(function (Builder $query) use ($user, $replacedCatalogIds): void {
            $query->where('user_id', $user->id)
                ->orWhere(function (Builder $query) use ($replacedCatalogIds): void {
                    $query->whereNull('user_id')->whereNotIn('id', $replacedCatalogIds);
                });
        });
    }
}
