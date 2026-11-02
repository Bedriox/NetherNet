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

use function is_string;

use const OPENSSL_ALGO_SHA384;

use function openssl_sign;
use function openssl_verify;

/**
 * @internal
 * ECDSA over P-384 with SHA-384, with signatures in JOSE form (r || s).
 */
final class Es384
{
    public const ALGORITHM = "ES384";

    private function __construct()
    {
        //NOOP
    }

    /**
     * @throws CryptoException
     */
    public static function sign(string $input, \OpenSSLAsymmetricKey $privateKey): string
    {
        $der = "";
        if (!openssl_sign($input, $der, $privateKey, OPENSSL_ALGO_SHA384)) {
            throw new CryptoException("OpenSSL failed to sign: " . OpenSsl::lastError());
        }
        if (!\is_string($der)) {
            throw new CryptoException("OpenSSL returned a non-string signature");
        }

        return EcdsaSignature::derToJose($der);
    }

    /**
     * @throws CryptoException
     */
    public static function verify(string $input, string $signature, \OpenSSLAsymmetricKey $publicKey): bool
    {
        return match (openssl_verify($input, EcdsaSignature::joseToDer($signature), $publicKey, OPENSSL_ALGO_SHA384)) {
            1 => true,
            0 => false,
            default => throw new CryptoException("OpenSSL failed to verify: " . OpenSsl::lastError())
        };
    }
}
