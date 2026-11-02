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

namespace Bedriox\NetherNet\Client;

use Bedriox\NetherNet\Identity\AssertionIdentityVerifier;
use Bedriox\NetherNet\Identity\SelfSignedIdentityProvider;
use Bedriox\NetherNet\Identity\SelfSignedTokenVerifier;
use Bedriox\NetherNet\Identity\ServerIdentity;
use Bedriox\NetherNet\NetherNetException;
use Bedriox\NetherNet\Protocol\Framing\Assembler;
use Bedriox\NetherNet\Protocol\Framing\Segmenter;
use Bedriox\NetherNet\Protocol\Reliability;
use Bedriox\NetherNet\Sdp\SessionDescription;
use Bedriox\NetherNet\Transport\WebRtc\WebRtcMessageChannel;
use pmmp\webrtc\ConnectionState;
use pmmp\webrtc\DataChannel;
use pmmp\webrtc\DataChannelOptions;
use pmmp\webrtc\GatheringState;
use pmmp\webrtc\PeerConnection;
use pmmp\webrtc\PeerConnectionOptions;
use pmmp\webrtc\WebRtcException;
use Throwable;

/** Poll-driven bounded NetherNet client for application and load-test consumers. */
final class NetherNetClient
{
    private readonly PeerConnection $peer;
    private readonly WebRtcMessageChannel $reliable;
    private readonly WebRtcMessageChannel $unreliable;
    private readonly Segmenter $segmenter;
    private readonly Assembler $reliableAssembler;
    private readonly Assembler $unreliableAssembler;

    /** @var resource|null */
    private mixed $signalingStream = null;
    private string $signalingRequest = '';
    private int $signalingRequestOffset = 0;
    private string $signalingResponse = '';
    private ?int $signalingResponseLength = null;
    private bool $answerApplied = false;
    private bool $ready = false;
    private bool $closed = false;
    private ?ClientCloseReason $closeReason = null;
    private readonly int $startedAtNanoseconds;
    private int $lastActivityNanoseconds;

    /** @var list<ClientPayload> */
    private array $receivedPayloads = [];

    private function __construct(
        private readonly ClientConfiguration $config,
        PeerConnection $peer,
        DataChannel $reliable,
        DataChannel $unreliable,
    ) {
        $this->peer = $peer;
        $this->reliable = new WebRtcMessageChannel($reliable);
        $this->unreliable = new WebRtcMessageChannel($unreliable);
        $this->segmenter = new Segmenter($config->budget->maxMessageSize - Segmenter::HEADER_SIZE);
        $this->reliableAssembler = new Assembler(true, $config->budget->maxPayloadSize);
        $this->unreliableAssembler = new Assembler(false, $config->budget->maxPayloadSize);
        $this->startedAtNanoseconds = hrtime(true);
        $this->lastActivityNanoseconds = $this->startedAtNanoseconds;
    }

    public static function connect(ClientConfiguration $config): self
    {
        if (!\extension_loaded('webrtc')) {
            throw new NetherNetException('NetherNet clients require ext-webrtc.');
        }
        try {
            $options = PeerConnectionOptions::create()
                ->setMaxMessageSize($config->budget->maxMessageSize)
                ->setMaxReceiveQueueSize($config->budget->maxReceiveQueueSize * 2)
                ->setMaxReceiveQueueMessages($config->budget->maxReceiveQueueMessages * 2)
                ->setMaxSendQueueSize($config->budget->maxSendQueueSize)
                ->setMaxPendingDataChannels($config->budget->maxPendingDataChannels)
                ->setBindAddress($config->bindAddress)
                ->setIceTcpEnabled(false)
                ->setMtu($config->maximumTransmissionUnit);
            $peer = new PeerConnection($options);
            $reliable = $peer->createDataChannel(Reliability::RELIABLE->getChannelLabel());
            $unreliable = $peer->createDataChannel(
                Reliability::UNRELIABLE->getChannelLabel(),
                DataChannelOptions::create()->setUnordered(true)->setMaxRetransmits(0),
            );

            return new self($config, $peer, $reliable, $unreliable);
        } catch (WebRtcException $exception) {
            throw new NetherNetException('Unable to initialize the NetherNet peer connection.', 0, $exception);
        }
    }

    public function poll(int $maximumMessages = 64): int
    {
        if ($maximumMessages < 1 || $maximumMessages > 4_096) {
            throw new \InvalidArgumentException('NetherNet client poll limit is invalid.');
        }
        if ($this->closed) {
            return 0;
        }
        $now = hrtime(true);
        if (!$this->ready && $now - $this->startedAtNanoseconds >= $this->config->handshakeTimeoutMilliseconds * 1_000_000) {
            $this->fail(ClientCloseReason::HANDSHAKE_TIMEOUT);
            return 0;
        }
        if ($this->ready && $now - $this->lastActivityNanoseconds >= $this->config->idleTimeoutMilliseconds * 1_000_000) {
            $this->fail(ClientCloseReason::IDLE_TIMEOUT);
            return 0;
        }
        try {
            if (!$this->answerApplied) {
                $this->pollSignaling();
            }
            if ($this->answerApplied && !$this->ready) {
                $this->pollOpening();
            }
            if (!$this->ready) {
                return 0;
            }
            $processed = $this->pollIncoming($maximumMessages);
            $state = $this->peer->getState();
            if ($state === ConnectionState::FAILED || $state === ConnectionState::DISCONNECTED || $state === ConnectionState::CLOSED) {
                $this->fail(ClientCloseReason::REMOTE_DISCONNECT);
            }
            return $processed;
        } catch (Throwable $exception) {
            $reason = match (true) {
                $exception instanceof ClientFailureException => $exception->reason,
                $exception instanceof WebRtcException => ClientCloseReason::REMOTE_DISCONNECT,
                default => ClientCloseReason::MALFORMED_RESPONSE,
            };
            $this->fail($reason);
            return 0;
        }
    }

    public function sendPacket(string $payload, Reliability $reliability = Reliability::RELIABLE): void
    {
        if (!$this->ready || $this->closed) {
            throw new NetherNetException('Application payloads require an open NetherNet client.');
        }
        $segments = $this->segmenter->segment($payload);
        if (\count($segments) > 1 && !$reliability->isFragmentationSupported()) {
            throw new NetherNetException('Unreliable NetherNet payloads cannot be fragmented.');
        }
        $channel = $reliability === Reliability::RELIABLE ? $this->reliable : $this->unreliable;
        foreach ($segments as $segment) {
            $channel->send($segment);
        }
        $this->lastActivityNanoseconds = hrtime(true);
    }

    /** @return list<ClientPayload> */
    public function drainReceivedPayloads(): array
    {
        $payloads = $this->receivedPayloads;
        $this->receivedPayloads = [];
        return $payloads;
    }

    public function isReady(): bool
    {
        return $this->ready && !$this->closed;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function getCloseReason(): ?ClientCloseReason
    {
        return $this->closeReason;
    }

    public function localPort(): int
    {
        $address = $this->peer->getLocalAddress();
        if ($address === null || preg_match('/:(\d+)$/', $address, $matches) !== 1) {
            return 0;
        }
        return (int) $matches[1];
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->closeReason ??= ClientCloseReason::LOCAL_CLOSE;
        if (\is_resource($this->signalingStream)) {
            fclose($this->signalingStream);
        }
        $this->signalingStream = null;
        try {
            $this->reliable->close();
            $this->unreliable->close();
            $this->peer->close();
        } catch (WebRtcException) {
        }
    }

    private function pollSignaling(): void
    {
        if ($this->signalingStream === null) {
            if ($this->peer->getGatheringState() !== GatheringState::COMPLETE) {
                return;
            }
            $description = $this->peer->getLocalDescription();
            if ($description === null) {
                return;
            }
            $offer = SessionDescription::parse($description);
            $offer->validate();
            $identity = new SelfSignedIdentityProvider(ServerIdentity::generate());
            $body = $offer->withIdentity($identity->issue($offer->getFingerprint())->encode())->toString();
            $this->signalingRequest = "POST /v1/join/{$this->config->networkId} HTTP/1.1\r\n"
                . "Host: {$this->config->targetAddress}:{$this->config->targetPort}\r\n"
                . "Content-Type: application/sdp\r\n"
                . 'Content-Length: ' . \strlen($body) . "\r\n"
                . "Connection: close\r\n\r\n"
                . $body;
            $stream = @stream_socket_client(
                "tcp://{$this->config->targetAddress}:{$this->config->targetPort}",
                $errorCode,
                $errorMessage,
                0.0,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
            );
            if ($stream === false) {
                throw new ClientFailureException(
                    ClientCloseReason::SIGNALING_REJECTED,
                    "Unable to connect to NetherNet signaling: $errorMessage",
                );
            }
            stream_set_blocking($stream, false);
            $this->signalingStream = $stream;
        }
        $stream = $this->signalingStream;
        if ($this->signalingRequestOffset < \strlen($this->signalingRequest)) {
            $written = @fwrite($stream, substr($this->signalingRequest, $this->signalingRequestOffset));
            if ($written === false) {
                throw new NetherNetException('Unable to write the NetherNet signaling request.');
            }
            $this->signalingRequestOffset += $written;
        }
        while (($chunk = @fread($stream, 65_536)) !== false && $chunk !== '') {
            $this->signalingResponse .= $chunk;
            if (\strlen($this->signalingResponse) > $this->config->maximumHttpResponseBytes) {
                throw new NetherNetException('NetherNet signaling response exceeded its size limit.');
            }
        }
        $this->discoverResponseLength();
        if ($this->signalingResponseLength === null || \strlen($this->signalingResponse) < $this->signalingResponseLength) {
            return;
        }
        [$head, $body] = explode("\r\n\r\n", $this->signalingResponse, 2);
        if (preg_match('/^HTTP\/1\.[01] 200(?: |\r?$)/', $head) !== 1) {
            throw new ClientFailureException(ClientCloseReason::SIGNALING_REJECTED, 'NetherNet signaling refused the offer.');
        }
        if (preg_match('/\r\nContent-Type:\s*application\/sdp(?:\s*;[^\r\n]*)?\r\n/i', "\r\n$head\r\n") !== 1) {
            throw new NetherNetException('NetherNet signaling returned an unexpected content type.');
        }
        $answer = SessionDescription::parse($body);
        $answer->validate();
        try {
            new AssertionIdentityVerifier(tokenVerifier: new SelfSignedTokenVerifier())
                ->verify($answer->getIdentity(), $answer->getFingerprint());
        } catch (Throwable $exception) {
            throw new ClientFailureException(
                ClientCloseReason::IDENTITY_REJECTED,
                'NetherNet signaling identity verification failed.',
                $exception,
            );
        }
        $this->peer->setRemoteAnswer($answer->toString());
        fclose($stream);
        $this->signalingStream = null;
        $this->answerApplied = true;
        $this->lastActivityNanoseconds = hrtime(true);
    }

    private function discoverResponseLength(): void
    {
        if ($this->signalingResponseLength !== null) {
            return;
        }
        $headEnd = strpos($this->signalingResponse, "\r\n\r\n");
        if ($headEnd === false) {
            return;
        }
        $head = substr($this->signalingResponse, 0, $headEnd);
        if (preg_match('/\r\nContent-Length:\s*(\d+)\r\n/i', "\r\n$head\r\n", $matches) !== 1) {
            throw new NetherNetException('NetherNet signaling response omitted Content-Length.');
        }
        $total = $headEnd + 4 + (int) $matches[1];
        if ($total > $this->config->maximumHttpResponseBytes) {
            throw new NetherNetException('NetherNet signaling response declared an excessive body.');
        }
        $this->signalingResponseLength = $total;
    }

    private function pollOpening(): void
    {
        if ($this->peer->getState() !== ConnectionState::CONNECTED || !$this->reliable->isOpen() || !$this->unreliable->isOpen()) {
            return;
        }
        if ($this->reliable->getReliability() !== Reliability::RELIABLE
            || $this->unreliable->getReliability() !== Reliability::UNRELIABLE) {
            throw new NetherNetException('NetherNet opened data channels with an invalid reliability contract.');
        }
        $this->ready = true;
        $this->lastActivityNanoseconds = hrtime(true);
    }

    private function pollIncoming(int $maximumMessages): int
    {
        $processed = 0;
        foreach ([
            [Reliability::RELIABLE, $this->reliable, $this->reliableAssembler],
            [Reliability::UNRELIABLE, $this->unreliable, $this->unreliableAssembler],
        ] as [$reliability, $channel, $assembler]) {
            while ($processed < $maximumMessages && ($segment = $channel->receive()) !== null) {
                ++$processed;
                $payload = $assembler->accept($segment);
                if ($payload !== null) {
                    $this->receivedPayloads[] = new ClientPayload($payload, $reliability);
                }
                $this->lastActivityNanoseconds = hrtime(true);
            }
        }
        return $processed;
    }

    private function fail(ClientCloseReason $reason): void
    {
        $this->closeReason = $reason;
        $this->close();
    }
}
