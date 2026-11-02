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

namespace Bedriox\NetherNet;

use Bedriox\NetherNet\Transport\ConnectionBudgetConfiguration;
use Bedriox\NetherNet\Transport\PeerTransport;
use Bedriox\NetherNet\Transport\PeerTransportFactory;
use Bedriox\NetherNet\Transport\TransportException;

final class FakeTransportFactory implements PeerTransportFactory
{
    /** @phpstan-var list<FakePeerTransport> */
    public array $created = [];

    public bool $fail = false;
    public bool $rejectOffers = false;

    public function create(ConnectionBudgetConfiguration $budget): PeerTransport
    {
        if ($this->fail) {
            throw new TransportException("transport refused by the test");
        }

        $transport = new FakePeerTransport();
        $transport->rejectOffer = $this->rejectOffers;

        return $this->created[] = $transport;
    }
}
