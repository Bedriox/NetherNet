# Bedriox/NetherNet

Bedriox/NetherNet is the standalone WebRTC DataChannel transport used by
[Bedriox](https://github.com/Bedriox/Bedriox). It owns NetherNet signaling,
identity assertions, peer negotiation, reliable and unreliable delivery,
message framing, sessions, and bounded transport security controls.

Website: [bedriox.com](https://bedriox.com)

The package deliberately contains no Minecraft gameplay packets, players,
worlds, commands, inventories, or plugin behavior.

> **Project status:** Early development. APIs and runtime requirements may
> change before a stable release.

## Requirements

- 64-bit PHP 8.4
- `ext-webrtc` built against the reviewed `libdatachannel` release
- OpenSSL, hash, JSON, and sockets extensions
- Composer 2 for development

Bedriox Runtime will provide the supported native extension and libraries.

## Development

```console
composer install
composer check
```

Backend-independent tests may be installed explicitly with
`--ignore-platform-req=ext-webrtc`. That exemption does not create a usable
native server and is not valid for release qualification.

## Transport boundary

Minecraft Bedrock opens two WebRTC DataChannels:

- `ReliableDataChannel` for ordered reliable messages;
- `UnreliableDataChannel` for unordered messages with no retransmission.

NetherNet performs the Minecraft message segmentation above those channels and
delivers opaque application payloads to its consumer. Bedriox/Protocol owns the
application packet bytes.

## License and attribution

NetherNet is distributed under GPL-3.0-only. Required third-party attribution
and license notices are retained in [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).

Bedriox is independent and is not affiliated with Mojang Studios or Microsoft.
Minecraft is a trademark of Microsoft Corporation.
