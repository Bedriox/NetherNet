<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 *
 * Website: https://bedriox.com
 * Source: https://github.com/Bedriox/NetherNet
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Client;

use Bedriox\NetherNet\Protocol\Reliability;

final readonly class ClientPayload
{
    public function __construct(public string $payload, public Reliability $reliability)
    {
        if ($payload === '') {
            throw new \InvalidArgumentException('NetherNet client payload must not be empty.');
        }
    }
}
