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

namespace Bedriox\NetherNet\Signaling\Lan\Packet;

use Bedriox\NetherNet\Protocol\BinaryWriter;

abstract class Packet
{
    /**
     * @param int $senderId 64-bit unsigned NetworkID of the host the packet comes from.
     */
    public function __construct(
        public readonly int $senderId
    ) {}

    abstract public function getType(): PacketType;

    abstract public function encodeBody(BinaryWriter $out): void;
}
