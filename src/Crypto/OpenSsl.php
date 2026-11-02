<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Axolotl Team (original work)
 * Copyright (C) 2026 Veno Ninja LLC (Bedriox modifications)
 *
 * Original work: https://github.com/axolotl-pm/NetherNet
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Crypto;

use function openssl_error_string;

/**
 * @internal
 */
final class OpenSsl
{
    private function __construct()
    {
        //NOOP
    }

    public static function lastError(): string
    {
        $message = null;
        while (($error = openssl_error_string()) !== false) {
            $message = $error;
        }

        return $message ?? "no detail available";
    }
}
