<?php

namespace App\Services;

use App\Models\SessionItem;
use App\Models\SessionSet;
use App\Models\User;
use App\Models\WorkoutSession;
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
                ->where(function (Builder $query): void {
                    $query->whereNull('synced_at')
                        ->orWhereColumn('updated_at', '>', 'synced_at');
                })
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

                $row['user_id'] = $user->id;
                $existing = $this->query($table)->whereKey($id)->first();

                if ($existing !== null && $existing->updated_at >= $this->moment($row['updated_at'] ?? null)) {
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
                ->where('user_id', $user->id)
                ->when($moment !== null, fn (Builder $query) => $query->where('updated_at', '>', $moment))
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

                if ($existing !== null && $existing->updated_at >= $this->moment($row['updated_at'] ?? null)) {
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
        return $row->synced_at === null || $row->updated_at > $row->synced_at;
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
            'workout_sessions' => WorkoutSession::class,
            'session_items' => SessionItem::class,
            'session_sets' => SessionSet::class,
        };

        return $class::on($this->connection)->withTrashed();
    }
}
