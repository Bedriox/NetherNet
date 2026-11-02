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

use PHPUnit\Framework\TestCase;

final class HttpResponseTest extends TestCase
{
    public function testResponseDeclaresItsLengthAndClosesTheConnection(): void
    {
        $response = new HttpResponse(HttpStatus::OK, "answer", "application/sdp", ["X-Test" => "1"]);

        self::assertSame(
            "HTTP/1.1 200 OK\r\nContent-Type: application/sdp\r\nX-Test: 1\r\nContent-Length: 6\r\nConnection: close\r\n\r\nanswer",
            $response->encode()
        );
    }

    /**
     * A HEAD response describes the body a GET would have had without sending it.
     */
    public function testBodyCanBeOmittedWhileItsLengthIsKept(): void
    {
        $encoded = new HttpResponse(HttpStatus::OK, "answer")->encode(omitBody: true);

        self::assertStringContainsString("Content-Length: 6\r\n", $encoded);
        self::assertStringEndsWith("\r\n\r\n", $encoded);
    }
}
