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

namespace Bedriox\NetherNet;

use Bedriox\NetherNet\Logging\Logger;
use Throwable;

/**
 * Keeps everything logged so a test can assert on it.
 *
 * Useful mainly in the negative: a transport that is doing nothing should say
 * nothing, and a loop that mistakes idleness for failure is otherwise invisible
 * until it fills somebody's console.
 */
final class RecordingLogger implements Logger
{
    /**
     * @var string[]
     * @phpstan-var list<string>
     */
    public array $messages = [];

    public function debug(string $message): void
    {
        $this->record('debug', $message);
    }

    public function info(string $message): void
    {
        $this->record('info', $message);
    }

    public function notice(string $message): void
    {
        $this->record('notice', $message);
    }

    public function warning(string $message): void
    {
        $this->record('warning', $message);
    }

    public function error(string $message): void
    {
        $this->record('error', $message);
    }

    public function logException(Throwable $throwable): void
    {
        $this->record('critical', $throwable->getMessage());
    }

    private function record(string $level, string $message): void
    {
        $this->messages[] = $level . ': ' . $message;
    }
}
