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

final readonly class PrefixedLogger implements Logger
{
    public function __construct(
        private Logger $delegate,
        private string $prefix,
    ) {}

    public function debug(string $message): void
    {
        $this->delegate->debug($this->prefix($message));
    }

    public function info(string $message): void
    {
        $this->delegate->info($this->prefix($message));
    }

    public function notice(string $message): void
    {
        $this->delegate->notice($this->prefix($message));
    }

    public function warning(string $message): void
    {
        $this->delegate->warning($this->prefix($message));
    }

    public function error(string $message): void
    {
        $this->delegate->error($this->prefix($message));
    }

    public function logException(Throwable $throwable): void
    {
        $this->delegate->logException($throwable);
    }

    private function prefix(string $message): string
    {
        return '[' . $this->prefix . '] ' . $message;
    }
}
