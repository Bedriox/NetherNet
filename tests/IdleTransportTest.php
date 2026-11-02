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

use Bedriox\NetherNet\Info\MutableServerInfoProvider;
use Bedriox\NetherNet\Info\ServerInfo;
use Bedriox\NetherNet\Logging\Logger;
use Bedriox\NetherNet\Signaling\Http\HttpSignaling;
use Bedriox\NetherNet\Signaling\Lan\LanSignaling;
use Bedriox\NetherNet\Signaling\Signaling;
use Bedriox\NetherNet\Utils\InternetAddress;

use function count;
use function implode;
use function microtime;

use PHPUnit\Framework\TestCase;

/**
 * A transport with nothing to do has to stay quiet.
 *
 * This is not tidiness. Both transports drain their socket until it reports
 * having nothing left, and "nothing left" is an error code; reading that code
 * from the wrong place makes an idle socket look like a broken one. The symptom
 * is a debug line every few milliseconds forever, and the only thing that
 * catches it is asserting on silence.
 *
 * It bit the accept loop once already: socket_accept() records its error in the
 * global slot rather than against the socket, so checking the socket's slot
 * returned zero and every idle pass was reported as a failure.
 */
final class IdleTransportTest extends TestCase
{
    private const IDLE_PASSES = 25;

    private ?Signaling $signaling = null;

    protected function tearDown(): void
    {
        $this->signaling?->shutdown();
    }

    private function start(Signaling $signaling, Logger $logger): Signaling
    {
        $signaling->start(TestSignalingContext::create(new FakeNegotiator("answer-sdp"), $logger));

        return $this->signaling = $signaling;
    }

    public function testIdleHttpSignalingLogsNothing(): void
    {
        $logger = new RecordingLogger();
        $signaling = $this->start(new HttpSignaling(new InternetAddress("127.0.0.1", 0, 4)), $logger);

        for ($i = 0; $i < self::IDLE_PASSES; ++$i) {
            $signaling->tick(microtime(true));
        }

        self::assertSame([], $logger->messages, "an idle listener logged: " . implode(" | ", $logger->messages));
    }

    public function testIdleLanSignalingLogsNothingBeyondItsStartupLine(): void
    {
        $logger = new RecordingLogger();
        $signaling = $this->start(new LanSignaling(
            new MutableServerInfoProvider(new ServerInfo(serverName: "host", protocol: 2181, gameVersion: "1.26.50", levelName: "world")),
            4242,
            new InternetAddress("127.0.0.1", 0, 4)
        ), $logger);

        //one line is written when the socket comes up, and nothing after it
        $afterStart = \count($logger->messages);
        self::assertSame(1, $afterStart);

        for ($i = 0; $i < self::IDLE_PASSES; ++$i) {
            $signaling->tick(microtime(true));
        }

        self::assertCount($afterStart, $logger->messages, "an idle listener logged: " . implode(" | ", $logger->messages));
    }
}
