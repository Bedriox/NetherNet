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

namespace Bedriox\NetherNet\Sdp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FingerprintTest extends TestCase
{
    /**
     * Both peers rebuild these bytes independently and one signs them, so a single
     * byte of difference makes every assertion fail with no useful diagnostic.
     * This pins the exact form rather than trusting a JSON encoder to keep the key
     * order and spacing it happens to produce today.
     */
    public function testCanonicalPayloadIsByteExact(): void
    {
        $fingerprint = new Fingerprint("sha-256", "00:11:22:33:44:55:66:77");

        self::assertSame(
            '{"fingerprint":[{"algorithm":"sha-256","digest":"00:11:22:33:44:55:66:77"}]}',
            $fingerprint->toCanonicalPayload()
        );
    }

    #[DataProvider('malformedValueProvider')]
    public function testMalformedValuesAreRefused(string $value): void
    {
        $this->expectException(SdpException::class);
        Fingerprint::parse($value);
    }

    /**
     * @return string[][]
     * @phpstan-return array<string, array{string}>
     */
    public static function malformedValueProvider(): array
    {
        return [
            "no digest" => ["sha-256"],
            "trailing junk" => ["sha-256 AA:BB extra"],
            "digest is not hex" => ["sha-256 zz:zz"],
            "digest without separators" => ["sha-256 AABB"],
            "digest with odd nibble" => ["sha-256 AA:B"],
            "empty" => [""],
        ];
    }

    /**
     * A value needing JSON escaping could never round-trip byte-identically
     * against the peer's copy, so it is refused at construction rather than
     * producing an assertion that silently never verifies.
     */
    public function testValueRequiringEscapingIsRefused(): void
    {
        $this->expectException(SdpException::class);
        new Fingerprint("sha-256\"", "AA:BB");
    }
}
