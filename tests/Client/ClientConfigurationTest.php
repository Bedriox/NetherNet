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

namespace Bedriox\NetherNet\Tests\Client;

use Bedriox\NetherNet\Client\ClientConfiguration;
use Bedriox\NetherNet\Client\NetherNetClient;
use Bedriox\NetherNet\NetherNetException;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientConfigurationTest extends TestCase
{
    public function testAcceptsCanonicalNetworkIdentifierAndBoundedDefaults(): void
    {
        $config = new ClientConfiguration('127.0.0.1', 19132, '18446744073709551615');

        self::assertSame('18446744073709551615', $config->networkId);
        self::assertSame(15_000, $config->handshakeTimeoutMilliseconds);
        self::assertSame(30_000, $config->idleTimeoutMilliseconds);
    }

    /** @param Closure(): ClientConfiguration $create */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(Closure $create): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $create();
    }

    /** @return iterable<string, array{Closure(): ClientConfiguration}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'hostname' => [static fn(): ClientConfiguration => new ClientConfiguration('localhost', 19132, '1')];
        yield 'zero port' => [static fn(): ClientConfiguration => new ClientConfiguration('127.0.0.1', 0, '1')];
        yield 'signed network id' => [static fn(): ClientConfiguration => new ClientConfiguration('127.0.0.1', 19132, '-1')];
        yield 'overflow network id' => [static fn(): ClientConfiguration => new ClientConfiguration('127.0.0.1', 19132, '18446744073709551616')];
        yield 'small mtu' => [static fn(): ClientConfiguration => new ClientConfiguration('127.0.0.1', 19132, '1', '0.0.0.0', 575)];
    }

    public function testClientFailsClearlyWithoutNativeBackend(): void
    {
        if (\extension_loaded('webrtc')) {
            self::markTestSkipped('This contract covers installations without ext-webrtc.');
        }
        $this->expectException(NetherNetException::class);
        $this->expectExceptionMessage('ext-webrtc');
        NetherNetClient::connect(new ClientConfiguration('127.0.0.1', 19132, '1'));
    }
}
