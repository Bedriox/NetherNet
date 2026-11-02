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

use Bedriox\NetherNet\Transport\MessageChannel;
use Bedriox\NetherNet\Transport\PeerTransport;
use Bedriox\NetherNet\Transport\TransportException;
use Bedriox\NetherNet\Transport\TransportState;
use Bedriox\NetherNet\Utils\InternetAddress;

final class FakePeerTransport implements PeerTransport
{
    public TransportState $state = TransportState::CONNECTING;
    public bool $lostConnectivity = false;

    public ?string $remoteOffer = null;
    public bool $rejectOffer = false;

    public ?string $localDescription = null;
    public bool $gatheringComplete = false;

    /** @phpstan-var list<string> */
    public array $remoteCandidates = [];
    public bool $rejectCandidates = false;

    /** @phpstan-var list<string> */
    public array $localCandidates = [];

    /** @phpstan-var list<MessageChannel> */
    public array $pendingChannels = [];

    public ?InternetAddress $remoteAddress = null;
    public ?int $roundTripTime = null;
    public int $bytesSent = 0;
    public int $bytesReceived = 0;

    public bool $closed = false;

    public function setRemoteOffer(string $sdp): void
    {
        if ($this->rejectOffer) {
            throw new TransportException("offer rejected by the test");
        }
        $this->remoteOffer = $sdp;
    }

    public function getLocalDescription(): ?string
    {
        return $this->localDescription;
    }

    public function isGatheringComplete(): bool
    {
        return $this->gatheringComplete;
    }

    public function addRemoteCandidate(string $candidate): void
    {
        if ($this->rejectCandidates) {
            throw new TransportException("candidate rejected by the test");
        }
        $this->remoteCandidates[] = $candidate;
    }

    public function pollLocalCandidates(): array
    {
        $taken = $this->localCandidates;
        $this->localCandidates = [];

        return $taken;
    }

    public function pollChannels(): array
    {
        $taken = $this->pendingChannels;
        $this->pendingChannels = [];

        return $taken;
    }

    public function getState(): TransportState
    {
        return $this->closed ? TransportState::CLOSED : $this->state;
    }

    public function hasLostConnectivity(): bool
    {
        return $this->lostConnectivity;
    }

    public function getRemoteAddress(): ?InternetAddress
    {
        return $this->remoteAddress;
    }

    public function getRoundTripTime(): ?int
    {
        return $this->closed ? null : $this->roundTripTime;
    }

    public function getBytesSent(): int
    {
        return $this->bytesSent;
    }

    public function getBytesReceived(): int
    {
        return $this->bytesReceived;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
