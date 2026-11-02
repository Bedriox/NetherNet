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

use Bedriox\NetherNet\Signaling\SignalingException;

/**
 * A request that cannot be served, carrying the status to answer it with.
 */
class HttpException extends SignalingException
{
    public function __construct(
        private readonly HttpStatus $status,
        string $message
    ) {
        parent::__construct($message);
    }

    public function getStatus(): HttpStatus
    {
        return $this->status;
    }
}
