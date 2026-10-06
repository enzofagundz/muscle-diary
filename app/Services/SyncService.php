<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\TemplateItem;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Moves rows between the device database and the server database.
 *
 * The same class runs on both sides. On the device it reads the rows still
 * pending and marks them as sent; on the server it accepts those rows and
 * answers with whatever changed since the last pull. Conflicts are settled by
 * `updated_at`, newest wins, and a row that is still pending on the device is
 * never overwritten by a pull.
 */
class SyncService
{
    /**
     * Tables that travel between the two sides, in dependency order.
     *
     * @var list<string>
     */
    public const TABLES = [
        'exercises',
        'workout_templates',
        'template_items',
        'workout_sessions',
        'session_items',
        'session_sets',
    ];

    /**
     * Columns carried across, per table.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'exercises' => [
            'id', 'user_id', 'based_on_id', 'name', 'muscle_group', 'unit_default', 'kg_per_plate',
            'notes', 'created_at', 'updated_at', 'deleted_at',
        ],
        'workout_templates' => [
            'id', 'user_id', 'name', 'rest_seconds', 'notes', 'position', 'is_active',
            'created_at', 'updated_at', 'deleted_at',
        ],
        'template_items' => [
            'id', 'user_id', 'workout_template_id', 'exercise_id', 'position', 'sets', 'rep_min',
            'rep_max', 'rest_seconds', 'notes', 'created_at', 'updated_at', 'deleted_at',
        ],
        'workout_sessions' => [
            'id', 'user_id', 'workout_template_id', 'name', 'performed_on', 'location',
            'rest_seconds', 'notes', 'finished_at', 'created_at', 'updated_at', 'deleted_at',
        ],
        'session_items' => [
            'id', 'user_id', 'workout_session_id', 'exercise_id', 'position', 'planned_sets',
            'rep_min', 'rep_max', 'rest_seconds', 'notes', 'created_at', 'updated_at', 'deleted_at',
        ],
        'session_sets' => [
            'id', 'user_id', 'session_item_id', 'set_number', 'part', 'load', 'unit', 'reps',
            'is_warmup', 'notes', 'created_at', 'updated_at', 'deleted_at',
        ],
    ];

    public function __construct(private readonly ?string $connection = null) {}

    /**
     * Rows waiting to be sent: never synced, or touched after the last sync.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function pending(): array
    {
        $payload = [];

        foreach (self::TABLES as $table) {
            $rows = $this->query($table)
                ->when($table === 'exercises', fn (Builder $query) => $query->whereNotNull('user_id'))
                ->whereNull('synced_at')
                ->get()
                ->map(fn (Model $row): array => $this->export($row))
                ->all();

            if ($rows !== []) {
                $payload[$table] = $rows;
            }
        }

        return $payload;
    }

    /**
     * Accept a batch coming from the other side. The newest `updated_at` wins,
     * so an older copy never overwrites a newer one.
     *
     * @param  array<string, list<array<string, mixed>>>  $payload
     * @return array<string, int>
     */
    public function push(array $payload, User $user): array
    {
        $written = [];

        foreach (self::TABLES as $table) {
            foreach ($payload[$table] ?? [] as $row) {
                $id = $row['id'] ?? null;

                if ($id === null) {
                    continue;
                }

                if ($table === 'exercises' && ($row['user_id'] ?? null) === null) {
                    // The shared catalog belongs to the server: a device never
                    // gets to rewrite it.
                    continue;
                }

                $row['user_id'] = $user->id;
                $existing = $this->query($table)->whereKey($id)->first();

                // Only a strictly newer copy loses; equal timestamps let the
                // arriving row win, because timestamps here have second
                // precision and a change made in the same second as the
                // previous one would otherwise never travel.
                if ($existing !== null && $existing->updated_at > $this->moment($row['updated_at'] ?? null)) {
                    continue;
                }

                $this->write($table, $id, $row);

                $written[$table] = ($written[$table] ?? 0) + 1;
            }
        }

        return $written;
    }

    /**
     * Everything that changed on this side since the given moment.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function pull(?string $since, User $user): array
    {
        $moment = $since === null ? null : Carbon::parse($since);
        $payload = [];

        foreach (self::TABLES as $table) {
            $rows = $this->query($table)
                ->when(
                    $table === 'exercises',
                    fn (Builder $query) => $query->where(fn (Builder $query) => $query
                        ->whereNull('user_id')
                        ->orWhere('user_id', $user->id)),
                    fn (Builder $query) => $query->where('user_id', $user->id),
                )
                // `>=` and not `>`: timestamps only have second precision, so a
                // row changed in the same second as the last pull would
                // otherwise never come back. Re-sending it is harmless, because
                // applying only overwrites what is not newer.
                ->when($moment !== null, fn (Builder $query) => $query->where('updated_at', '>=', $moment))
                ->get()
                ->map(fn (Model $row): array => $this->export($row))
                ->all();

            if ($rows !== []) {
                $payload[$table] = $rows;
            }
        }

        return $payload;
    }

    /**
     * Write rows received from the other side into this database, keeping any
     * local row that has not been sent yet: a pending change must never be lost
     * to an older copy arriving from the server.
     *
     * @param  array<string, list<array<string, mixed>>>  $payload
     */
    public function apply(array $payload): void
    {
        foreach (self::TABLES as $table) {
            foreach ($payload[$table] ?? [] as $row) {
                $id = $row['id'] ?? null;

                if ($id === null) {
                    continue;
                }

                $existing = $this->query($table)->whereKey($id)->first();

                if ($existing !== null && $this->isPending($existing)) {
                    continue;
                }

                if ($existing !== null && $existing->updated_at > $this->moment($row['updated_at'] ?? null)) {
                    continue;
                }

                $this->write($table, $id, $row);
            }
        }
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $payload
     */
    public function markSynced(array $payload): void
    {
        foreach (self::TABLES as $table) {
            $ids = array_column($payload[$table] ?? [], 'id');

            if ($ids === []) {
                continue;
            }

            $this->query($table)->whereIn('id', $ids)->update(['synced_at' => now()]);
        }
    }

    /**
     * Mark every row as pending again, so the next round trip sends the
     * whole history. A query builder update fires no model events, so the
     * trait that marks rows on save never fights it.
     */
    public function markEverythingPending(): void
    {
        foreach (self::TABLES as $table) {
            $this->query($table)->update(['synced_at' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function write(string $table, string $id, array $row): void
    {
        $attributes = array_intersect_key($row, array_flip(self::COLUMNS[$table]));
        $attributes['id'] = $id;
        $attributes['synced_at'] = now();

        $this->query($table)->updateOrInsert(['id' => $id], $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function export(Model $row): array
    {
        return array_intersect_key($row->getAttributes(), array_flip(self::COLUMNS[$row->getTable()]));
    }

    private function isPending(Model $row): bool
    {
        return $row->synced_at === null;
    }

    private function moment(?string $value): Carbon
    {
        return $value === null ? Carbon::createFromTimestamp(0) : Carbon::parse($value);
    }

    /**
     * @return Builder<Model>
     */
    private function query(string $table): Builder
    {
        $class = match ($table) {
            'exercises' => Exercise::class,
            'workout_templates' => WorkoutTemplate::class,
            'template_items' => TemplateItem::class,
            'workout_sessions' => WorkoutSession::class,
            'session_items' => SessionItem::class,
            'session_sets' => SessionSet::class,
        };

        return $class::on($this->connection)->withTrashed();
    }
}
