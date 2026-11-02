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

final class InternetAddressTest extends TestCase
{
    public function testParsesUnbracketedIpv6(): void
    {
        $address = InternetAddress::parse("2001:db8::1:5000");

        self::assertNotNull($address);
        self::assertSame("2001:db8::1", $address->ip);
        self::assertSame(5000, $address->port);
        self::assertSame(6, $address->version);
        self::assertSame("[2001:db8::1]:5000", $address->toString());
    }

    public function testParsesBracketedIpv6(): void
    {
        $address = InternetAddress::parse("[::1]:5000");

        self::assertNotNull($address);
        self::assertSame("::1", $address->ip);
        self::assertSame(5000, $address->port);
    }

    public function testRejectsMalformedAddresses(): void
    {
        self::assertNull(InternetAddress::parse("192.0.2.10"));
        self::assertNull(InternetAddress::parse("example.test:80"));
        self::assertNull(InternetAddress::parse("192.0.2.10:70000"));
        self::assertNull(InternetAddress::parse("192.0.2.10:"));
    }
}
