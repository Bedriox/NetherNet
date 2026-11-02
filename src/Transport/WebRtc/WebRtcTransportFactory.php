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

namespace Bedriox\NetherNet\Transport\WebRtc;

use Bedriox\NetherNet\Transport\ConnectionBudgetConfiguration;
use Bedriox\NetherNet\Transport\PeerTransport;
use Bedriox\NetherNet\Transport\PeerTransportFactory;
use Bedriox\NetherNet\Transport\TransportException;

use function count;

use pmmp\webrtc\IceServer;
use pmmp\webrtc\PeerConnection;
use pmmp\webrtc\PeerConnectionOptions;
use pmmp\webrtc\WebRtcException;

final class WebRtcTransportFactory implements PeerTransportFactory
{
    public const DEFAULT_MTU = 1200;
    public const MIN_MTU = 620;
    public const MAX_MTU = 4144;

    /**
     * The connection's own receive queues are given this many times the budget, so that a session over budget is
     * still there to be closed with the reason, rather than the connection dropping what it could not queue.
     */
    private const RECEIVE_QUEUE_MARGIN = 2;

    /**
     * @phpstan-param list<IceServer> $iceServers
     */
    public function __construct(
        private readonly array $iceServers = [],
        private readonly ?string $bindAddress = null,
        private readonly ?int $portRangeBegin = null,
        private readonly ?int $portRangeEnd = null,
        private readonly bool $iceUdpMuxEnabled = false,
        private readonly int $mtu = self::DEFAULT_MTU
    ) {
        if (($portRangeBegin === null) !== ($portRangeEnd === null)) {
            throw new \InvalidArgumentException("Both port range begin and end must be specified together, or both must be null");
        }
        if ($portRangeBegin !== null && ($portRangeBegin < 1 || $portRangeBegin > 65535)) {
            throw new \InvalidArgumentException("Port range start must be between 1 and 65535, got $portRangeBegin");
        }
        if ($portRangeEnd !== null && ($portRangeEnd < 1 || $portRangeEnd > 65535)) {
            throw new \InvalidArgumentException("Port range end must be between 1 and 65535, got $portRangeEnd");
        }
        if ($portRangeBegin !== null && $portRangeEnd !== null && $portRangeBegin > $portRangeEnd) {
            throw new \InvalidArgumentException("Port range start ($portRangeBegin) cannot be greater than end ($portRangeEnd)");
        }
        if ($iceUdpMuxEnabled) {
            foreach ($iceServers as $iceServer) {
                if ($iceServer->isTurn()) {
                    throw new \InvalidArgumentException("TURN servers cannot be used with ICE UDP mux");
                }
            }
        }
        if ($mtu < self::MIN_MTU || $mtu > self::MAX_MTU) {
            throw new \InvalidArgumentException("MTU must be between " . self::MIN_MTU . " and " . self::MAX_MTU . " bytes, got $mtu");
        }
    }

    public function create(ConnectionBudgetConfiguration $budget): PeerTransport
    {
        // Options are created per connection because PeerConnectionOptions setters mutate the instance in place.
        $options = PeerConnectionOptions::create()
            ->setMaxMessageSize($budget->maxMessageSize)
            ->setMaxReceiveQueueSize($budget->maxReceiveQueueSize * self::RECEIVE_QUEUE_MARGIN)
            ->setMaxReceiveQueueMessages($budget->maxReceiveQueueMessages * self::RECEIVE_QUEUE_MARGIN)
            ->setMaxSendQueueSize($budget->maxSendQueueSize)
            ->setMaxPendingDataChannels($budget->maxPendingDataChannels)
            ->setIceTcpEnabled(false)
            ->setIceUdpMuxEnabled($this->iceUdpMuxEnabled)
            ->setMtu($this->mtu);

        if ($this->bindAddress !== null) {
            $options->setBindAddress($this->bindAddress);
        }
        if ($this->portRangeBegin !== null && $this->portRangeEnd !== null) {
            $options->setPortRange($this->portRangeBegin, $this->portRangeEnd);
        }
        if (\count($this->iceServers) > 0) {
            $options->setIceServers(...$this->iceServers);
        }

        try {
            return new WebRtcPeerTransport(new PeerConnection($options));
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }
}
