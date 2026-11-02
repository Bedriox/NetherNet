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

namespace Bedriox\NetherNet\Info;

use Bedriox\NetherNet\Protocol\BinaryDecodeException;
use Bedriox\NetherNet\Protocol\BinaryReader;
use Bedriox\NetherNet\Protocol\BinaryWriter;
use Bedriox\NetherNet\Signaling\Lan\Packet\DiscoveryException;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function strlen;

/**
 * Binary (LAN advertisement) and JSON (HTTP probe) encodings of a {@link ServerInfo}.
 */
final class ServerInfoCodec
{
    private function __construct()
    {
        //NOOP
    }

    public static function encode(ServerInfo $info): string
    {
        $out = new BinaryWriter();

        $out->writeUnsignedByte(ServerInfo::DATA_VERSION);
        self::writeString($out, $info->serverName);
        $out->writeSignedVarInt($info->protocol);
        self::writeString($out, $info->gameVersion);
        self::writeString($out, $info->levelName);
        $out->writeSignedVarInt($info->playerCount);
        $out->writeSignedVarInt($info->maxPlayerCount);
        $out->writeSignedVarInt($info->gameType->value);
        $out->writeUnsignedByte($info->editorWorld ? 1 : 0);
        $out->writeUnsignedByte($info->hardcore ? 1 : 0);
        $out->writeUnsignedByte($info->acceptsOnlineAuth ? 1 : 0);
        $out->writeUnsignedByte($info->acceptsSelfSignedAuth ? 1 : 0);
        self::writeString($out, $info->nonce);
        $out->writeSignedVarInt(ServerInfo::CONNECTION_TYPE_NETHERNET);

        return $out->data();
    }

    /**
     * @throws \JsonException
     */
    public static function encodeJson(ServerInfo $info): string
    {
        return json_encode([
            "dataVersion" => ServerInfo::DATA_VERSION,
            "name" => $info->serverName,
            "protocol" => $info->protocol,
            "version" => $info->gameVersion,
            "level" => $info->levelName,
            "players" => $info->playerCount,
            "maxPlayers" => $info->maxPlayerCount,
            "gameType" => $info->gameType->value,
            "editor" => $info->editorWorld,
            "hardcore" => $info->hardcore,
            "onlineAuth" => $info->acceptsOnlineAuth,
            "selfSignedAuth" => $info->acceptsSelfSignedAuth,
            "nonce" => $info->nonce,
            "transportLayer" => ServerInfo::TRANSPORT_LAYER_NETHERNET,
            "connection" => ServerInfo::CONNECTION_TYPE_NETHERNET,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws DiscoveryException
     */
    public static function decode(string $data): ServerInfo
    {
        $in = new BinaryReader($data);

        try {
            $version = $in->readUnsignedByte();
            if ($version !== ServerInfo::DATA_VERSION) {
                throw new DiscoveryException("Expected server advertisement version " . ServerInfo::DATA_VERSION . ", got $version");
            }

            $serverName = self::readString($in);
            $protocol = $in->readSignedVarInt();
            $gameVersion = self::readString($in);
            $levelName = self::readString($in);
            $playerCount = $in->readSignedVarInt();
            $maxPlayerCount = $in->readSignedVarInt();

            $rawGameType = $in->readSignedVarInt();
            $gameType = GameType::tryFrom($rawGameType);
            if ($gameType === null) {
                throw new DiscoveryException("Unknown game type $rawGameType");
            }

            $editorWorld = $in->readUnsignedByte() !== 0;
            $hardcore = $in->readUnsignedByte() !== 0;
            $acceptsOnlineAuth = $in->readUnsignedByte() !== 0;
            $acceptsSelfSignedAuth = $in->readUnsignedByte() !== 0;
            $nonce = self::readString($in);

            $connectionType = $in->readSignedVarInt();
            if ($connectionType !== ServerInfo::CONNECTION_TYPE_NETHERNET) {
                throw new DiscoveryException("Expected connection type " . ServerInfo::CONNECTION_TYPE_NETHERNET . ", got $connectionType");
            }

            if ($in->unreadLength() !== 0) {
                throw new DiscoveryException("Server advertisement has " . $in->unreadLength() . " unread bytes remaining");
            }
        } catch (BinaryDecodeException $e) {
            throw new DiscoveryException("Malformed server advertisement: " . $e->getMessage(), 0, $e);
        }

        return new ServerInfo(
            $serverName,
            $protocol,
            $gameVersion,
            $levelName,
            $gameType,
            $playerCount,
            $maxPlayerCount,
            $editorWorld,
            $hardcore,
            $acceptsOnlineAuth,
            $acceptsSelfSignedAuth,
            $nonce
        );
    }

    /**
     * @throws BinaryDecodeException
     */
    private static function readString(BinaryReader $in): string
    {
        return $in->readBytes($in->readUnsignedVarInt());
    }

    private static function writeString(BinaryWriter $out, string $value): void
    {
        $out->writeUnsignedVarInt(\strlen($value));
        $out->writeBytes($value);
    }
}
