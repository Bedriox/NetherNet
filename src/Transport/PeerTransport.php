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

namespace Bedriox\NetherNet\Transport;

use Bedriox\NetherNet\Utils\InternetAddress;

interface PeerTransport
{
    /**
     * @throws TransportException
     */
    public function setRemoteOffer(string $sdp): void;

    /**
     * @throws TransportException
     */
    public function getLocalDescription(): ?string;

    /**
     * @throws TransportException
     */
    public function isGatheringComplete(): bool;

    /**
     * @throws TransportException
     */
    public function addRemoteCandidate(string $candidate): void;

    /**
     * Drains the local candidates gathered since the last call.
     *
     * @return string[]
     * @phpstan-return list<string>
     *
     * @throws TransportException
     */
    public function pollLocalCandidates(): array;

    /**
     * Drains the channels the peer has opened since the last call.
     *
     * @return MessageChannel[]
     * @phpstan-return list<MessageChannel>
     *
     * @throws TransportException
     */
    public function pollChannels(): array;

    public function getState(): TransportState;

    /**
     * Returns whether connectivity was lost, as opposed to an orderly close. Still answered after close().
     */
    public function hasLostConnectivity(): bool;

    /**
     * The last known address is kept after close().
     */
    public function getRemoteAddress(): ?InternetAddress;

    public function getRoundTripTime(): ?int;

    /**
     * The final count is kept after close().
     */
    public function getBytesSent(): int;

    /**
     * The final count is kept after close().
     */
    public function getBytesReceived(): int;

    /**
     * Never throws.
     */
    public function close(): void;
}
