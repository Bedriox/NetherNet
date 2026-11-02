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

use function str_repeat;
use function strlen;

final class PacketSerializer
{
    private const HEADER_SIZE = 18;

    private const PADDING_SIZE = 8;

    private function __construct() {}

    public static function encode(Packet $packet): string
    {
        $body = new BinaryWriter();
        $body->writeUnsignedShortLE($packet->getType()->value);
        $body->writeUnsignedLongLE($packet->senderId);
        $body->writeBytes(str_repeat("\x00", self::PADDING_SIZE));
        $packet->encodeBody($body);

        $payload = new BinaryWriter();
        $payload->writeUnsignedShortLE(\strlen($body->data()) + 2);
        $payload->writeBytes($body->data());

        return DiscoveryCipher::seal($payload->data());
    }

    /**
     * @throws DiscoveryException
     */
    public static function decode(string $frame): Packet
    {
        $payload = DiscoveryCipher::open($frame);
        if (\strlen($payload) < self::HEADER_SIZE + 2) {
            throw new DiscoveryException("Datagram is too short to hold a header");
        }

        $in = new BinaryReader($payload);

        try {
            $in->readUnsignedShortLE();

            $rawType = $in->readUnsignedShortLE();
            $senderId = $in->readUnsignedLongLE();
            $in->readBytes(self::PADDING_SIZE);

            $type = PacketType::tryFrom($rawType);
            if ($type === null) {
                throw new DiscoveryException("Unknown packet type $rawType");
            }

            $packet = match ($type) {
                PacketType::REQUEST => new RequestPacket($senderId),
                PacketType::RESPONSE => ResponsePacket::decodeBody($senderId, $in),
                PacketType::MESSAGE => MessagePacket::decodeBody($senderId, $in)
            };

            if ($in->unreadLength() !== 0) {
                throw new DiscoveryException("Datagram has " . $in->unreadLength() . " unread bytes remaining");
            }
        } catch (BinaryDecodeException $e) {
            throw new DiscoveryException("Unexpected end of datagram payload: " . $e->getMessage(), 0, $e);
        }

        return $packet;
    }
}
