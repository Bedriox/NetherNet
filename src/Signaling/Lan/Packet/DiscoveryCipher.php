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

use function hash;
use function hash_equals;
use function hash_hmac;
use function openssl_decrypt;
use function openssl_encrypt;

use const OPENSSL_RAW_DATA;

use function pack;
use function strlen;
use function substr;

final class DiscoveryCipher
{
    /**
     * Application ID used to derive the discovery cipher key.
     */
    public const APPLICATION_ID = 0xdeadbeef;

    public const CHECKSUM_SIZE = 32;
    private const BLOCK_SIZE = 16;
    private static ?string $key = null;

    private function __construct() {}

    public static function key(): string
    {
        if (self::$key === null) {
            self::$key = hash("sha256", pack("P", self::APPLICATION_ID), true);
        }

        return self::$key;
    }

    public static function seal(string $payload): string
    {
        $key = self::key();

        $ciphertext = openssl_encrypt($payload, "aes-256-ecb", $key, OPENSSL_RAW_DATA, "");
        if ($ciphertext === false) {
            throw new DiscoveryException("Could not encrypt the datagram");
        }

        return hash_hmac("sha256", $payload, $key, true) . $ciphertext;
    }

    /**
     * @throws DiscoveryException
     */
    public static function open(string $frame): string
    {
        if (\strlen($frame) < self::CHECKSUM_SIZE + self::BLOCK_SIZE) {
            throw new DiscoveryException("Datagram is shorter than a checksum and one block");
        }

        $ciphertext = substr($frame, self::CHECKSUM_SIZE);
        if (\strlen($ciphertext) % self::BLOCK_SIZE !== 0) {
            throw new DiscoveryException("Ciphertext length must be a multiple of the block size (16 bytes)");
        }

        $key = self::key();
        $payload = openssl_decrypt($ciphertext, "aes-256-ecb", $key, OPENSSL_RAW_DATA, "");
        if ($payload === false) {
            throw new DiscoveryException("Could not decrypt the datagram");
        }

        if (!hash_equals(hash_hmac("sha256", $payload, $key, true), substr($frame, 0, self::CHECKSUM_SIZE))) {
            throw new DiscoveryException("Datagram checksum does not match");
        }

        return $payload;
    }
}
