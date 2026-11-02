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

namespace Bedriox\NetherNet\Crypto;

use function base64_decode;
use function base64_encode;
use function rtrim;
use function str_repeat;
use function strlen;
use function strtr;

/**
 * @internal
 */
final class Base64Url
{
    private function __construct() {}

    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * @throws CryptoException
     */
    public static function decode(string $data): string
    {
        $remainder = \strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat("=", 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, "-_", "+/"), true);
        if ($decoded === false) {
            throw new CryptoException("Value is not valid base64url");
        }

        return $decoded;
    }
}
