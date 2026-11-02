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

namespace Bedriox\NetherNet\Transport;

enum TransportState
{
    case CONNECTING;
    case CONNECTED;
    /**
     * Connectivity was lost and may still come back.
     */
    case DISCONNECTED;
    case FAILED;
    case CLOSED;

    public function isBroken(): bool
    {
        return $this === self::FAILED || $this === self::CLOSED;
    }
}
