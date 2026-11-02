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

namespace Bedriox\NetherNet\Protocol;

enum DisconnectReason: int
{
    /**
     * The remote client closed the connection or data channels, or the SCTP association was lost to missed heartbeats.
     */
    case PEER_DISCONNECT = 0;
    case SERVER_DISCONNECT = 1;
    case SERVER_SHUTDOWN = 2;
    case REJECTED_BY_HOST = 3;

    /**
     * The underlying WebRTC peer connection failed (e.g. ICE consent check timeout).
     */
    case CONNECTION_FAILED = 4;
    case BAD_DATA = 5;
    case SEND_FAILED = 6;
    case RECEIVE_QUEUE_TOO_MANY_BYTES = 7;
    case RECEIVE_QUEUE_TOO_MANY_MESSAGES = 8;
    case SEND_QUEUE_TOO_MANY_BYTES = 9;
    case ADDRESS_BLOCKED = 10;

    public function getMessage(): string
    {
        return match ($this) {
            self::PEER_DISCONNECT => "client disconnect",
            self::SERVER_DISCONNECT => "server disconnect",
            self::SERVER_SHUTDOWN => "server shutdown",
            self::REJECTED_BY_HOST => "rejected by the host",
            self::CONNECTION_FAILED => "connection failed",
            self::BAD_DATA => "bad data received",
            self::SEND_FAILED => "failed to send",
            self::RECEIVE_QUEUE_TOO_MANY_BYTES => "too many unread bytes queued",
            self::RECEIVE_QUEUE_TOO_MANY_MESSAGES => "too many unread messages queued",
            self::SEND_QUEUE_TOO_MANY_BYTES => "too many unsent bytes queued",
            self::ADDRESS_BLOCKED => "address blocked"
        };
    }
}
