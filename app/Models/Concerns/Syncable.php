<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Marks a row as pending whenever it is saved, so the sync never has to guess
 * from timestamps. Timestamps only have second precision, and a change made in
 * the same second as the last sync would otherwise be missed.
 *
 * Clearing the mark happens through a query builder update, which does not
 * fire model events, so marking and clearing never fight each other.
 */
trait Syncable
{
    public static function bootSyncable(): void
    {
        static::saving(function (Model $model): void {
            if (! $model->isDirty('synced_at')) {
                $model->synced_at = null;
            }
        });

        // A soft delete writes `deleted_at` straight to the database, so the
        // mark has to be cleared there too, or the deletion would never travel.
        static::deleting(function (Model $model): void {
            if ($model->synced_at === null) {
                return;
            }

            $model->newModelQuery()
                ->whereKey($model->getKey())
                ->update(['synced_at' => null]);

            $model->synced_at = null;
        });
    }
}
