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

/**
 * Accepts only tokens signed by the key they name.
 */
final class SelfSignedTokenVerifier implements TokenVerifier
{
    public function check(JsonWebToken $token): void
    {
        if (!$token->isSignedBy($token->getPublicKey())) {
            throw new IdentityException("Token is not signed by the key it carries");
        }
    }
}
