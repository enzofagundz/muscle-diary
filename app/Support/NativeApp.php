<?php

namespace App\Support;

/**
 * Whether the app is running inside the native shell rather than in a browser.
 * The same screens serve both, and only a few behaviours differ: the mobile
 * chrome, the safe area, and opening straight into a workout in progress.
 *
 * Read from config, never from `env()`, so a cached config keeps working.
 */
class NativeApp
{
    public static function isRunning(): bool
    {
        return (bool) config('nativephp-internal.running', false);
    }
}
