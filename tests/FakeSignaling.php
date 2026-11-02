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

namespace Bedriox\NetherNet;

use Bedriox\NetherNet\Signaling\Signaling;
use Bedriox\NetherNet\Signaling\SignalingContext;
use Bedriox\NetherNet\Signaling\SignalingException;

final class FakeSignaling implements Signaling
{
    public ?SignalingContext $context = null;
    /** @phpstan-var list<float> */
    public array $ticks = [];
    public int $shutdowns = 0;

    public function __construct(
        private readonly bool $failToStart = false
    ) {}

    public function describe(): string
    {
        return "fake signaling";
    }

    public function start(SignalingContext $context): void
    {
        if ($this->failToStart) {
            throw new SignalingException("start refused by the test");
        }
        $this->context = $context;
    }

    public function tick(float $now): void
    {
        $this->ticks[] = $now;
    }

    public function shutdown(): void
    {
        ++$this->shutdowns;
    }
}
