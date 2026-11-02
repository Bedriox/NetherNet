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

namespace Bedriox\NetherNet\Utils;

use Bedriox\NetherNet\NetherNetException;

use function socket_strerror;

class SocketException extends NetherNetException
{
    public static function fromErrno(string $what, int $errno): self
    {
        return new self("$what: " . socket_strerror($errno), $errno);
    }
}
