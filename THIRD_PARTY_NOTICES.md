# Third-party notices

## axolotl-pm/NetherNet

- Project: `https://github.com/axolotl-pm/NetherNet`
- Reviewed revision: `16c25367f0bb310215b71cad3393b3b451891b16`
- Copyright: Copyright (C) 2026 Axolotl Team
- Original license: LGPL-3.0-or-later

The transport implementation, tests, and fixtures were adapted from the
reviewed revision. Under section 3 of LGPL version 3, the adapted copy is
distributed under GNU GPL version 3 terms. Original copyright notices and this
provenance record are retained.

## Native runtime dependencies

`ext-webrtc` and `libdatachannel` are runtime build dependencies rather than
vendored source in this repository. Their exact revisions, licenses, binary
closure, and notices are owned and validated by Bedriox Runtime.

The PHP declarations in `tools/phpstan/webrtc/` are adapted from `ext-webrtc`
revision `2858b33fee03e90a71ad2a294264b642db48a13e`, copyright the Axolotl Team,
under the MIT License. They are analysis-only declarations and contain no
native implementation.
