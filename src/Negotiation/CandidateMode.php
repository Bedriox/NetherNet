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

enum CandidateMode
{
    /**
     * Full ICE - all ICE candidates are gathered before the SDP is sent (trickle ICE disabled, used in HTTP signaling).
     */
    case BUNDLED;

    /**
     * Trickle ICE - candidates are gathered and transmitted incrementally in parallel (used in LAN signaling).
     */
    case TRICKLE;
}
