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

namespace Bedriox\NetherNet\Protocol;

enum Reliability
{
    /**
     * Ordered, lossless SCTP data channel with fragmentation support for game data.
     */
    case RELIABLE;

    /**
     * Unordered, loss-tolerant SCTP data channel (maxRetransmits=0, no fragmentation) for latency-sensitive data.
     */
    case UNRELIABLE;

    public function getChannelLabel(): string
    {
        return match ($this) {
            self::RELIABLE => "ReliableDataChannel",
            self::UNRELIABLE => "UnreliableDataChannel"
        };
    }

    public function isFragmentationSupported(): bool
    {
        return $this === self::RELIABLE;
    }

    public static function fromChannelLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getChannelLabel() === $label) {
                return $case;
            }
        }

        return null;
    }
}
