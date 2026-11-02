<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Protocol;

final class BinaryReader
{
    private int $offset = 0;

    public function __construct(private readonly string $data) {}

    public function unreadLength(): int
    {
        return \strlen($this->data) - $this->offset;
    }

    public function readBytes(int $length): string
    {
        if ($length < 0 || $length > $this->unreadLength()) {
            throw new BinaryDecodeException('Not enough bytes remain in the buffer.');
        }
        $result = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        return $result;
    }

    public function readUnsignedByte(): int
    {
        return \ord($this->readBytes(1));
    }

    public function readUnsignedShortLE(): int
    {
        return self::unpackInteger('vvalue', $this->readBytes(2));
    }

    public function readUnsignedIntLE(): int
    {
        return self::unpackInteger('Vvalue', $this->readBytes(4));
    }

    public function readUnsignedLongLE(): int
    {
        return self::unpackInteger('Pvalue', $this->readBytes(8));
    }

    public function readUnsignedVarInt(): int
    {
        $value = 0;
        for ($shift = 0; $shift <= 28; $shift += 7) {
            $byte = $this->readUnsignedByte();
            if ($shift === 28 && ($byte & 0xf0) !== 0) {
                throw new BinaryDecodeException('Unsigned VarInt exceeds the 32-bit range.');
            }
            $value |= ($byte & 0x7f) << $shift;
            if (($byte & 0x80) === 0) {
                return $value;
            }
        }

        throw new BinaryDecodeException('Unsigned VarInt exceeds five bytes.');
    }

    public function readSignedVarInt(): int
    {
        $encoded = $this->readUnsignedVarInt();

        return ($encoded >> 1) ^ (-($encoded & 1));
    }

    private static function unpackInteger(string $format, string $bytes): int
    {
        $result = unpack($format, $bytes);
        $value = \is_array($result) ? ($result['value'] ?? null) : null;
        if (!\is_int($value)) {
            throw new BinaryDecodeException('Could not decode the requested integer.');
        }

        return $value;
    }
}
