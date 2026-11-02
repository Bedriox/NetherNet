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

use Bedriox\NetherNet\Negotiation\Negotiation;
use Bedriox\NetherNet\Protocol\ErrorCode;

/**
 * A negotiation whose answer, candidates and outcome are all decided up front.
 */
final class FakeNegotiation implements Negotiation
{
    /**
     * @var string[]
     * @phpstan-var list<string>
     */
    public array $remoteCandidates = [];

    public bool $closed = false;

    private bool $failed = false;
    private ?string $failureReason = null;
    private ErrorCode $failureCode = ErrorCode::NONE;

    /**
     * @param string[] $localCandidates
     * @phpstan-param list<string> $localCandidates
     */
    public function __construct(
        private readonly ?string $answer,
        private array $localCandidates
    ) {}

    public function getAnswer(): ?string
    {
        return $this->failed ? null : $this->answer;
    }

    public function isFinished(): bool
    {
        return $this->failed;
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }

    public function isClosed(): bool
    {
        return $this->failed || $this->closed;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getFailureCode(): ErrorCode
    {
        return $this->failureCode;
    }

    public function addRemoteCandidate(string $candidate): void
    {
        $this->remoteCandidates[] = $candidate;
    }

    public function takeLocalCandidates(): array
    {
        $taken = $this->localCandidates;
        $this->localCandidates = [];

        return $taken;
    }

    public function fail(string $reason, ErrorCode $code = ErrorCode::GENERIC_FAILURE): void
    {
        $this->failed = true;
        $this->failureReason = $reason;
        $this->failureCode = $code;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
