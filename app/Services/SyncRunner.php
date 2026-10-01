<?php

namespace App\Services;

use App\Models\SyncSetting;
use App\Support\NativeNetwork;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One full round trip with the server: send what is pending, mark it as sent,
 * then take back whatever changed since the last time. Never throws — a
 * trigger running in the background must not be able to break the screen the
 * user is on.
 */
class SyncRunner
{
    public function isConnected(): bool
    {
        return SyncSetting::current()->isConnected();
    }

    /**
     * Schedule the round trip for after the response, so the screen never
     * waits on the network. Returns whether the work was scheduled.
     */
    public function runInBackground(): bool
    {
        if (! $this->isConnected() || ! NativeNetwork::isConnected()) {
            return false;
        }

        defer(fn () => $this->run());

        return true;
    }

    public function run(): bool
    {
        $settings = SyncSetting::current();

        if (! $settings->isConnected()) {
            return false;
        }

        try {
            $sync = new SyncService;
            $payload = $sync->pending();

            $push = Http::withToken($settings->token)
                ->acceptJson()
                ->timeout(15)
                ->post($settings->server_url.'/api/sync/push', ['rows' => $payload]);

            if ($push->failed()) {
                Log::warning('Sync push falhou', ['status' => $push->status()]);

                return false;
            }

            $sync->markSynced($payload);

            $pull = Http::withToken($settings->token)
                ->acceptJson()
                ->timeout(15)
                ->get($settings->server_url.'/api/sync/pull', [
                    'since' => $settings->last_synced_at?->toIso8601String(),
                ]);

            if ($pull->failed()) {
                Log::warning('Sync pull falhou', ['status' => $pull->status()]);

                return false;
            }

            $sync->apply($pull->json('rows', []));

            $settings->update(['last_synced_at' => now()]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Sync falhou', ['erro' => $exception->getMessage()]);

            return false;
        }
    }
}
