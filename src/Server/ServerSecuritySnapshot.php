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

namespace Bedriox\NetherNet\Server;

final readonly class ServerSecuritySnapshot
{
    public function __construct(
        public int $activeSessions,
        public int $maximumSessions,
        public int $activeNegotiations,
        public int $activeAddressBlocks,
        public int $negotiationFloodBlocks,
        public int $sessionLimitRejections,
        public int $blockedPeerRejections,
    ) {}
}
