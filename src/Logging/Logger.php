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

namespace Bedriox\NetherNet\Logging;

use Throwable;

interface Logger
{
    public function debug(string $message): void;

    public function info(string $message): void;

    public function notice(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;

    public function logException(Throwable $throwable): void;
}
