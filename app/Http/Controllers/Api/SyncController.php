<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private readonly SyncService $sync) {}

    public function push(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*' => ['array'],
        ])['rows'];

        return response()->json([
            'written' => $this->sync->push($payload, $request->user()),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $since = $request->query('since');

        return response()->json([
            'rows' => $this->sync->pull(is_string($since) ? $since : null, $request->user()),
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
