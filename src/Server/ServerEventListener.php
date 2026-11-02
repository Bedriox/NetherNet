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

namespace Bedriox\NetherNet\Server;

use Bedriox\NetherNet\Identity\PeerIdentity;
use Bedriox\NetherNet\Protocol\DisconnectReason;
use Bedriox\NetherNet\Protocol\Reliability;
use Bedriox\NetherNet\Utils\InternetAddress;

interface ServerEventListener
{
    /**
     * The session can be turned away from here with {@link ServerInterface::closeSession()}; it is then reported to
     * onSessionClose() like any other, without any of its packets having been delivered.
     * If this throws, the session is closed without onSessionClose() being called.
     *
     * @param InternetAddress $address       Where the transport reaches the peer; a relay's address if it connects through one.
     * @param string|null     $clientAddress IPv4 or IPv6 address the offer was signaled from, or the one a trusted
     *                                       reverse proxy named, where the signaling transport knew it. Always in
     *                                       canonical form: unbracketed, IPv6 lowercase and zero-compressed.
     */
    public function onSessionOpen(int $sessionId, string $networkId, InternetAddress $address, ?string $clientAddress, ?PeerIdentity $identity): void;

    public function onSessionClose(int $sessionId, DisconnectReason $reason): void;

    /**
     * Asked before every packet is read. Returning false leaves the packet in the client's receive queue.
     */
    public function canAcceptPackets(): bool;

    public function onPacketReceive(int $sessionId, string $payload, Reliability $reliability): void;

    /**
     * Called when a packet sent with a receipt ID has left the send queue.
     */
    public function onPacketAck(int $sessionId, int $receiptId): void;

    public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff): void;

    public function onPingMeasure(int $sessionId, int $pingMs): void;
}
