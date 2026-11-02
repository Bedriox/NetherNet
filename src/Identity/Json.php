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

use function array_is_list;
use function is_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function ucfirst;

/**
 * @internal
 */
final class Json
{
    private function __construct()
    {
        //NOOP
    }

    /**
     * @return mixed[]
     * @phpstan-return array<mixed>
     *
     * @throws IdentityException
     */
    public static function decodeObject(string $json, string $what): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new IdentityException("Malformed $what: " . $e->getMessage(), 0, $e);
        }
        if (!\is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new IdentityException(ucfirst($what) . " is not a JSON object");
        }

        return $decoded;
    }

    /**
     * @phpstan-param array<string, mixed> $value
     *
     * @throws IdentityException
     */
    public static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new IdentityException("Could not encode JSON: " . $e->getMessage(), 0, $e);
        }
    }
}
