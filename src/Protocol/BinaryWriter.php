<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Protocol;

final class BinaryWriter
{
    private string $data = '';

    public function data(): string
    {
        return $this->data;
    }

    public function writeBytes(string $bytes): void
    {
        $this->data .= $bytes;
    }

    public function writeUnsignedByte(int $value): void
    {
        self::requireRange($value, 0xff, 'byte');
        $this->data .= pack('C', $value);
    }

    public function writeUnsignedShortLE(int $value): void
    {
        self::requireRange($value, 0xffff, 'unsigned short');
        $this->data .= pack('v', $value);
    }

    public function writeUnsignedIntLE(int $value): void
    {
        self::requireRange($value, 0xffff_ffff, 'unsigned int');
        $this->data .= pack('V', $value);
    }

    public function writeUnsignedLongLE(int $value): void
    {
        $this->data .= pack('P', $value);
    }

    public function writeUnsignedVarInt(int $value): void
    {
        self::requireRange($value, 0xffff_ffff, 'unsigned VarInt');
        do {
            $byte = $value & 0x7f;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $this->writeUnsignedByte($byte);
        } while ($value !== 0);
    }

    public function writeSignedVarInt(int $value): void
    {
        if ($value < -0x8000_0000 || $value > 0x7fff_ffff) {
            throw new \InvalidArgumentException('Signed VarInt is outside the 32-bit range.');
        }
        $this->writeUnsignedVarInt((($value << 1) ^ ($value >> 31)) & 0xffff_ffff);
    }

    private static function requireRange(int $value, int $maximum, string $name): void
    {
        if ($value < 0 || $value > $maximum) {
            throw new \InvalidArgumentException(ucfirst($name) . ' is outside its supported range.');
        }
    }
}
