<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 *
 * Website: https://bedriox.com
 * Source: https://github.com/Bedriox/NetherNet
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Client;

enum ClientCloseReason: string
{
    case HANDSHAKE_TIMEOUT = 'handshake_timeout';
    case IDLE_TIMEOUT = 'idle_timeout';
    case SIGNALING_REJECTED = 'signaling_rejected';
    case IDENTITY_REJECTED = 'identity_rejected';
    case MALFORMED_RESPONSE = 'malformed_response';
    case REMOTE_DISCONNECT = 'remote_disconnect';
    case LOCAL_CLOSE = 'local_close';
}
