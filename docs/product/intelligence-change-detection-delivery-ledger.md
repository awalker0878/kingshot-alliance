# Intelligence Change Detection — Delivery Ledger

Status: Implementation verification in progress

Date: 2026-08-28

Canonical product contract: [Intelligence Change Detection](intelligence-change-detection.md)

This ledger records implementation evidence for Phase 11 of the Capability Extension Program. A row is `Complete` only when its implementation exists and applicable repository verification is green. The capability remains a Selected extension until final reconciliation closes every applicable row.

| ID | Delivery item | Implementation evidence | Verification state |
| --- | --- | --- | --- |
| ICD-0 | Product contract, ownership/provenance, taxonomy, UX/release criteria | `docs/product/intelligence-change-detection.md` | Complete |
| ICD-1 | Typed signals, fingerprint, configurable rule semantics | `ReadModels/IntelligenceSignals/Enums`, `ValueObjects`, `Services/IntelligenceSignalRules.php`, `config/intelligence.php` | CI pending |
| ICD-2 | Alliance power/member/freshness derivation | `IntelligenceSignalFactory`, `IntelligenceSignalQuery` | CI pending |
| ICD-3 | Disappearance/reappearance complete-source discipline | Factory requires explicit `completeSource`; current ordinary Alliance history intentionally emits no disappearance because no complete-snapshot absence contract exists | CI pending; runtime subtype intentionally unsupported until source contract exists |
| ICD-4 | Governor progression change/staleness with Evidence/dataset provenance | `IntelligenceSignalFactory::progressionChange`, active-Governor scoped query; partial Hero-roster capture blocked from absence-sensitive comparison | CI pending |
| ICD-5 | Transfer validity/expiry | Canonical `TransferObservation.valid_until` derivation; Evidence is provenance only | CI pending |
| ICD-6 | Bear Hunt trend derivation | Existing completed-run history + configured minimum comparable monotonic runs; missing runs omitted rather than zero-filled | CI pending |
| ICD-7 | Recruitment owner-history changes | `RecruitmentStageHistory` only; no `updated_at` history fabrication | CI pending |
| ICD-8 | Unified bounded composition/dedupe/sort/query limits | `IntelligenceSignalQuery::recentForAlliance`; alliance ID applied to owner reads; actor progression limited to active Governor | CI pending |
| ICD-9 | Kingdom Intelligence integration | authorized controller supplies typed `signals`; shared `IntelligenceSignalFeed` renders source-linked feed | CI/visual pending |
| ICD-10 | Command Overview integration | query checks Intelligence view permission before retrieval; compact feed; signals do not inflate `actionCount` | CI/visual pending |
| ICD-11 | Alliance Assistant intent/citations | `AssistantIntent::IntelligenceChanges`, bounded `IntelligenceChangeAssistantQuery`, controller routing, existing server-built evidence/citation values | CI pending |
| ICD-12 | Communications delivery boundary | `IntelligenceSignalNotificationPublisher` uses existing preferences/channels and fingerprint-derived idempotency; no signal persistence | CI pending |
| ICD-13 | Localization/accessibility/responsive UX/privacy semantics | shared semantic list/feed, navigation-only source links, 17-locale label overlay, neutral materiality/attention semantics | CI/visual pending |
| ICD-14 | Architecture/behavior/security/release verification | new factory/Assistant/notification/architecture tests plus repository CI, Intelligence, Architecture, Visual Regression, CodeQL and Dependency Review workflows | In progress on PR #132 |
| ICD-15 | Final product reconciliation/status promotion | Must reconcile this ledger, canonical contract and global capability docs only after all applicable gates pass | Not started |

## Test evidence added

- `IntelligenceSignalFactoryV3Test` — deterministic fingerprint, materiality, exact stale boundary, complete-source disappearance gate, progression provenance, Transfer validity, Bear Hunt minimum/trend and Recruitment history.
- `IntelligenceSignalNotificationPublisherV3Test` — delivery idempotency by signal fingerprint/recipient/policy.
- `IntelligenceChangeAssistantQueryV3Test` — bounded intent recognition that does not hijack ordinary observation, guide or eligibility questions.
- `IntelligenceChangeDetectionArchitectureV3Test` — no new bounded context/table, no owner-context dependency on the read model, complete-source guard and navigation-only accessible feed.
- updated `CommandOverviewBehaviorV3Test` — informational signal feed remains outside action count.

## Explicit unsupported disposition

Tracked-Alliance disappearance/reappearance is implemented as a typed derivation primitive but is **not emitted from ordinary observation history** because the current observed-Alliance owner model does not prove that a missing Alliance in an ingestion/capture is absent from a complete source set. This is the required factual disposition, not an incomplete guess. A future approved source may enable this subtype only by providing explicit complete-source presence/absence evidence.

## Completion rule

Do not promote Intelligence Change Detection to Current complete capability until the latest branch head has green applicable repository gates and `/docs/product` has been reconciled to those results. A failing workflow is work to resolve, not a reason to mark the row complete.
