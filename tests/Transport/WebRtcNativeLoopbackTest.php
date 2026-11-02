<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 *
 * Website: https://bedriox.com
 * Source: https://github.com/Bedriox/NetherNet
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Transport;

use Bedriox\NetherNet\Client\ClientConfiguration;
use Bedriox\NetherNet\Client\ClientPayload;
use Bedriox\NetherNet\Client\NetherNetClient;
use Bedriox\NetherNet\Identity\AssertionIdentityVerifier;
use Bedriox\NetherNet\Identity\SelfSignedIdentityProvider;
use Bedriox\NetherNet\Identity\SelfSignedTokenVerifier;
use Bedriox\NetherNet\Identity\ServerIdentity;
use Bedriox\NetherNet\Protocol\Reliability;
use Bedriox\NetherNet\RecordingEventListener;
use Bedriox\NetherNet\RecordingLogger;
use Bedriox\NetherNet\Server\NetherNetServer;
use Bedriox\NetherNet\Server\ServerConfiguration;
use Bedriox\NetherNet\Signaling\Http\HttpSignaling;
use Bedriox\NetherNet\Utils\InternetAddress;
use PHPUnit\Framework\TestCase;

final class WebRtcNativeLoopbackTest extends TestCase
{
    public function testReliableAndUnreliablePayloadsCrossNativeLoopback(): void
    {
        if (!\extension_loaded('webrtc')) {
            self::markTestSkipped('Native loopback requires ext-webrtc.');
        }

        $listener = new RecordingEventListener();
        $logger = new RecordingLogger();
        $server = NetherNetServer::create(
            new ServerConfiguration(
                identityProvider: new SelfSignedIdentityProvider(ServerIdentity::generate()),
                logger: $logger,
                identityVerifier: new AssertionIdentityVerifier(tokenVerifier: new SelfSignedTokenVerifier()),
                maxSessions: 1,
            ),
            $listener,
        );
        $signaling = new HttpSignaling(new InternetAddress('127.0.0.1', 0, 4));
        $server->addSignaling($signaling);
        $server->start();

        $client = NetherNetClient::connect(new ClientConfiguration(
            targetAddress: '127.0.0.1',
            targetPort: $signaling->getBoundAddress()->port,
            networkId: '42',
        ));

        try {
            self::pollUntil(
                static fn(): bool => $client->isReady() && \count($listener->opened) === 1,
                static function () use ($server, $client): void {
                    $server->tick();
                    $client->poll();
                },
                static fn(): string => 'open=' . \count($listener->opened) . ', client=' . self::describeClient($client),
            );

            $client->sendPacket('client reliable');
            $client->sendPacket('client unreliable', Reliability::UNRELIABLE);
            self::pollUntil(
                static fn(): bool => \count($listener->packets) === 2,
                static function () use ($server, $client): void {
                    $server->tick();
                    $client->poll();
                },
                static fn(): string => 'server received=' . \count($listener->packets) . ', client=' . ($client->isClosed() ? 'closed' : 'open'),
            );

            $sessionId = $listener->opened[0][0];
            $server->sendPacket($sessionId, 'server reliable', receiptId: 1);
            $server->sendPacket($sessionId, 'server unreliable', Reliability::UNRELIABLE, receiptId: 2);
            $received = self::emptyPayloadBuffer();
            self::pollUntil(
                static function () use (&$received): bool {
                    return \count($received) === 2;
                },
                static function () use ($server, $client, &$received): void {
                    $server->tick();
                    $client->poll();
                    array_push($received, ...$client->drainReceivedPayloads());
                },
                static function () use (&$received, $listener, $client): string {
                    return 'client received=' . \count($received) . ', acknowledged=' . \count($listener->acks)
                        . ', client=' . ($client->isClosed() ? 'closed' : 'open');
                },
            );

            self::assertSame('42', $listener->opened[0][1]);
            self::assertSame([
                [$sessionId, 'client reliable', Reliability::RELIABLE],
                [$sessionId, 'client unreliable', Reliability::UNRELIABLE],
            ], $listener->packets);
            self::assertSame('server reliable', $received[0]->payload);
            self::assertSame(Reliability::RELIABLE, $received[0]->reliability);
            self::assertSame('server unreliable', $received[1]->payload);
            self::assertSame(Reliability::UNRELIABLE, $received[1]->reliability);
            self::assertSame([[$sessionId, 1], [$sessionId, 2]], $listener->acks);
        } finally {
            $client->close();
            $server->shutdown();
        }
    }

    /**
     * @param \Closure(): bool $complete
     * @param \Closure(): void $poll
     * @param \Closure(): string $describe
     */
    private static function pollUntil(\Closure $complete, \Closure $poll, \Closure $describe): void
    {
        $deadline = hrtime(true) + 15_000_000_000;
        while (!$complete()) {
            if (hrtime(true) >= $deadline) {
                self::fail('Native WebRTC loopback did not complete before its deadline: ' . $describe());
            }
            $poll();
            usleep(1_000);
        }
    }

    /** @return list<ClientPayload> */
    private static function emptyPayloadBuffer(): array
    {
        return [];
    }

    private static function describeClient(NetherNetClient $client): string
    {
        if (!$client->isClosed()) {
            return 'opening';
        }
        $reason = $client->getCloseReason();
        return 'closed:' . ($reason === null ? 'unknown' : $reason->name);
    }
}
