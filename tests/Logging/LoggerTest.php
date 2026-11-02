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

use Bedriox\NetherNet\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoggerTest extends TestCase
{
    public function testPrefixedLoggerPreservesLevelsAndExceptionReporting(): void
    {
        $recording = new RecordingLogger();
        $logger = new PrefixedLogger($recording, 'HTTP signaling');

        $logger->debug('debug');
        $logger->info('info');
        $logger->notice('notice');
        $logger->warning('warning');
        $logger->error('error');
        $logger->logException(new RuntimeException('failure'));

        self::assertSame([
            'debug: [HTTP signaling] debug',
            'info: [HTTP signaling] info',
            'notice: [HTTP signaling] notice',
            'warning: [HTTP signaling] warning',
            'error: [HTTP signaling] error',
            'critical: failure',
        ], $recording->messages);
    }

    public function testNullLoggerAcceptsEverySupportedLevel(): void
    {
        $this->expectNotToPerformAssertions();
        $logger = new NullLogger();

        $logger->debug('debug');
        $logger->info('info');
        $logger->notice('notice');
        $logger->warning('warning');
        $logger->error('error');
        $logger->logException(new RuntimeException('failure'));

    }
}
