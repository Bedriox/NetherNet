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

use Bedriox\NetherNet\FakeNegotiator;
use Bedriox\NetherNet\Info\MutableServerInfoProvider;
use Bedriox\NetherNet\Negotiation\Negotiation;
use Bedriox\NetherNet\Negotiation\NegotiationException;
use Bedriox\NetherNet\Protocol\ErrorCode;
use Bedriox\NetherNet\RecordingLogger;
use Bedriox\NetherNet\Utils\AddressBlockTracker;
use Bedriox\NetherNet\Utils\InternetAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class JoinHandlerTest extends TestCase
{
    private FakeNegotiator $negotiator;
    private AddressBlockTracker $blockTracker;

    protected function setUp(): void
    {
        $this->negotiator = new FakeNegotiator("answer-sdp");
        $this->blockTracker = new AddressBlockTracker();
    }

    private function handler(?ReverseProxy $reverseProxy = null): JoinHandler
    {
        return new JoinHandler($this->negotiator, $this->blockTracker, new MutableServerInfoProvider(), $reverseProxy, new RecordingLogger());
    }

    private static function request(string $requestLine, string $headers = ""): HttpRequest
    {
        return HttpRequest::parse("$requestLine HTTP/1.1\r\nHost: example.test\r\n$headers");
    }

    private static function offer(string $path = "/v1/join/1234", string $headers = ""): HttpRequest
    {
        return self::request("POST $path", "Content-Type: application/sdp\r\nContent-Length: 5\r\n$headers");
    }

    private static function peer(): InternetAddress
    {
        return new InternetAddress("192.0.2.10", 40000, 4);
    }

    public function testProbeEndpointIsRouted(): void
    {
        self::assertNull($this->handler()->route(self::request("GET /v1/join")));
        self::assertNull($this->handler()->route(self::request("HEAD /v1/join/")));
    }

    public function testOfferIsRoutedByTheNetworkIdInItsPath(): void
    {
        self::assertSame("1234", $this->handler()->route(self::offer()));
        self::assertSame("567", $this->handler()->route(self::offer("/v1/join/5%367?x=1")));
    }

    #[DataProvider('unroutableProvider')]
    public function testUnroutableRequestIsRefusedFromItsHeadAlone(HttpRequest $request, HttpStatus $expected): void
    {
        try {
            $this->handler()->route($request);
            self::fail("the request was routed");
        } catch (HttpException $e) {
            self::assertSame($expected, $e->getStatus());
        }
    }

    /**
     * @phpstan-return array<string, array{HttpRequest, HttpStatus}>
     */
    public static function unroutableProvider(): array
    {
        return [
            "unknown path" => [self::request("GET /status"), HttpStatus::NOT_FOUND],
            "prefix that only looks like the endpoint" => [self::request("GET /v1/joined"), HttpStatus::NOT_FOUND],
            "post to the probe" => [self::request("POST /v1/join", "Content-Length: 0\r\n"), HttpStatus::METHOD_NOT_ALLOWED],
            "get of an offer path" => [self::request("GET /v1/join/1234"), HttpStatus::METHOD_NOT_ALLOWED],
            "offer that is not sdp" => [self::request("POST /v1/join/1234", "Content-Type: text/plain\r\nContent-Length: 5\r\n"), HttpStatus::UNSUPPORTED_MEDIA_TYPE],
            "network id too long" => [self::offer("/v1/join/" . str_repeat("9", HttpSignaling::MAX_NETWORK_ID_LENGTH + 1)), HttpStatus::URI_TOO_LONG],
        ];
    }

    public function testRequestFromABlockedAddressGetsNoAnswer(): void
    {
        $this->blockTracker->block("192.0.2.10");
        $handler = $this->handler();
        $request = self::offer();

        self::assertNull($handler->handle($request, "offer", self::peer()));
        self::assertNull($this->negotiator->lastOffer);
    }

    /**
     * Behind a proxy every connection comes from the proxy, so a block has to be judged against the address
     * the proxy vouches for.
     */
    public function testBlockIsJudgedAgainstTheAddressATrustedProxyNames(): void
    {
        $this->blockTracker->block("203.0.113.5");
        $handler = $this->handler(new ReverseProxy(ReverseProxy::HEADER_FORWARDED_FOR, ["192.0.2.10"]));
        $request = self::offer(headers: "X-Forwarded-For: 203.0.113.5\r\n");

        self::assertNull($handler->handle($request, "offer", self::peer()));
    }

    public function testOfferFromATrustedProxyThatNamesNoClientIsRefused(): void
    {
        $handler = $this->handler(new ReverseProxy(ReverseProxy::HEADER_REAL_IP, ["192.0.2.10"]));

        foreach (["", "X-Forwarded-For: 203.0.113.5\r\n", "X-Real-IP: unknown\r\n"] as $headers) {
            try {
                $handler->handle(self::offer(headers: $headers), "offer", self::peer());
                self::fail("the offer was accepted");
            } catch (HttpException $e) {
                self::assertSame(HttpStatus::BAD_REQUEST, $e->getStatus());
            }
        }
        self::assertNull($this->negotiator->lastOffer);
    }

    public function testProbeFromATrustedProxyThatNamesNoClientIsAnswered(): void
    {
        $handler = $this->handler(new ReverseProxy(ReverseProxy::HEADER_REAL_IP, ["192.0.2.10"]));

        $response = $handler->handle(self::request("GET /v1/join"), "", self::peer());

        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(HttpStatus::OK, $response->status);
    }

    public function testOfferTheNegotiatorRefusesIsReportedAsItsErrorCode(): void
    {
        $negotiator = new class extends \stdClass implements \Bedriox\NetherNet\Negotiation\Negotiator {
            public function beginNegotiation(string $offerSdp, string $networkId, \Bedriox\NetherNet\Negotiation\CandidateMode $candidateMode = \Bedriox\NetherNet\Negotiation\CandidateMode::BUNDLED, ?string $peerAddress = null): Negotiation
            {
                throw new NegotiationException("offer is no good", ErrorCode::FAILED_TO_SET_REMOTE_DESCRIPTION);
            }

            public function tick(float $now): void {}

            public function takeEstablished(): array
            {
                return [];
            }

            public function takeFloodingAddresses(): array
            {
                return [];
            }

            public function shutdown(): void {}
        };
        $handler = new JoinHandler($negotiator, $this->blockTracker, null, null, new RecordingLogger());
        $response = $handler->handle(self::offer(), "offer", self::peer());

        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $response->status);
        self::assertSame((string) ErrorCode::FAILED_TO_SET_REMOTE_DESCRIPTION->value, $response->body);
    }
}
