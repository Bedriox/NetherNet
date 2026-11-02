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

namespace Bedriox\NetherNet\Negotiation;

use Bedriox\NetherNet\NetherNetException;
use Bedriox\NetherNet\Protocol\ErrorCode;

class NegotiationException extends NetherNetException
{
    public function __construct(
        string $message,
        private readonly ErrorCode $errorCode = ErrorCode::GENERIC_FAILURE,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
