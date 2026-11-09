<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 *
 * Website: https://bedriox.com
 * Source: https://github.com/Bedriox/NetherNet
 *
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Client;

use Bedriox\NetherNet\Transport\ConnectionBudgetConfiguration;

final readonly class ClientConfiguration
{
    public function __construct(
        public string $targetAddress,
        public int $targetPort,
        public string $networkId,
        public string $bindAddress = '0.0.0.0',
        public int $maximumTransmissionUnit = 1_280,
        public int $handshakeTimeoutMilliseconds = 15_000,
        public int $idleTimeoutMilliseconds = 30_000,
        public int $maximumHttpResponseBytes = 262_144,
        public ConnectionBudgetConfiguration $budget = new ConnectionBudgetConfiguration(),
        public ?string $forwardedForAddress = null,
    ) {
        if (filter_var($targetAddress, FILTER_VALIDATE_IP) === false
            || filter_var($bindAddress, FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException('NetherNet client addresses must be IP addresses.');
        }
        if ($targetPort < 1 || $targetPort > 65_535
            || preg_match('/^(?:0|[1-9][0-9]{0,19})$/D', $networkId) !== 1
            || (\strlen($networkId) === 20 && strcmp($networkId, '18446744073709551615') > 0)) {
            throw new \InvalidArgumentException('NetherNet client endpoint is invalid.');
        }
        if ($maximumTransmissionUnit < 576 || $maximumTransmissionUnit > 65_535
            || $handshakeTimeoutMilliseconds < 1 || $idleTimeoutMilliseconds < 1
            || $maximumHttpResponseBytes < 1_024 || $maximumHttpResponseBytes > 1_048_576) {
            throw new \InvalidArgumentException('NetherNet client limits are invalid.');
        }
        if ($forwardedForAddress !== null && filter_var($forwardedForAddress, FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException('Forwarded client address must be an IP address when present.');
        }
    }
}
