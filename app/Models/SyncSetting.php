<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['server_url', 'token', 'last_synced_at'])]
class SyncSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function isConnected(): bool
    {
        return $this->server_url !== null && $this->token !== null;
    }
}
