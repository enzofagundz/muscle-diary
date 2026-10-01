<?php

namespace App\Support;

use Native\Mobile\Facades\Network;
use Throwable;

/**
 * Whether the device has a connection. Outside the native app there is no
 * device to ask, so the answer is a plain yes.
 */
class NativeNetwork
{
    public static function isConnected(): bool
    {
        if (! class_exists(Network::class)) {
            return true;
        }

        try {
            return (bool) Network::status()->connected;
        } catch (Throwable) {
            return true;
        }
    }
}
