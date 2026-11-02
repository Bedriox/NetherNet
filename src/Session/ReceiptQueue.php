<?php

/*
 * Bedriox NetherNet - WebRTC transport for Minecraft: Bedrock Edition
 * Copyright (C) 2026 Veno Ninja LLC
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

namespace Bedriox\NetherNet\Session;

/** @extends \SplQueue<array{int, int}> */
final class ReceiptQueue extends \SplQueue {}
