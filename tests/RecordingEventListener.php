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

use Bedriox\NetherNet\Identity\PeerIdentity;
use Bedriox\NetherNet\Protocol\DisconnectReason;
use Bedriox\NetherNet\Protocol\Reliability;
use Bedriox\NetherNet\Server\ServerEventListener;
use Bedriox\NetherNet\Utils\InternetAddress;

/**
 * Keeps every event in the order it arrived, and lets a test play the part of a listener that pushes back.
 */
final class RecordingEventListener implements ServerEventListener
{
    /** @phpstan-var list<array{int, string, InternetAddress, ?string, ?PeerIdentity}> */
    public array $opened = [];
    /** @phpstan-var list<array{int, string, Reliability}> */
    public array $packets = [];
    /** @phpstan-var list<array{int, DisconnectReason}> */
    public array $closed = [];

    /** @phpstan-var list<array{int, int}> */
    public array $acks = [];
    /** @phpstan-var list<array{int, int}> */
    public array $bandwidth = [];
    /** @phpstan-var list<array{int, int}> */
    public array $pings = [];

    public bool $accepting = true;
    public ?\Throwable $failOpenWith = null;

    /** @phpstan-var (\Closure(int) : void)|null */
    public ?\Closure $onOpen = null;

    /** @phpstan-var (\Closure(int, string) : void)|null */
    public ?\Closure $onPacket = null;

    public function onSessionOpen(int $sessionId, string $networkId, InternetAddress $address, ?string $clientAddress, ?PeerIdentity $identity): void
    {
        $this->opened[] = [$sessionId, $networkId, $address, $clientAddress, $identity];
        if ($this->failOpenWith !== null) {
            throw $this->failOpenWith;
        }
        if ($this->onOpen !== null) {
            ($this->onOpen)($sessionId);
        }
    }

    public function canAcceptPackets(): bool
    {
        return $this->accepting;
    }

    public function onPacketReceive(int $sessionId, string $payload, Reliability $reliability): void
    {
        $this->packets[] = [$sessionId, $payload, $reliability];
        if ($this->onPacket !== null) {
            ($this->onPacket)($sessionId, $payload);
        }
    }

    public function onSessionClose(int $sessionId, DisconnectReason $reason): void
    {
        $this->closed[] = [$sessionId, $reason];
    }

    public function onPacketAck(int $sessionId, int $receiptId): void
    {
        $this->acks[] = [$sessionId, $receiptId];
    }

    public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff): void
    {
        $this->bandwidth[] = [$bytesSentDiff, $bytesReceivedDiff];
    }

    public function onPingMeasure(int $sessionId, int $pingMs): void
    {
        $this->pings[] = [$sessionId, $pingMs];
    }
}
