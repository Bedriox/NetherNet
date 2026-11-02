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

namespace Bedriox\NetherNet\Signaling\Lan;

use Bedriox\NetherNet\Negotiation\Negotiation;
use Bedriox\NetherNet\Signaling\Signal;
use Bedriox\NetherNet\Signaling\SignalType;
use Bedriox\NetherNet\Utils\InternetAddress;

/**
 * @internal
 */
final class LanConnection
{
    private bool $answerSent = false;

    public function __construct(
        public readonly Negotiation $negotiation,
        public readonly int $peerId,
        public readonly InternetAddress $address,
        public readonly string $connectionId
    ) {}

    /**
     * Takes the signals the peer is now owed.
     *
     * @return Signal[]
     * @phpstan-return list<Signal>
     */
    public function takeSignals(): array
    {
        if ($this->negotiation->isFailed()) {
            return [Signal::error($this->connectionId, $this->negotiation->getFailureCode())];
        }

        $signals = [];
        if (!$this->answerSent) {
            $answer = $this->negotiation->getAnswer();
            if ($answer === null) {
                /* A candidate refers to the answer it belongs to, so none goes out before the answer has. */
                return [];
            }
            $signals[] = new Signal(SignalType::CONNECT_RESPONSE, $this->connectionId, $answer);
            $this->answerSent = true;
        }
        foreach ($this->negotiation->takeLocalCandidates() as $candidate) {
            $signals[] = new Signal(SignalType::CANDIDATE_ADD, $this->connectionId, $candidate);
        }

        return $signals;
    }
}
