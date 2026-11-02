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

namespace Bedriox\NetherNet\Signaling\Http;

use function strlen;

/**
 * @internal
 */
final class HttpResponse
{
    public const HEADER_CONTENT_TYPE = "Content-Type";
    public const HEADER_CONTENT_LENGTH = "Content-Length";
    public const HEADER_CONNECTION = "Connection";
    public const HEADER_LOCATION = "Location";

    /**
     * @param string[] $headers
     * @phpstan-param array<string, string> $headers
     */
    public function __construct(
        public readonly HttpStatus $status,
        public readonly string $body = "",
        public readonly ?string $contentType = null,
        public readonly array $headers = []
    ) {}

    /**
     * @param bool $omitBody Whether to leave the body out while still declaring its length, as a HEAD response does.
     */
    public function encode(bool $omitBody = false): string
    {
        $head = "HTTP/1.1 " . $this->status->value . " " . $this->status->getReasonPhrase() . "\r\n";
        if ($this->contentType !== null) {
            $head .= self::HEADER_CONTENT_TYPE . ": $this->contentType\r\n";
        }
        foreach ($this->headers as $name => $value) {
            $head .= "$name: $value\r\n";
        }
        $head .= self::HEADER_CONTENT_LENGTH . ": " . \strlen($this->body) . "\r\n";
        $head .= self::HEADER_CONNECTION . ": close\r\n\r\n";

        return $head . ($omitBody ? "" : $this->body);
    }
}
