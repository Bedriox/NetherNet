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

namespace Bedriox\NetherNet\Signaling\Lan\Packet;

use Bedriox\NetherNet\Protocol\BinaryDecodeException;
use Bedriox\NetherNet\Protocol\BinaryReader;
use Bedriox\NetherNet\Protocol\BinaryWriter;

use function strlen;

final class MessagePacket extends Packet
{
    private const MAX_DATA_LENGTH = 65535;

    public function __construct(
        int $senderId,
        public readonly int $recipientId,
        public readonly string $data
    ) {
        parent::__construct($senderId);
    }

    public function getType(): PacketType
    {
        return PacketType::MESSAGE;
    }

    /**
     * @throws BinaryDecodeException
     * @throws DiscoveryException
     */
    public static function decodeBody(int $senderId, BinaryReader $in): self
    {
        $recipientId = $in->readUnsignedLongLE();

        $length = $in->readUnsignedIntLE();
        if ($length > self::MAX_DATA_LENGTH) {
            throw new DiscoveryException("Message of $length bytes is larger than a datagram can carry");
        }

        // Vanilla packets can carry more than the length they declare; all of it is the message
        return new self($senderId, $recipientId, $in->readBytes($length) . $in->readBytes($in->unreadLength()));
    }

    public function encodeBody(BinaryWriter $out): void
    {
        $out->writeUnsignedLongLE($this->recipientId);
        $out->writeUnsignedIntLE(\strlen($this->data));
        $out->writeBytes($this->data);
    }
}
