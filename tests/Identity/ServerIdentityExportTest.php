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

namespace Bedriox\NetherNet\Identity;

use function min;
use function openssl_pkey_get_details;
use function openssl_pkey_get_private;

use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * The key file this writes is the one clients pin the host by, so it has to be a
 * PEM other tools can read, and it has to survive a restart unchanged.
 *
 * It is written by hand rather than with openssl_pkey_export(), because that is
 * the only OpenSSL call in this library that insists on a configuration file and
 * stock PHP on Windows has none. These tests are what keeps the hand-written
 * encoding honest.
 */
final class ServerIdentityExportTest extends TestCase
{
    /** @return array{key: string, ec: array{d: string, x: string, y: string}} */
    private static function keyDetails(\OpenSSLAsymmetricKey $key): array
    {
        $details = openssl_pkey_get_details($key);
        if (!\is_array($details) || !\is_string($details["key"] ?? null)) {
            throw new \RuntimeException("OpenSSL did not return public key details");
        }
        $ec = $details["ec"] ?? null;
        if (!\is_array($ec) || !\is_string($ec["d"] ?? null) || !\is_string($ec["x"] ?? null) || !\is_string($ec["y"] ?? null)) {
            throw new \RuntimeException("OpenSSL did not return complete EC key details");
        }

        return ["key" => $details["key"], "ec" => ["d" => $ec["d"], "x" => $ec["x"], "y" => $ec["y"]]];
    }

    /** If OpenSSL cannot read it back, no other tool will either. */
    public function testOpenSslReadsBackWhatWeWrote(): void
    {
        $identity = ServerIdentity::generate();
        $reloaded = openssl_pkey_get_private($identity->exportPrivateKeyPem());

        self::assertNotFalse($reloaded);

        $original = self::keyDetails($identity->getPrivateKey());
        $parsed = self::keyDetails($reloaded);
        self::assertSame($original["ec"]["d"], $parsed["ec"]["d"]);
        self::assertSame($original["key"], $parsed["key"]);
    }

    /**
     * OpenSSL strips leading zero bytes from curve components, so roughly one key
     * in 256 has a scalar or coordinate that is a byte short. Writing it short
     * produces a PEM that still parses but describes a different key, and the
     * failure only appears for the unlucky operator whose key happened to be one
     * of those. Keys are drawn until one of them turns up.
     */
    public function testKeyWithAShortComponentStillRoundTrips(): void
    {
        for ($i = 0; $i < 5000; ++$i) {
            $identity = ServerIdentity::generate();
            $details = self::keyDetails($identity->getPrivateKey());
            if (min(\strlen($details["ec"]["d"]), \strlen($details["ec"]["x"]), \strlen($details["ec"]["y"])) >= 48) {
                continue;
            }

            $pem = $identity->exportPrivateKeyPem();
            self::assertNotFalse(openssl_pkey_get_private($pem), "the key could not be read back");
            self::assertSame(
                $identity->getPublicKey()->getDigest(),
                ServerIdentity::fromPrivateKeyPem($pem)->getPublicKey()->getDigest(),
                "the key changed identity across the round trip"
            );

            return;
        }

        self::fail("no key with a short component appeared, so this test proved nothing");
    }
}
