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

namespace Bedriox\NetherNet\Negotiation;

use Bedriox\NetherNet\Identity\PeerIdentity;
use Bedriox\NetherNet\Transport\ChannelPair;
use Bedriox\NetherNet\Transport\PeerTransport;
use Bedriox\NetherNet\Utils\InternetAddress;

/**
 * Whoever takes it from the negotiator owns the transport and must close it.
 *
 * The address is where the transport reaches the peer, which is a relay's when the peer connects through one. The
 * client address is the IP the offer was signaled from, where the signaling transport knew it, in the canonical
 * form {@link InternetAddress::normalizeIp()} gives.
 */
final class EstablishedPeer
{
    public function __construct(
        public readonly PeerTransport $transport,
        public readonly ChannelPair $channels,
        public readonly string $networkId,
        public readonly InternetAddress $address,
        public readonly ?string $clientAddress,
        public readonly ?PeerIdentity $identity
    ) {}
}
