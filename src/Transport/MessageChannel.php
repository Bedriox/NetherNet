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

namespace Bedriox\NetherNet\Transport;

use Bedriox\NetherNet\Protocol\Reliability;

interface MessageChannel
{
    public function getLabel(): string;

    /**
     * Returns null if the channel's label or delivery guarantees match neither NetherNet channel.
     */
    public function getReliability(): ?Reliability;

    public function isOpen(): bool;

    public function isClosed(): bool;

    public function getMaxMessageSize(): int;

    /**
     * @throws TransportException
     */
    public function send(string $message): void;

    /**
     * @throws TransportException
     */
    public function receive(): ?string;

    /**
     * Returns the bytes queued for sending that have not left yet.
     */
    public function getBufferedAmount(): int;

    /**
     * Returns the bytes received and not yet taken with receive().
     */
    public function getAvailableAmount(): int;

    public function getQueuedMessageCount(): int;

    /**
     * Never throws.
     */
    public function close(): void;
}
