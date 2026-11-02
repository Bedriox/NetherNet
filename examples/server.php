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

/**
 * Minimal echo server: accepts NetherNet clients over HTTP and LAN signaling and sends every packet back.
 */

use Bedriox\NetherNet\Identity\PeerIdentity;
use Bedriox\NetherNet\Identity\SelfSignedIdentityProvider;
use Bedriox\NetherNet\Identity\ServerIdentity;
use Bedriox\NetherNet\Info\MutableServerInfoProvider;
use Bedriox\NetherNet\Info\ServerInfo;
use Bedriox\NetherNet\Protocol\DisconnectReason;
use Bedriox\NetherNet\Protocol\Reliability;
use Bedriox\NetherNet\Server\NetherNetServer;
use Bedriox\NetherNet\Server\ServerConfiguration;
use Bedriox\NetherNet\Server\ServerEventListener;
use Bedriox\NetherNet\Server\ServerInterface;
use Bedriox\NetherNet\Signaling\Http\HttpSignaling;
use Bedriox\NetherNet\Signaling\Lan\LanSignaling;
use Bedriox\NetherNet\Utils\InternetAddress;

require dirname(__DIR__) . '/vendor/autoload.php';

// 1. Define an event listener. Sessions are named by ID; commands go back through the server.
$listener = new class implements ServerEventListener{
	public ?ServerInterface $server = null;

	public function onSessionOpen(int $sessionId, string $networkId, InternetAddress $address, ?string $clientAddress, ?PeerIdentity $identity) : void{
		echo "Session $sessionId: network id $networkId connected from $address" . PHP_EOL;
	}

	public function onSessionClose(int $sessionId, DisconnectReason $reason) : void{
		echo "Session $sessionId disconnected: " . $reason->getMessage() . PHP_EOL;
	}

	public function canAcceptPackets() : bool{
		return true;
	}

	public function onPacketReceive(int $sessionId, string $payload, Reliability $reliability) : void{
		$this->server?->sendPacket($sessionId, $payload, $reliability);
	}

	public function onPacketAck(int $sessionId, int $receiptId) : void{
		//only called for packets sent with a receipt ID
	}

	public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff) : void{
		//called about once a second while there is traffic
	}

	public function onPingMeasure(int $sessionId, int $pingMs) : void{
		echo "Session $sessionId ping: $pingMs ms" . PHP_EOL;
	}
};

// 2. Load or generate the server identity and create the server
$keyFile = __DIR__ . '/server.key';
$pem = file_exists($keyFile) ? file_get_contents($keyFile) : false;
$identity = $pem !== false ? ServerIdentity::fromPrivateKeyPem($pem) : ServerIdentity::generate();

$config = new ServerConfiguration(
	identityProvider: new SelfSignedIdentityProvider($identity),
	logger: new SimpleLogger()
);
$server = NetherNetServer::create($config, $listener);
$listener->server = $server;

// 3. Add signaling transports (HTTP and LAN)
$infoProvider = new MutableServerInfoProvider(new ServerInfo(
	serverName: 'Dedicated Server',
	protocol: 800,
	gameVersion: '1.21.100',
	levelName: 'World',
	maxPlayerCount: 20
));
$server->addSignaling(new HttpSignaling(new InternetAddress('0.0.0.0', 19132, 4), infoProvider: $infoProvider));
$server->addSignaling(new LanSignaling($infoProvider, networkId: 12345678), optional: true);

// 4. Start the server and run the tick loop
$server->start();

while($server->isRunning()){
	$server->tick();
	usleep(10_000); // 10ms tick
}
