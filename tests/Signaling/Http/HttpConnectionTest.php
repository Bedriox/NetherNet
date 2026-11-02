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
use Bedriox\NetherNet\RecordingLogger;
use Bedriox\NetherNet\Utils\AddressBlockTracker;
use Bedriox\NetherNet\Utils\InternetAddress;

use function fclose;
use function fread;
use function fwrite;
use function is_resource;

use const PHP_OS_FAMILY;

use PHPUnit\Framework\TestCase;

use const SO_SNDBUF;

use function socket_import_stream;
use function socket_set_option;

use const SOL_SOCKET;

use function str_repeat;
use function stream_set_blocking;
use function stream_socket_accept;
use function stream_socket_client;
use function stream_socket_get_name;
use function stream_socket_server;

/**
 * Drives a connection over a socket pair with the clock in the test's hands, which is the only way to see
 * what it does about a client that is slow, oversized or gone.
 */
final class HttpConnectionTest extends TestCase
{
    /** @var resource|null */
    private mixed $client = null;
    private ?\Socket $serverSocket = null;

    private FakeNegotiator $negotiator;
    private JoinHandler $handler;
    private HttpConnection $connection;

    protected function setUp(): void
    {
        $listener = stream_socket_server("tcp://127.0.0.1:0", $errorCode, $errorMessage);
        self::assertNotFalse($listener, "could not create test listener: $errorMessage ($errorCode)");
        $address = stream_socket_get_name($listener, false);
        self::assertNotFalse($address);
        $client = stream_socket_client("tcp://$address", $errorCode, $errorMessage, 5.0);
        if ($client === false) {
            throw new \RuntimeException("could not connect test client: $errorMessage ($errorCode)");
        }
        $this->client = $client;
        $server = stream_socket_accept($listener, 5.0);
        fclose($listener);
        self::assertNotFalse($server, "could not accept test client");
        $serverSocket = socket_import_stream($server);
        self::assertNotFalse($serverSocket);
        $this->serverSocket = $serverSocket;
        socket_set_option($this->serverSocket, SOL_SOCKET, SO_SNDBUF, 4096);
        stream_set_blocking($this->client, false);
        stream_set_blocking($server, false);

        $logger = new RecordingLogger();
        $this->negotiator = new FakeNegotiator("answer-sdp");
        $this->handler = new JoinHandler($this->negotiator, new AddressBlockTracker(), null, null, $logger);
        $this->connection = new HttpConnection(
            $server,
            null,
            new InternetAddress("192.0.2.10", 40000, 4),
            null,
            $logger,
            100.0
        );
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        if (\is_resource($this->client)) {
            fclose($this->client);
        }
    }

    private function send(string $data): void
    {
        self::assertNotNull($this->client);
        fwrite($this->client, $data);
    }

    private function received(): string
    {
        self::assertNotNull($this->client);
        $data = fread($this->client, 65536);

        return $data === false ? "" : $data;
    }

    public function testConnectionWaitsWhileTheNegotiationHasNoAnswer(): void
    {
        $this->handler = new JoinHandler($negotiator = new FakeNegotiator(null), new AddressBlockTracker(), null, null, new RecordingLogger());
        $this->send("POST /v1/join/1234 HTTP/1.1\r\nHost: h\r\nContent-Type: application/sdp\r\nContent-Length: 5\r\n\r\noffer");

        $this->connection->advance(100.1, $this->handler);
        $this->connection->advance(500.0, $this->handler);

        self::assertFalse($this->connection->isDone());
        self::assertSame("", $this->received());

        $negotiator->negotiation?->fail("ice failed");
        $this->connection->advance(500.1, $this->handler);
        self::assertStringStartsWith("HTTP/1.1 500 ", $this->received());
    }

    public function testBodyArrivingInPiecesIsWaitedFor(): void
    {
        $this->send("POST /v1/join/1234 HTTP/1.1\r\nHost: h\r\nContent-Type: application/sdp\r\nContent-Length: 5\r\n\r\nof");
        $this->connection->advance(100.1, $this->handler);

        self::assertFalse($this->connection->isDone());
        self::assertNull($this->negotiator->lastOffer);

        $this->send("fer");
        $this->connection->advance(100.2, $this->handler);
        self::assertSame("offer", $this->negotiator->lastOffer);
    }

    public function testClientThatNeverFinishesItsHeadTimesOut(): void
    {
        $this->send("GET /v1/join HTTP/1.1\r\n");

        $this->connection->advance(100.0 + HttpConnection::HEAD_TIMEOUT, $this->handler);
        self::assertSame("", $this->received());

        $this->connection->advance(100.0 + HttpConnection::HEAD_TIMEOUT + 0.01, $this->handler);
        self::assertStringStartsWith("HTTP/1.1 408 ", $this->received());
    }

    /**
     * The body timeout covers the whole request, so a client cannot buy itself more time by sending its head late.
     */
    public function testClientThatNeverFinishesItsBodyTimesOut(): void
    {
        $this->send("POST /v1/join/1234 HTTP/1.1\r\nHost: h\r\nContent-Type: application/sdp\r\nContent-Length: 5\r\n\r\nof");
        $this->connection->advance(104.0, $this->handler);

        $this->connection->advance(100.0 + HttpConnection::BODY_TIMEOUT, $this->handler);
        self::assertSame("", $this->received());

        $this->connection->advance(100.0 + HttpConnection::BODY_TIMEOUT + 0.01, $this->handler);
        self::assertStringStartsWith("HTTP/1.1 408 ", $this->received());
        self::assertNull($this->negotiator->lastOffer);
    }

    public function testHeadThatNeverEndsIsRefusedOnceItIsTooLarge(): void
    {
        $this->send("GET /v1/join HTTP/1.1\r\nX-Padding: " . str_repeat("a", HttpConnection::MAX_HEAD_SIZE));

        $this->connection->advance(100.1, $this->handler);

        self::assertStringStartsWith("HTTP/1.1 431 ", $this->received());
    }

    public function testClientThatLeavesMidRequestEndsTheConnection(): void
    {
        $this->send("GET /v1/join HTTP/1.1\r\n");
        $this->connection->advance(100.1, $this->handler);

        self::assertNotNull($this->client);
        fclose($this->client);
        $this->connection->advance(100.2, $this->handler);

        self::assertTrue($this->connection->isDone());
    }

    /**
     * A negotiation nobody is left to hear the answer of would otherwise hold a peer connection open until it timed out.
     */
    public function testClosingBeforeTheAnswerIsDeliveredFailsTheNegotiation(): void
    {
        $this->handler = new JoinHandler($negotiator = new FakeNegotiator(null), new AddressBlockTracker(), null, null, new RecordingLogger());
        $this->send("POST /v1/join/1234 HTTP/1.1\r\nHost: h\r\nContent-Type: application/sdp\r\nContent-Length: 5\r\n\r\noffer");
        $this->connection->advance(100.1, $this->handler);

        $this->connection->close();

        self::assertTrue($negotiator->negotiation?->isFailed());
    }

    public function testResponseTheClientNeverTakesIsGivenUpOn(): void
    {
        if (PHP_OS_FAMILY === "Windows") {
            self::markTestSkipped("Windows accepts the complete loopback response into its TCP buffer.");
        }
        //more than a socket buffer holds, to a client that reads none of it
        $this->handler = new JoinHandler(new FakeNegotiator(str_repeat("a", 4 * 1024 * 1024)), new AddressBlockTracker(), null, null, new RecordingLogger());
        $this->send("POST /v1/join/1234 HTTP/1.1\r\nHost: h\r\nContent-Type: application/sdp\r\nContent-Length: 5\r\n\r\noffer");

        $this->connection->advance(100.0, $this->handler);
        $this->connection->advance(100.1, $this->handler);
        self::assertFalse($this->connection->isDone());

        $this->connection->advance(100.1 + HttpConnection::WRITE_TIMEOUT, $this->handler);
        self::assertFalse($this->connection->isDone());

        $this->connection->advance(100.1 + HttpConnection::WRITE_TIMEOUT + 0.01, $this->handler);
        self::assertTrue($this->connection->isDone());
    }
}
