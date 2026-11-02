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

use PHPUnit\Framework\TestCase;

final class AddressBlockTrackerTest extends TestCase
{
    public function testBlockLastsUntilItsExpiry(): void
    {
        $tracker = new AddressBlockTracker();
        $tracker->update(100.0);
        $tracker->block("192.0.2.1", 110.0);

        self::assertSame(1, $tracker->count());
        self::assertTrue($tracker->isBlocked("192.0.2.1"));

        $tracker->update(109.9);
        self::assertTrue($tracker->isBlocked("192.0.2.1"));

        $tracker->update(110.0);
        self::assertFalse($tracker->isBlocked("192.0.2.1"));
        self::assertSame(0, $tracker->count());
    }

    public function testBlockWithoutExpiryLastsUntilUnblocked(): void
    {
        $tracker = new AddressBlockTracker();
        $tracker->block("192.0.2.1");

        $tracker->update(1.0e12);
        self::assertTrue($tracker->isBlocked("192.0.2.1"));

        $tracker->unblock("192.0.2.1");
        self::assertFalse($tracker->isBlocked("192.0.2.1"));
    }

    /**
     * A shorter block arriving later must not cut an existing longer one short.
     */
    public function testShorterBlockDoesNotReplaceALongerOne(): void
    {
        $tracker = new AddressBlockTracker();
        $tracker->block("192.0.2.1", 500.0);
        $tracker->block("192.0.2.1", 50.0);

        $tracker->update(100.0);
        self::assertTrue($tracker->isBlocked("192.0.2.1"));
    }

    public function testExpiryOfOneBlockLeavesLaterOnesInPlace(): void
    {
        $tracker = new AddressBlockTracker();
        $tracker->block("192.0.2.1", 10.0);
        $tracker->block("192.0.2.2", 20.0);

        $tracker->update(15.0);
        self::assertFalse($tracker->isBlocked("192.0.2.1"));
        self::assertTrue($tracker->isBlocked("192.0.2.2"));

        $tracker->update(20.0);
        self::assertFalse($tracker->isBlocked("192.0.2.2"));
    }

    /**
     * The same IPv6 address can be written many ways, and a block has to hold for all of them.
     */
    public function testEquivalentSpellingsOfAnAddressShareABlock(): void
    {
        $tracker = new AddressBlockTracker();
        $tracker->block("2001:db8:0:0:0:0:0:1");

        self::assertTrue($tracker->isBlocked("2001:db8::1"));
        self::assertTrue($tracker->isBlocked("[2001:DB8::1]"));

        $tracker->unblock("2001:db8::1");
        self::assertFalse($tracker->isBlocked("2001:db8:0:0:0:0:0:1"));
    }
}
