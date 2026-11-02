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

final class RequestPacket extends Packet
{
    public function getType(): PacketType
    {
        return PacketType::REQUEST;
    }

    public function encodeBody(BinaryWriter $out): void {}
}
