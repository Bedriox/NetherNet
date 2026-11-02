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

namespace Bedriox\NetherNet\Session;

use Bedriox\NetherNet\Protocol\Reliability;

/**
 * @internal
 */
final class ReceivedMessage
{
    public function __construct(
        public readonly Reliability $reliability,
        public readonly string $payload
    ) {}
}
