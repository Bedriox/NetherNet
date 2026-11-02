# Bedriox/NetherNet contributor guide

## Purpose and boundary

This repository is the standalone NetherNet transport maintained for Bedriox.
It owns HTTP and LAN signaling, identity assertions, SDP and ICE negotiation,
WebRTC DataChannels, Minecraft transport framing, sessions, delivery, resource
limits, and transport security telemetry.

It must not contain Minecraft application packet definitions, authentication
policy, players, worlds, commands, plugins, or gameplay. Payload bytes remain
opaque to this component.

## Architecture invariants

- One event-loop owner mutates signaling, negotiation, peers, channels, and
  sessions.
- Every request, header, body, SDP line, candidate, queue, segment, payload,
  callback batch, timeout, and map has a hard validated bound.
- A stable session ID owns a connection. Signaling, selected ICE, proxy, and
  relay addresses are distinct observations and must not replace session
  identity.
- A session opens only after identity verification and both exact Minecraft
  DataChannels have opened with their required reliability properties.
- Native callbacks never mutate consumer state directly. Consumers drain
  immutable events through bounded queues.
- Complete SDP, private keys, DTLS material, credentials, and opaque payloads
  are never logged.
- Malformed, unauthenticated, oversized, exhausted, and cryptographically
  invalid input fails closed and remains isolated to its peer.
- Shutdown releases signaling sockets, negotiations, native peers, channels,
  sessions, callbacks, and queued data deterministically.

## Repository structure

- `src/Signaling/` owns bounded HTTP and LAN signaling.
- `src/Identity/` and `src/Crypto/` own identity assertions and signatures.
- `src/Sdp/` and `src/Negotiation/` own validated descriptions and ICE flow.
- `src/Transport/` owns native peer and DataChannel adapters.
- `src/Protocol/Framing/` owns Minecraft NetherNet segmentation.
- `src/Session/` and `src/Server/` own session and server lifecycle.
- `tests/` mirrors production responsibilities with exact, negative, cleanup,
  and loopback coverage.

## Native backend

Production requires the exact `ext-webrtc` and `libdatachannel` revisions
qualified by Bedriox Runtime. Tests may use fake peer and channel adapters, but
release qualification requires native reliable and unreliable loopback tests.
Never silently use an ambient native library or fall back to a legacy transport.

## Licensing

Preserve the original Axolotl Team copyright and the exact reviewed source
revision in `THIRD_PARTY_NOTICES.md`. Do not remove attribution from adapted
files. New original work is GPL-3.0-only.

## Verification

Use PHP 8.4 and Composer 2:

```console
composer install
composer check
composer validate --strict
composer audit --locked
```

Wire behavior requires independent literal vectors. Network behavior requires
loopback tests with deterministic deadlines and cleanup. Inspect the complete
diff and `git diff --check` before committing.

## Commits

Use short natural imperative subjects without Conventional Commit prefixes,
personal email addresses, or identity trailers. Public documentation must stay
project-focused and must not expose private development workflow.
