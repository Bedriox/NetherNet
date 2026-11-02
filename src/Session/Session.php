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

namespace Bedriox\NetherNet\Session;

use Bedriox\NetherNet\Protocol\DisconnectReason;
use Bedriox\NetherNet\Protocol\Framing\Assembler;
use Bedriox\NetherNet\Protocol\Framing\FramingException;
use Bedriox\NetherNet\Protocol\Framing\Segmenter;
use Bedriox\NetherNet\Protocol\Reliability;
use Bedriox\NetherNet\Transport\ChannelPair;
use Bedriox\NetherNet\Transport\ConnectionBudgetConfiguration;
use Bedriox\NetherNet\Transport\PeerTransport;
use Bedriox\NetherNet\Transport\TransportException;
use Bedriox\NetherNet\Transport\TransportState;
use Bedriox\NetherNet\Utils\InternetAddress;

use function count;
use function strlen;

/**
 * @internal
 */
final class Session
{
    /** Timeout in seconds for a graceful disconnect before the session is forcibly closed. */
    public const DISCONNECT_TIMEOUT = 3.0;

    /** @phpstan-var array<string, Assembler> keyed by Reliability case name */
    private readonly array $assemblers;

    private SessionState $state = SessionState::CONNECTED;
    /** Why the session is disconnecting or has disconnected. */
    private ?DisconnectReason $disconnectReason = null;

    private int $reportedBytesSent = 0;
    private int $reportedBytesReceived = 0;
    private ?int $reportedPing = null;

    /** @phpstan-var array<string, int> bytes handed to each channel so far, keyed by Reliability case name */
    private array $queuedBytes = [];

    /**
     * Receipts not yet acknowledged, oldest first, each with the queued byte count its packet ended at.
     * @phpstan-var array<string, ReceiptQueue>
     */
    private array $pendingReceipts = [];

    private int $pendingReceiptCount = 0;

    /** Measured from the first update that finds the disconnect pending. */
    private ?float $disconnectDeadline = null;
    /** Whether a pending disconnect waits for the peer to close, not only for queued data to leave. */
    private bool $awaitPeerClose = true;

    public function __construct(
        private readonly int $id,
        private readonly PeerTransport $transport,
        private readonly ChannelPair $channels,
        private readonly InternetAddress $address,
        private readonly ?string $clientAddress,
        private readonly Segmenter $segmenter,
        private readonly ConnectionBudgetConfiguration $budget
    ) {
        $assemblers = [];
        foreach (Reliability::cases() as $reliability) {
            $assemblers[$reliability->name] = new Assembler($reliability->isFragmentationSupported(), $budget->maxPayloadSize);
            $this->queuedBytes[$reliability->name] = 0;
            $this->pendingReceipts[$reliability->name] = new ReceiptQueue();
        }
        $this->assemblers = $assemblers;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRemoteAddress(): InternetAddress
    {
        return $this->address;
    }

    public function getClientAddress(): ?string
    {
        return $this->clientAddress;
    }

    public function isConnected(): bool
    {
        return $this->state === SessionState::CONNECTED;
    }

    public function isFullyDisconnected(): bool
    {
        return $this->state === SessionState::DISCONNECTED;
    }

    public function getDisconnectReason(): ?DisconnectReason
    {
        return $this->disconnectReason;
    }

    /**
     * Queues a packet for the peer. Packets sent after a disconnect was initiated are dropped.
     * @throws \InvalidArgumentException
     * @throws SessionException
     */
    public function send(string $payload, Reliability $reliability = Reliability::RELIABLE, ?int $receiptId = null): void
    {
        if ($this->state === SessionState::DISCONNECT_PENDING) {
            return;
        }
        $this->requireOpen();

        try {
            $segments = $this->segmenter->segment($payload);
        } catch (FramingException $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }
        if (\count($segments) > 1 && !$reliability->isFragmentationSupported()) {
            throw new \InvalidArgumentException("Payload requires " . \count($segments) . " segments, but " . $reliability->getChannelLabel() . " does not support fragmentation");
        }

        try {
            $channel = $this->channels->get($reliability);
            foreach ($segments as $segment) {
                $channel->send($segment);
                $this->queuedBytes[$reliability->name] += \strlen($segment);
            }
        } catch (TransportException $e) {
            $this->forciblyDisconnect(DisconnectReason::SEND_FAILED);

            throw new SessionException("Failed to send on channel " . $reliability->getChannelLabel() . ": " . $e->getMessage(), 0, $e);
        }

        if ($receiptId !== null) {
            $this->pendingReceipts[$reliability->name]->enqueue([$receiptId, $this->queuedBytes[$reliability->name]]);
            ++$this->pendingReceiptCount;
        }
    }

    /**
     * @return int[]
     * @phpstan-return list<int>
     */
    public function takeAcknowledgedReceipts(): array
    {
        if ($this->state === SessionState::DISCONNECTED || $this->pendingReceiptCount === 0) {
            return [];
        }

        $acknowledged = [];
        foreach (Reliability::cases() as $reliability) {
            $pending = $this->pendingReceipts[$reliability->name];
            if ($pending->isEmpty()) {
                continue;
            }

            $delivered = $this->queuedBytes[$reliability->name] - $this->channels->get($reliability)->getBufferedAmount();
            while (!$pending->isEmpty()) {
                [$receiptId, $watermark] = $pending->bottom();
                if ($watermark > $delivered) {
                    break;
                }
                $pending->dequeue();
                --$this->pendingReceiptCount;
                $acknowledged[] = $receiptId;
            }
        }

        return $acknowledged;
    }

    /**
     * @throws SessionException
     */
    public function receive(): ?ReceivedMessage
    {
        $this->requireOpen();

        foreach (Reliability::cases() as $reliability) {
            $channel = $this->channels->get($reliability);
            $assembler = $this->assemblers[$reliability->name];

            try {
                while (($segment = $channel->receive()) !== null) {
                    $payload = $assembler->accept($segment);
                    if ($payload !== null) {
                        return new ReceivedMessage($reliability, $payload);
                    }
                }
            } catch (FramingException|TransportException $e) {
                $this->forciblyDisconnect(DisconnectReason::BAD_DATA);

                throw new SessionException("Bad data on channel " . $reliability->getChannelLabel() . ": " . $e->getMessage(), 0, $e);
            }
        }

        return null;
    }

    /**
     * @phpstan-return array{int, int} bytes sent and received since the last call
     */
    public function takeBandwidth(): array
    {
        $sent = $this->transport->getBytesSent();
        $received = $this->transport->getBytesReceived();
        $diff = [$sent - $this->reportedBytesSent, $received - $this->reportedBytesReceived];
        $this->reportedBytesSent = $sent;
        $this->reportedBytesReceived = $received;

        return $diff;
    }

    /**
     * Returns the round trip time in milliseconds if it differs from what the last call returned.
     */
    public function takePingChange(): ?int
    {
        $ping = $this->transport->getRoundTripTime();
        if ($ping === null || $ping === $this->reportedPing) {
            return null;
        }

        return $this->reportedPing = $ping;
    }

    public function update(float $now): void
    {
        if ($this->state === SessionState::DISCONNECTED) {
            return;
        }

        $transportState = $this->transport->getState();
        if ($transportState === TransportState::FAILED) {
            $this->forciblyDisconnect(DisconnectReason::CONNECTION_FAILED);

            return;
        }

        if ($this->state === SessionState::DISCONNECT_PENDING) {
            $this->disconnectDeadline ??= $now + self::DISCONNECT_TIMEOUT;
            /* An empty send buffer does not mean the peer has handled the data (e.g. a transfer), so let it close first. */
            $completed = $this->awaitPeerClose ? $this->hasClosedChannel() : $this->bufferedBytes() === 0;
            $channelFailed = !$this->discardIncoming();
            if ($completed || $channelFailed || $transportState !== TransportState::CONNECTED || $now >= $this->disconnectDeadline) {
                $this->forciblyDisconnect($this->disconnectReason ?? DisconnectReason::SERVER_DISCONNECT);
            }

            return;
        }

        /* Check queue limits before channel closures so flooding is reported accurately. */
        $queuedBytes = 0;
        $queuedMessages = 0;
        foreach ($this->channels->all() as $channel) {
            $queuedBytes += $channel->getAvailableAmount();
            $queuedMessages += $channel->getQueuedMessageCount();
        }
        if ($queuedBytes > $this->budget->maxReceiveQueueSize) {
            $this->forciblyDisconnect(DisconnectReason::RECEIVE_QUEUE_TOO_MANY_BYTES);

            return;
        }
        if ($queuedMessages > $this->budget->maxReceiveQueueMessages) {
            $this->forciblyDisconnect(DisconnectReason::RECEIVE_QUEUE_TOO_MANY_MESSAGES);

            return;
        }
        if ($this->bufferedBytes() > $this->budget->maxSendQueueSize) {
            $this->forciblyDisconnect(DisconnectReason::SEND_QUEUE_TOO_MANY_BYTES);

            return;
        }

        if ($transportState === TransportState::CLOSED || $this->hasClosedChannel()) {
            $this->forciblyDisconnect($this->getTransportDisconnectReason());
        }
    }

    /**
     * Discards all incoming data on every channel while the peer is disconnecting. Returns false if any channel encounters a transport error.
     */
    private function discardIncoming(): bool
    {
        foreach ($this->channels->all() as $channel) {
            try {
                while ($channel->receive() !== null);
            } catch (TransportException) {
                return false;
            }
        }

        return true;
    }

    private function hasClosedChannel(): bool
    {
        foreach ($this->channels->all() as $channel) {
            if ($channel->isClosed()) {
                return true;
            }
        }

        return false;
    }

    private function getTransportDisconnectReason(): DisconnectReason
    {
        return $this->transport->hasLostConnectivity() ? DisconnectReason::CONNECTION_FAILED : DisconnectReason::PEER_DISCONNECT;
    }

    /**
     * @param bool $awaitPeerClose false to close once queued data has left, e.g. for a blocked peer
     */
    public function initiateDisconnect(DisconnectReason $reason, bool $awaitPeerClose = true): void
    {
        if ($this->state !== SessionState::CONNECTED) {
            return;
        }

        $this->state = SessionState::DISCONNECT_PENDING;
        $this->disconnectReason = $reason;
        $this->awaitPeerClose = $awaitPeerClose;
    }

    private function bufferedBytes(): int
    {
        $buffered = 0;
        foreach ($this->channels->all() as $channel) {
            $buffered += $channel->getBufferedAmount();
        }

        return $buffered;
    }

    /** Closes the transport immediately, discarding anything still queued. The first reason given is the one kept. */
    public function forciblyDisconnect(DisconnectReason $reason): void
    {
        if ($this->state === SessionState::DISCONNECTED) {
            return;
        }
        $this->state = SessionState::DISCONNECTED;
        $this->disconnectReason = $reason;

        foreach ($this->channels->all() as $channel) {
            $channel->close();
        }
        $this->transport->close();
    }

    /**
     * @throws SessionException
     */
    private function requireOpen(): void
    {
        if ($this->state === SessionState::DISCONNECTED) {
            throw new SessionException("Session is closed" . ($this->disconnectReason !== null ? ": " . $this->disconnectReason->getMessage() : ""));
        }
        if ($this->hasClosedChannel()) {
            $this->forciblyDisconnect($this->getTransportDisconnectReason());

            throw new SessionException("Data channel of the session is closed");
        }
    }
}
