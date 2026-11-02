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

use Bedriox\NetherNet\Sdp\Fingerprint;

use function time;

/**
 * Verifies a peer's identity assertion (`a=identity`) against its DTLS fingerprint.
 *
 * On its own this proves only that the peer holds the key its token names. Whether the token itself is to be
 * believed is up to the {@link TokenVerifier}; without one, any well-formed, unexpired token is accepted.
 */
final class AssertionIdentityVerifier
{
    public function __construct(
        public readonly bool $allowAnonymous = false,
        public readonly ?TokenVerifier $tokenVerifier = null
    ) {}

    /**
     * @param string|null $attributeValue The offer's `a=identity` value, or null if it has none.
     *
     * @return PeerIdentity|null null for a peer without an assertion, when those are allowed
     *
     * @throws IdentityException
     */
    public function verify(?string $attributeValue, Fingerprint $remoteFingerprint): ?PeerIdentity
    {
        if ($attributeValue === null) {
            if (!$this->allowAnonymous) {
                throw new IdentityException("Offer has no identity assertion");
            }

            return null;
        }

        try {
            $assertion = IdentityAssertion::parse($attributeValue);
        } catch (IdentityException $e) {
            throw new IdentityException("Identity assertion is malformed: " . $e->getMessage(), 0, $e);
        }

        $token = $assertion->getToken();
        $token->checkTimestamps(time());
        $this->tokenVerifier?->check($token);

        try {
            $assertion->verifyFingerprint($remoteFingerprint);
        } catch (IdentityException $e) {
            throw new IdentityException("Identity assertion does not cover this connection: " . $e->getMessage(), 0, $e);
        }

        return new PeerIdentity($token->getPublicKey(), $assertion->getDomain(), $token);
    }
}
