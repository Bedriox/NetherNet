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

use function bin2hex;
use function ctype_xdigit;
use function hex2bin;
use function strlen;

final class ResponsePacket extends Packet
{
    private const MAX_ENCODED_LENGTH = 65535;

    public function __construct(
        int $senderId,
        public readonly string $applicationData
    ) {
        parent::__construct($senderId);
    }

    public function getType(): PacketType
    {
        return PacketType::RESPONSE;
    }

    /**
     * @throws BinaryDecodeException
     * @throws DiscoveryException
     */
    public static function decodeBody(int $senderId, BinaryReader $in): self
    {
        $length = $in->readUnsignedIntLE();
        if ($length > self::MAX_ENCODED_LENGTH) {
            throw new DiscoveryException("Invalid data length $length (max " . self::MAX_ENCODED_LENGTH . ")");
        }

        $hex = $in->readBytes($length);
        /* hex2bin() raises a warning for input it cannot decode, so that is ruled out before it is asked. */
        $decoded = $length % 2 === 0 && ($hex === "" || ctype_xdigit($hex)) ? hex2bin($hex) : false;
        if ($decoded === false) {
            throw new DiscoveryException("Invalid hex data");
        }

        return new self($senderId, $decoded);
    }

    public function encodeBody(BinaryWriter $out): void
    {
        $hex = bin2hex($this->applicationData);
        $out->writeUnsignedIntLE(\strlen($hex));
        $out->writeBytes($hex);
    }
}
