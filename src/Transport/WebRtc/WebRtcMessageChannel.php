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

use Bedriox\NetherNet\Protocol\Reliability;
use Bedriox\NetherNet\Transport\MessageChannel;
use Bedriox\NetherNet\Transport\TransportException;
use pmmp\webrtc\DataChannel;
use pmmp\webrtc\WebRtcException;

final class WebRtcMessageChannel implements MessageChannel
{
    public function __construct(
        private readonly DataChannel $channel
    ) {}

    public function getLabel(): string
    {
        return $this->channel->getLabel();
    }

    public function getReliability(): ?Reliability
    {
        $channel = $this->channel;
        if ($channel->getProtocol() !== "" || $channel->getMaxPacketLifeTime() !== null) {
            return null;
        }

        $reliability = Reliability::fromChannelLabel($channel->getLabel());

        return match (true) {
            $reliability === Reliability::RELIABLE && !$channel->isUnordered() && $channel->getMaxRetransmits() === null,
            $reliability === Reliability::UNRELIABLE && $channel->isUnordered() && $channel->getMaxRetransmits() === 0 => $reliability,
            default => null
        };
    }

    public function isOpen(): bool
    {
        return $this->channel->isOpen();
    }

    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    public function getMaxMessageSize(): int
    {
        return $this->channel->getMaxMessageSize();
    }

    public function send(string $message): void
    {
        try {
            $this->channel->send($message);
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function receive(): ?string
    {
        try {
            return $this->channel->receive();
        } catch (WebRtcException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getBufferedAmount(): int
    {
        return $this->channel->getBufferedAmount();
    }

    public function getAvailableAmount(): int
    {
        return $this->channel->getAvailableAmount();
    }

    public function getQueuedMessageCount(): int
    {
        return $this->channel->getQueuedMessageCount();
    }

    public function close(): void
    {
        try {
            $this->channel->close();
        } catch (WebRtcException) {
            //already gone, which is what was asked for
        }
    }
}
