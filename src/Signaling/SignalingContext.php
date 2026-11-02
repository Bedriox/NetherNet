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

namespace Bedriox\NetherNet\Signaling;

use Bedriox\NetherNet\Logging\Logger;
use Bedriox\NetherNet\Negotiation\Negotiator;
use Bedriox\NetherNet\Utils\AddressBlockList;

final class SignalingContext
{
    public function __construct(
        public readonly Negotiator $negotiator,
        public readonly AddressBlockList $blockList,
        public readonly Logger $logger
    ) {}
}
