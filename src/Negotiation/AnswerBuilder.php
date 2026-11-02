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

use Bedriox\NetherNet\Identity\IdentityException;
use Bedriox\NetherNet\Identity\IdentityProvider;
use Bedriox\NetherNet\Protocol\ErrorCode;
use Bedriox\NetherNet\Sdp\SdpException;
use Bedriox\NetherNet\Sdp\SessionDescription;

/**
 * @internal
 */
final class AnswerBuilder
{
    /**
     * @param string[]|null $advertisedAddresses
     * @phpstan-param list<string>|null $advertisedAddresses
     */
    public function __construct(
        private readonly IdentityProvider $identityProvider,
        private readonly ?array $advertisedAddresses = null
    ) {}

    /**
     * @throws SdpException
     * @throws IdentityException
     * @throws NegotiationException if a bundled answer would carry no candidate to reach this host at
     */
    public function build(string $localDescription, CandidateMode $candidateMode): LocalAnswer
    {
        $answer = SessionDescription::parse($localDescription);
        $ufrag = $answer->getMediaAttributeValues("ice-ufrag")[0] ?? null;
        if ($ufrag === null) {
            throw new SdpException("Local description has no ice-ufrag");
        }

        // Advertised addresses only rewrite bundled answers; trickle ICE is used for LAN discovery where local gathered addresses are required.
        if ($candidateMode === CandidateMode::TRICKLE) {
            $answer = $answer->withoutCandidates();
        } elseif ($this->advertisedAddresses !== null) {
            //the transport writes its default candidate's address to the connection line
            $answer = $answer
                ->withCandidates(IceCandidateFormatter::advertise($answer->getMediaAttributeValues("candidate"), $this->advertisedAddresses))
                ->withoutConnectionAddress();
        }
        if ($candidateMode === CandidateMode::BUNDLED && $answer->countCandidates() === 0) {
            throw new NegotiationException("No local candidates to answer with", ErrorCode::ICE);
        }

        $assertion = $this->identityProvider->issue($answer->getFingerprint());

        return new LocalAnswer($answer->withIdentity($assertion->encode())->toString(), $ufrag);
    }
}
