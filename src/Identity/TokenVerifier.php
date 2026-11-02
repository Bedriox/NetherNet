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
 * Verifies whether a peer's identity token is acceptable.
 *
 * Implementations can validate token signatures, issuers, or custom authorization policies.
 *
 * If no verifier is provided to {@link AssertionIdentityVerifier}, any well-formed, unexpired token
 * is accepted.
 *
 * @see SelfSignedTokenVerifier
 */
interface TokenVerifier
{
    /**
     * @throws IdentityException if the token fails verification.
     */
    public function check(JsonWebToken $token): void;
}
