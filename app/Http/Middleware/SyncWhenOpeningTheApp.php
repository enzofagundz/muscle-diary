<?php

namespace App\Http\Middleware;

use App\Services\SyncRunner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Syncs in the background when the app is opened, at most once every few
 * minutes. The other moments — finishing a workout, for instance — call the
 * runner directly from the action that knows about them.
 *
 * The work is deferred until after the response, so the screen never waits on
 * the network, and the runner swallows failures so a dead server cannot break
 * the page the user is on.
 */
class SyncWhenOpeningTheApp
{
    private const OPEN_COOLDOWN_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isOpeningTheApp($request)) {
            app(SyncRunner::class)->runInBackground();
        }

        return $next($request);
    }

    private function isOpeningTheApp(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->ajax()
            && Cache::add('sync-on-open', true, now()->addMinutes(self::OPEN_COOLDOWN_MINUTES));
    }
}
