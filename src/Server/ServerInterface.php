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

use Bedriox\NetherNet\Protocol\DisconnectReason;
use Bedriox\NetherNet\Protocol\Reliability;

/**
 * Commands naming a session that no longer exists are ignored.
 */
interface ServerInterface
{
    /**
     * A payload too large to send closes the session with {@link DisconnectReason::SEND_FAILED} if it was to be
     * reliable, and is dropped if it was not.
     */
    public function sendPacket(int $sessionId, string $payload, Reliability $reliability = Reliability::RELIABLE, ?int $receiptId = null): void;

    /**
     * Disconnects a client once it closes after the packets already queued for it, or after a timeout.
     */
    public function closeSession(int $sessionId, DisconnectReason $reason = DisconnectReason::SERVER_DISCONNECT): void;

    /**
     * Refuses new connections from the address and disconnects the sessions that either connect from it or were
     * signaled from it.
     *
     * @param int $timeout Seconds until the address is unblocked, or -1 to block it until unblockAddress() is called.
     */
    public function blockAddress(string $address, int $timeout = 300): void;

    public function unblockAddress(string $address): void;
}
