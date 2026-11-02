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

namespace Bedriox\NetherNet;

use Bedriox\NetherNet\Logging\Logger;
use Bedriox\NetherNet\Negotiation\Negotiator;
use Bedriox\NetherNet\Signaling\SignalingContext;
use Bedriox\NetherNet\Utils\AddressBlockTracker;

final class TestSignalingContext
{
    private function __construct()
    {
        //NOOP
    }

    /**
     * Builds what a server would hand a transport at startup, for tests that start one on its own.
     */
    public static function create(Negotiator $negotiator, ?Logger $logger = null): SignalingContext
    {
        return new SignalingContext($negotiator, new AddressBlockTracker(), $logger ?? new RecordingLogger());
    }
}
