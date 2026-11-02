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

enum SignalingState
{
    case CREATED;
    case STARTED;
    case SHUT_DOWN;

    /**
     * @throws \LogicException
     */
    public function checkCanStart(): void
    {
        if ($this !== self::CREATED) {
            throw new \LogicException($this === self::STARTED ? "Signaling transport is already started" : "Signaling transport has been shut down and cannot be restarted");
        }
    }
}
