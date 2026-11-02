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

namespace Bedriox\NetherNet\Transport;

use Bedriox\NetherNet\Protocol\Reliability;

final class ChannelPair
{
    public function __construct(
        public readonly MessageChannel $reliable,
        public readonly MessageChannel $unreliable
    ) {}

    public function get(Reliability $reliability): MessageChannel
    {
        return match ($reliability) {
            Reliability::RELIABLE => $this->reliable,
            Reliability::UNRELIABLE => $this->unreliable
        };
    }

    /**
     * @return MessageChannel[]
     * @phpstan-return list<MessageChannel>
     */
    public function all(): array
    {
        return [$this->reliable, $this->unreliable];
    }
}
