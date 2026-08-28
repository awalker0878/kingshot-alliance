# Intelligence Change Detection — Delivery Ledger

Status: Current complete capability

Date: 2026-08-28

Canonical product contract: [Intelligence Change Detection](intelligence-change-detection.md)

This ledger records implementation evidence for Phase 11 of the Capability Extension Program. A row is `Complete` only when its implementation exists and applicable repository verification is green. Phase 11 is complete; the wider Capability Extension Program remains active because other selected and evidence-gated extensions are still open.

| ID | Delivery item | Implementation evidence | Verification state |
| --- | --- | --- | --- |
| ICD-0 | Product contract, ownership/provenance, taxonomy, UX/release criteria | `docs/product/intelligence-change-detection.md` | Complete |
| ICD-1 | Typed signals, fingerprint, configurable rule semantics | `ReadModels/IntelligenceSignals/Enums`, `ValueObjects`, `Services/IntelligenceSignalRules.php`, `config/intelligence.php` | Complete |
| ICD-2 | Alliance power/member/freshness derivation | `IntelligenceSignalFactory`, `IntelligenceSignalQuery` | Complete |
| ICD-3 | Disappearance/reappearance complete-source discipline | Factory requires explicit `completeSource`; ordinary Alliance history intentionally emits no disappearance because no complete-snapshot absence contract exists | Complete; runtime subtype intentionally unsupported until a source contract proves exhaustive presence/absence |
| ICD-4 | Governor progression change/staleness with Evidence/dataset provenance | `IntelligenceSignalFactory::progressionChange`, active-Governor scoped query; partial Hero-roster capture blocked from absence-sensitive comparison | Complete |
| ICD-5 | Transfer validity/expiry | Canonical `TransferObservation.valid_until` derivation; Evidence is provenance only | Complete |
| ICD-6 | Bear Hunt trend derivation | Existing completed-run history + configured minimum comparable monotonic runs; missing runs omitted rather than zero-filled | Complete |
| ICD-7 | Recruitment owner-history changes | `RecruitmentStageHistory` only; no `updated_at` history fabrication | Complete |
| ICD-8 | Unified bounded composition/dedupe/sort/query limits | `IntelligenceSignalQuery::recentForAlliance`; Alliance ID applied to owner reads; actor progression limited to active Governor | Complete |
| ICD-9 | Kingdom Intelligence integration | authorized controller supplies typed `signals`; shared `IntelligenceSignalFeed` renders source-linked feed | Complete |
| ICD-10 | Command Overview integration | query checks Intelligence view permission before retrieval; compact feed is shown only with a concrete active Alliance scope; signals do not inflate `actionCount` | Complete |
| ICD-11 | Alliance Assistant intent/citations | `AssistantIntent::IntelligenceChanges`, bounded `IntelligenceChangeAssistantQuery`, controller routing, existing server-built evidence/citation values | Complete |
| ICD-12 | Communications delivery boundary | `IntelligenceSignalNotificationPublisher` uses existing preferences/channels and fingerprint-derived idempotency; no signal persistence | Complete |
| ICD-13 | Localization/accessibility/responsive UX/privacy semantics | shared semantic list/feed, navigation-only source links, 17-locale label overlay, neutral materiality/attention semantics | Complete |
| ICD-14 | Architecture/behavior/security/release verification | factory/Assistant/notification/architecture tests plus repository CI, Intelligence, Architecture, Visual Regression, CodeQL and Dependency Review workflows | Complete — implementation candidate `e5c492f9391431ab68e1b2ca215038f448e5539d` passed all applicable workflows |
| ICD-15 | Final product reconciliation/status promotion | canonical contract, this ledger and global capability state reconciled after the green implementation candidate | Complete |

## Test evidence

- `IntelligenceSignalFactoryV3Test` — deterministic fingerprint, materiality, exact stale boundary, complete-source disappearance gate, progression provenance, Transfer validity, Bear Hunt minimum/trend and Recruitment history.
- `IntelligenceSignalNotificationPublisherV3Test` — delivery idempotency by signal fingerprint/recipient/policy.
- `IntelligenceChangeAssistantQueryV3Test` — bounded intent recognition that does not hijack ordinary observation, guide or eligibility questions.
- `IntelligenceChangeDetectionArchitectureV3Test` — no new bounded context/table, no owner-context dependency on the read model, complete-source guard, navigation-only accessible feed and dashboard scope gating.
- `CommandOverviewBehaviorV3Test` — informational signal feed remains outside action count.
- Playwright visual regression — verifies desktop/mobile command surfaces, including the rule that an unscoped dashboard does not render a false empty Intelligence feed.

## Explicit unsupported disposition

Tracked-Alliance disappearance/reappearance is implemented as a typed derivation primitive but is **not emitted from ordinary observation history** because the current observed-Alliance owner model does not prove that a missing Alliance in an ingestion/capture is absent from a complete source set. This is the required factual disposition, not an incomplete guess. A future approved source may enable this subtype only by providing explicit complete-source presence/absence evidence.

## Verification evidence

Implementation candidate `e5c492f9391431ab68e1b2ca215038f448e5539d` passed:

- CI, including full PHP checks, frontend quality/build, production image build, ephemeral staging, backup/restore and image scan;
- Intelligence Verification, including Pint, Larastan, behavior contracts and frontend contracts;
- Architecture V3 Verification;
- Visual Regression;
- CodeQL;
- Dependency Review.

The reconciliation commit is documentation-only and must also pass the applicable latest-head repository gates before PR #132 leaves draft state.
