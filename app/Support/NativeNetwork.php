<?php

namespace App\Support;

use Native\Mobile\Facades\Network;
use Throwable;

/**
 * Whether the device has a connection right now. Outside the native app there
 * is no device to ask, so the answer is a plain yes and the sync goes ahead.
 *
 * NativePHP only exposes the current status, not a "network came back" event,
 * so the sync asks this at the moments it already triggers on.
 */
class NativeNetwork
{
    public static function isConnected(): bool
    {
        if (! class_exists(Network::class)) {
            return true;
        }

        try {
            $status = Network::status();
        } catch (Throwable) {
            return true;
        }

        return (bool) ($status->connected ?? true);
    }
}
