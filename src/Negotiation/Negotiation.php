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

namespace Bedriox\NetherNet\Negotiation;

use Bedriox\NetherNet\Protocol\ErrorCode;

interface Negotiation
{
    /**
     * Returns the SDP answer to send back to the peer, or null if gathering/generation is pending.
     */
    public function getAnswer(): ?string;

    public function isFinished(): bool;

    public function isFailed(): bool;

    /**
     * Returns whether the peer connection is gone: the negotiation failed, or the connection it established has
     * since been closed.
     */
    public function isClosed(): bool;

    public function getFailureReason(): ?string;

    public function getFailureCode(): ErrorCode;

    /**
     * @throws NegotiationException
     */
    public function addRemoteCandidate(string $candidate): void;

    /**
     * Drains the local ICE candidates gathered since the last call.
     *
     * @return string[]
     * @phpstan-return list<string>
     */
    public function takeLocalCandidates(): array;

    public function fail(string $reason, ErrorCode $code = ErrorCode::GENERIC_FAILURE): void;

    /**
     * Closes the peer connection, including one that is established and has been handed over.
     */
    public function close(): void;
}
