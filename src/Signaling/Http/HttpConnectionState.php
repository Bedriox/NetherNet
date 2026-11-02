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

namespace Bedriox\NetherNet\Signaling\Http;

/**
 * @internal
 */
enum HttpConnectionState
{
    /**
     * On a TLS listener, waiting for the first byte to tell a TLS client from a plain HTTP one.
     */
    case SNIFFING;
    case TLS_HANDSHAKE;
    case READING_HEAD;
    /**
     * A plain HTTP request on a TLS listener; its head is read only to redirect it.
     */
    case READING_HEAD_TO_REDIRECT;
    case READING_BODY;
    case NEGOTIATING;
    case WRITING;
    case DONE;

    /**
     * Returns whether the client has yet to deliver a complete request.
     */
    public function isAwaitingRequest(): bool
    {
        return match ($this) {
            self::SNIFFING, self::TLS_HANDSHAKE, self::READING_HEAD, self::READING_HEAD_TO_REDIRECT, self::READING_BODY => true,
            self::NEGOTIATING, self::WRITING, self::DONE => false
        };
    }
}
