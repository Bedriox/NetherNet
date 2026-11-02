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

namespace Bedriox\NetherNet\Signaling;

interface Signaling
{
    public function describe(): string;

    /**
     * @throws SignalingException
     * @throws \LogicException if already started or shut down
     */
    public function start(SignalingContext $context): void;

    public function tick(float $now): void;

    /**
     * Must be safe to call even if {@link Signaling::start()} failed or was never called.
     */
    public function shutdown(): void;
}
