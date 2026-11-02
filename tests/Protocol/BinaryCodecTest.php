<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Tests\Protocol;

use Bedriox\NetherNet\Protocol\BinaryDecodeException;
use Bedriox\NetherNet\Protocol\BinaryReader;
use Bedriox\NetherNet\Protocol\BinaryWriter;

use function hex2bin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BinaryCodecTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unsignedVarIntVectors(): iterable
    {
        yield 'zero' => [0, '00'];
        yield 'one-byte maximum' => [127, '7f'];
        yield 'two-byte minimum' => [128, '8001'];
        yield 'protocol 2225' => [2225, 'b111'];
        yield 'unsigned maximum' => [0xffff_ffff, 'ffffffff0f'];
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function signedVarIntVectors(): iterable
    {
        yield 'zero' => [0, '00'];
        yield 'negative one' => [-1, '01'];
        yield 'positive one' => [1, '02'];
        yield 'signed maximum' => [0x7fff_ffff, 'feffffff0f'];
        yield 'signed minimum' => [-0x8000_0000, 'ffffffff0f'];
    }

    #[DataProvider('unsignedVarIntVectors')]
    public function testUnsignedVarIntLiteralVectors(int $value, string $hex): void
    {
        $writer = new BinaryWriter();
        $writer->writeUnsignedVarInt($value);
        self::assertSame($hex, bin2hex($writer->data()));

        $bytes = hex2bin($hex);
        self::assertIsString($bytes);
        $reader = new BinaryReader($bytes);
        self::assertSame($value, $reader->readUnsignedVarInt());
        self::assertSame(0, $reader->unreadLength());
    }

    #[DataProvider('signedVarIntVectors')]
    public function testSignedVarIntLiteralVectors(int $value, string $hex): void
    {
        $writer = new BinaryWriter();
        $writer->writeSignedVarInt($value);
        self::assertSame($hex, bin2hex($writer->data()));

        $bytes = hex2bin($hex);
        self::assertIsString($bytes);
        $reader = new BinaryReader($bytes);
        self::assertSame($value, $reader->readSignedVarInt());
        self::assertSame(0, $reader->unreadLength());
    }

    public function testLittleEndianIntegerLiteralVector(): void
    {
        $writer = new BinaryWriter();
        $writer->writeUnsignedByte(0xab);
        $writer->writeUnsignedShortLE(0x1234);
        $writer->writeUnsignedIntLE(0x89abcdef);
        $writer->writeUnsignedLongLE(0x0123456789abcdef);
        self::assertSame('ab3412efcdab89efcdab8967452301', bin2hex($writer->data()));

        $reader = new BinaryReader($writer->data());
        self::assertSame(0xab, $reader->readUnsignedByte());
        self::assertSame(0x1234, $reader->readUnsignedShortLE());
        self::assertSame(0x89abcdef, $reader->readUnsignedIntLE());
        self::assertSame(0x0123456789abcdef, $reader->readUnsignedLongLE());
        self::assertSame(0, $reader->unreadLength());
    }

    public function testTruncatedReadIsRejected(): void
    {
        $this->expectException(BinaryDecodeException::class);
        new BinaryReader("\x01")->readUnsignedShortLE();
    }

    public function testOverlongVarIntIsRejected(): void
    {
        $this->expectException(BinaryDecodeException::class);
        new BinaryReader("\x80\x80\x80\x80\x80\x00")->readUnsignedVarInt();
    }

    public function testOutOfRangeVarIntIsRejected(): void
    {
        $this->expectException(BinaryDecodeException::class);
        new BinaryReader("\xff\xff\xff\xff\x10")->readUnsignedVarInt();
    }
}
