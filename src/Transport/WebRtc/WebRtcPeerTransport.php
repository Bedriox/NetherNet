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

use Bedriox\NetherNet\Transport\PeerTransport;
use Bedriox\NetherNet\Transport\TransportException;
use Bedriox\NetherNet\Transport\TransportState;
use Bedriox\NetherNet\Utils\InternetAddress;
use pmmp\webrtc\ConnectionState;
use pmmp\webrtc\GatheringState;
use pmmp\webrtc\IceCandidate;
use pmmp\webrtc\PeerConnection;
use pmmp\webrtc\WebRtcException;

/**
 * A closed peer connection throws on every call, so the facts that outlive it (final byte counts, the peer's
 * address, why it ended) are captured as it closes.
 */
final class WebRtcPeerTransport implements PeerTransport
{
    private bool $closed = false;

    private bool $lostConnectivity = false;
    private ?InternetAddress $remoteAddress = null;
    private int $bytesSent = 0;
    private int $bytesReceived = 0;

    public function __construct(
        private readonly PeerConnection $peerConnection
    ) {}

    public function setRemoteOffer(string $sdp): void
    {
        try {
            $this->peerConnection->setRemoteOffer($sdp);
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getLocalDescription(): ?string
    {
        try {
            return $this->peerConnection->getLocalDescription();
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function isGatheringComplete(): bool
    {
        try {
            return $this->peerConnection->getGatheringState() === GatheringState::COMPLETE;
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function addRemoteCandidate(string $candidate): void
    {
        try {
            $this->peerConnection->addRemoteCandidate(IceCandidate::create($candidate));
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function pollLocalCandidates(): array
    {
        try {
            $candidates = [];
            foreach ($this->peerConnection->pollLocalCandidates() as $candidate) {
                $candidates[] = $candidate->getCandidate();
            }

            return $candidates;
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function pollChannels(): array
    {
        try {
            $channels = [];
            foreach ($this->peerConnection->pollDataChannels() as $channel) {
                $channels[] = new WebRtcMessageChannel($channel);
            }

            return $channels;
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getState(): TransportState
    {
        if ($this->closed) {
            return TransportState::CLOSED;
        }

        try {
            return match ($this->peerConnection->getState()) {
                ConnectionState::NOT_STARTED, ConnectionState::CONNECTING => TransportState::CONNECTING,
                ConnectionState::CONNECTED => TransportState::CONNECTED,
                ConnectionState::DISCONNECTED => TransportState::DISCONNECTED,
                ConnectionState::FAILED => TransportState::FAILED,
                ConnectionState::CLOSED => TransportState::CLOSED
            };
        } catch (WebRtcException) {
            return TransportState::CLOSED;
        }
    }

    public function hasLostConnectivity(): bool
    {
        if (!$this->closed) {
            try {
                return $this->peerConnection->getFailureState() === ConnectionState::FAILED;
            } catch (WebRtcException) {
                return false;
            }
        }

        return $this->lostConnectivity;
    }

    public function getRemoteAddress(): ?InternetAddress
    {
        if (!$this->closed) {
            try {
                $address = $this->peerConnection->getRemoteAddress();
            } catch (WebRtcException) {
                $address = null;
            }
            if ($address !== null) {
                $this->remoteAddress = InternetAddress::parse($address) ?? $this->remoteAddress;
            }
        }

        return $this->remoteAddress;
    }

    public function getRoundTripTime(): ?int
    {
        if ($this->closed) {
            return null;
        }

        try {
            return $this->peerConnection->getRoundTripTime();
        } catch (WebRtcException) {
            return null;
        }
    }

    public function getBytesSent(): int
    {
        if (!$this->closed) {
            try {
                $this->bytesSent = $this->peerConnection->getBytesSent();
            } catch (WebRtcException) {
                //keep the last count
            }
        }

        return $this->bytesSent;
    }

    public function getBytesReceived(): int
    {
        if (!$this->closed) {
            try {
                $this->bytesReceived = $this->peerConnection->getBytesReceived();
            } catch (WebRtcException) {
                //keep the last count
            }
        }

        return $this->bytesReceived;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->lostConnectivity = $this->hasLostConnectivity();
        $this->getRemoteAddress();
        $this->getBytesSent();
        $this->getBytesReceived();
        $this->closed = true;

        try {
            $this->peerConnection->close();
        } catch (WebRtcException) {
            //already gone, which is what was asked for
        }
    }
}
