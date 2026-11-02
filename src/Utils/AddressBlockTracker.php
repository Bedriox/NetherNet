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

namespace Bedriox\NetherNet\Utils;

use const INF;

/**
 * @internal
 */
final class AddressBlockTracker implements AddressBlockList
{
    /** @phpstan-var array<string, float> */
    private array $blocked = [];

    private float $now = 0.0;
    private float $nextExpiry = INF;

    /**
     * @param float $until Timestamp at which the block expires, or INF to block until {@link AddressBlockTracker::unblock()}.
     */
    public function block(string $address, float $until = INF): void
    {
        $address = self::key($address);
        if (($this->blocked[$address] ?? 0.0) >= $until) {
            return;
        }

        $this->blocked[$address] = $until;
        if ($until < $this->nextExpiry) {
            $this->nextExpiry = $until;
        }
    }

    public function unblock(string $address): void
    {
        unset($this->blocked[self::key($address)]);
    }

    public function isBlocked(string $address): bool
    {
        $until = $this->blocked[self::key($address)] ?? null;

        return $until !== null && $until > $this->now;
    }

    public function update(float $now): void
    {
        $this->now = $now;
        if ($now < $this->nextExpiry) {
            return;
        }

        $this->nextExpiry = INF;
        foreach ($this->blocked as $address => $until) {
            if ($until <= $now) {
                unset($this->blocked[$address]);
            } elseif ($until < $this->nextExpiry) {
                $this->nextExpiry = $until;
            }
        }
    }

    public function count(): int
    {
        return \count($this->blocked);
    }

    /** Equivalent spellings of one address (IPv6 zero compression, brackets) must share a single entry. */
    private static function key(string $address): string
    {
        return InternetAddress::normalizeIp($address) ?? $address;
    }
}
