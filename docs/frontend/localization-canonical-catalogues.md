# Canonical frontend catalogues

All ten remaining runtime overlays now live in their owning English domain catalogues. The loader imports one native catalogue per domain and locale. Laravel messages remain in `lang/`; no PHP messages move into the browser bundle.

The immutable pre-move source is `355d975d3b1f37e0decb3bf80803596c0570eb6d`. `check-localization-overlay-consolidation.mjs` reconstructs every intermediate merge from that Git object and verifies its result against the historical loader. The resulting canonical files match all 17 locales across all 19 domains. No non-English catalogue is edited.

## Ordered overlay inventory

| Domain | Overlay | Added | Identical collisions | Different winners |
|---|---|---:|---:|---:|
| core | `alliance-capability-expansion-labels` | 175 | 0 | 0 |
| core | `governance-capability-expansion-labels` | 89 | 0 | 0 |
| core | `gift-code-workspace-labels` | 171 | 2 | 4 |
| core | `governor-lifecycle-labels` | 44 | 0 | 0 |
| account | `governor-lifecycle-labels` | 44 | 0 | 0 |
| alliance | `alliance-capability-expansion-labels` | 175 | 0 | 0 |
| alliance | `intelligence-change-labels` | 11 | 0 | 0 |
| assistant | `assistant-gameworld-extension` | 0 | 0 | 0 |
| assistant | `assistant-transfer-labels` | 13 | 0 | 0 |
| assistant | `intelligence-change-labels` | 11 | 0 | 0 |
| events | `event-command-labels` | 138 | 0 | 0 |
| kingdom | `governance-capability-expansion-labels` | 89 | 0 | 0 |
| kingdom | `intelligence-change-labels` | 11 | 0 | 0 |
| progression | `progression-planner-labels` | 42 | 0 | 0 |
| territory | `territory-reconciliation-labels` | 104 | 0 | 0 |

The Assistant Game World extension returns no English values; its localized values were already present in the native catalogues. Its multilingual module and the governance label table have no remaining runtime consumers and are removed. Governor and Governance choice tests now read the canonical files. Cross-domain ownership and the two previously consolidated Account/Transfer value groups are preserved.

## Differing collision winners

| Message | Previous raw text | Preserved runtime text |
|---|---|---|
| `core:receipts.gift-code-session-created` | Gift Code redemption run created. | Gift Code redemption run created with {count} items. |
| `core:receipts.gift-code-session-item-prepared` | Gift Code handoff prepared. | Official Gift Code handoff prepared. |
| `core:receipts.gift-code-session-item-recorded` | Gift Code result saved and the run advanced. | Gift Code outcome recorded and run progress updated. |
| `core:receipts.gift-code-session-item-skipped` | Gift Code redemption item skipped for now. | Gift Code item skipped for this run. |

Each of these four real canonical values is independently reverted by a negative control. Export succeeds, then the production six-fingerprint parity checker must fail on changed effective text. Existing recipient/transfer collision checks and raw-source mutation controls remain in place.

## Existing wording debt

Consolidation exposed eight existing runtime messages that the previous product-language scanner did not inspect in overlay files. `structural-wording-debt.json` records exact domain/key, value, phrase and historical overlay. The audit proves each value existed before this move. The product-language checker rejects new violations, edited debt text and stale records; two negative controls demonstrate this. These records are a separate wording-review backlog, not approval of terminology or native language quality.

| Message | Follow-up |
|---|---|
| `core:giftCodes.acquisitionOperations.effectivenessHelp` | append-only, provenance |
| `core:giftCodes.acquisitionOperations.replays` | replay |
| `core:giftCodes.acquisitionOperations.quarantined` | quarantined |
| `events:events.command.eyebrow` | Event Command |
| `events:events.command.closeoutEyebrow` | Event Command |
| `events:events.command.attentionItems` | Event Command |
| `progression:progression.staleObservationHelp` | provenance |
| `territory:territory.observationProvenance` | provenance |

## Validation and emitted bytes

Local `npm run check` passes, including completeness, placeholders, semantic checks, source contracts, formatting, types, receipts, build and performance limits. The six immutable effective/server/risk hashes are unchanged. Raw catalogue output now equals effective catalogue output; its explicitly allowed SHA-256 is `2ab071d65edd083aadd2f3707dffcf2f1490f38ded8aa87bb8a59cf401f2f834`. The original fingerprint manifest is unchanged.

| English fallback domain | Before JS bytes | After JS bytes | Gzip change | Brotli change |
|---|---:|---:|---:|---:|
| core | 60633 | 45758 | -7656 | -6006 |
| account | 19000 | 18909 | -239 | -150 |
| alliance | 20521 | 12496 | -3945 | -3171 |
| assistant | 88019 | 10502 | -24524 | -20486 |
| events | 37908 | 28541 | -3958 | -3221 |
| kingdom | 50732 | 28683 | -9537 | -7784 |
| progression | 15078 | 11681 | -1226 | -1067 |
| territory | 17364 | 17279 | -328 | -181 |

The app entry changes from 220,645 to 219,092 bytes. The largest page remains 73,089 bytes against a 73,728-byte limit (639 bytes headroom).

These are actual emitted assets, with static imports deduplicated per domain and each asset compressed separately (gzip level 9; Brotli quality 11). Domain rows overlap shared assets and must not be summed into a whole-page claim. French/Arabic scenarios and individual asset lists are retained in the CI artifact. This is not observed CDN transfer size or timing.

Hosted CI, production browser recovery and independent HTTP/native reviews remain separate gates. This branch includes the prior runtime, navigation and normalization fixes; integration order remains #187, #181, #184, then this structural move. No merge or deployment is implied.
