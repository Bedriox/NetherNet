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

namespace Bedriox\NetherNet\Negotiation;

/**
 * @internal
 */
enum NegotiationState
{
    case GATHERING;

    /**
     * SDP answer generated, waiting for remote peer to open required SCTP data channels.
     */
    case ANSWERED;
    case ESTABLISHED;
    case FAILED;

    public function isFinished(): bool
    {
        return $this === self::ESTABLISHED || $this === self::FAILED;
    }
}
