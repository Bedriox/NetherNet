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

/**
 * Supplies the identity assertion a host attaches to its negotiation answers.
 */
interface IdentityProvider
{
    /**
     * @throws IdentityException
     */
    public function issue(Fingerprint $localFingerprint): IdentityAssertion;
}
