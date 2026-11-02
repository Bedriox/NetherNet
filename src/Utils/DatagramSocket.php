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

namespace Bedriox\NetherNet\Utils;

use const AF_INET;
use const AF_INET6;
use const IPPROTO_IPV6;
use const IPV6_V6ONLY;
use const SO_BROADCAST;
use const SO_REUSEADDR;
use const SOCK_DGRAM;

use function socket_bind;
use function socket_clear_error;
use function socket_close;
use function socket_create;

use const SOCKET_ECONNRESET;
use const SOCKET_EWOULDBLOCK;

use function socket_getsockname;
use function socket_last_error;
use function socket_recvfrom;
use function socket_sendto;
use function socket_set_nonblock;
use function socket_set_option;

use const SOL_SOCKET;
use const SOL_UDP;

use function strlen;

/**
 * @internal
 * Non-blocking bound UDP socket.
 */
final class DatagramSocket
{
    private const MAX_DATAGRAM_SIZE = 65535;

    private readonly \Socket $socket;
    private readonly InternetAddress $bindAddress;
    private bool $closed = false;

    /**
     * @param InternetAddress $address Address to bind; port 0 lets the system choose one.
     *
     * @throws SocketException
     */
    public function __construct(InternetAddress $address, bool $broadcast = false)
    {
        $ipv6 = $address->version === 6;
        $socket = @socket_create($ipv6 ? AF_INET6 : AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            throw SocketException::fromErrno("Could not create a UDP socket", socket_last_error());
        }

        if ($broadcast) {
            @socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        }
        @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if ($ipv6) {
            @socket_set_option($socket, IPPROTO_IPV6, IPV6_V6ONLY, 1);
        }

        if (!@socket_bind($socket, $address->ip, $address->port)) {
            $errno = socket_last_error($socket);
            socket_close($socket);

            throw SocketException::fromErrno("Could not bind to $address", $errno);
        }

        socket_set_nonblock($socket);
        $this->socket = $socket;

        $ip = "";
        $port = 0;
        if (@socket_getsockname($socket, $ip, $port) && \is_string($ip) && \is_int($port)) {
            $this->bindAddress = new InternetAddress($ip, $port, $address->version);
        } else {
            $this->bindAddress = $address;
        }
    }

    /**
     * Returns the address actually bound, with the port the system chose if none was asked for.
     */
    public function getBindAddress(): InternetAddress
    {
        return $this->bindAddress;
    }

    /**
     * Takes the next waiting datagram, or returns null if there is none.
     *
     * @phpstan-return array{string, InternetAddress}|null the datagram and where it came from
     *
     * @throws SocketException
     */
    public function receive(): ?array
    {
        while (true) {
            $buffer = "";
            $ip = "";
            $port = 0;
            if (@socket_recvfrom($this->socket, $buffer, self::MAX_DATAGRAM_SIZE, 0, $ip, $port) !== false) {
                if (!\is_string($buffer) || !\is_string($ip) || !\is_int($port)) {
                    throw new SocketException("Read returned invalid address or payload data");
                }
                return [$buffer, new InternetAddress($ip, $port, $this->bindAddress->version)];
            }

            $errno = socket_last_error($this->socket);
            socket_clear_error($this->socket);
            if ($errno === 0 || $errno === SOCKET_EWOULDBLOCK) {
                return null;
            }
            /* Windows reports an ICMP port-unreachable for something sent earlier as a failed read; the next datagram is unaffected. */
            if ($errno !== SOCKET_ECONNRESET) {
                throw SocketException::fromErrno("Read failed", $errno);
            }
        }
    }

    /**
     * @throws SocketException
     */
    public function send(string $datagram, InternetAddress $destination): void
    {
        if (@socket_sendto($this->socket, $datagram, \strlen($datagram), 0, $destination->ip, $destination->port) === false) {
            $errno = socket_last_error($this->socket);
            socket_clear_error($this->socket);

            throw SocketException::fromErrno("Could not send to $destination", $errno);
        }
    }

    public function close(): void
    {
        if (!$this->closed) {
            $this->closed = true;
            socket_close($this->socket);
        }
    }
}
