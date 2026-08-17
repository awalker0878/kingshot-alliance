# CA-P4 Certification — Context-Owned Write APIs

Status: PASS

Completed:
- Removed all cross-context Eloquent model imports from application Actions.
- Platform account deletion now orchestrates owner APIs: Accounts anonymizes the account, GameWorld releases Player ownership, Alliance removes memberships, and Platform owns only deletion-request state/audit/outbox.
- Platform Alliance lifecycle administration now calls Alliance-owned `TransitionAllianceLifecycle` using scalar Alliance identity and immutable references.
- Platform Event administration delegates Event scope persistence/audit/outbox to the Operations owner action.
- Platform plan/settings/feature writes use Alliance immutable references and Platform-owned aggregates rather than mutating an Alliance model.
- Legal holds verify/lock subjects through Accounts/Alliance owner queries; no foreign Eloquent subject crosses into Platform.
- Intelligence Observations, Diplomacy, Sharing and Ingestion write paths use scalar IDs/immutable references and owner-owned write APIs.
- Ingestion stable-ID resolution now uses GameWorld/Alliance reference queries rather than foreign model persistence.
- Alliance Content revision writing consumes immutable `PlayerReference` rather than a GameWorld Player model.
- Plan entitlement and Platform usage calculations consume Alliance-owned membership/storage queries instead of foreign Eloquent models.
- Alliance data export resolves Alliance identity through `AllianceReferenceQuery` and records Platform-owned export metadata without a foreign aggregate object.

Executable evidence:
- Cross-context Eloquent model imports under `app/Contexts/**/Actions` = 0.
- Write-capable service scan has no known foreign Eloquent mutation dependency; remaining cross-context read composition is assigned to CA-P8/CA-P9.
- PHP syntax checks pass for the rewritten CA-P4 slices.

Known follow-on work:
- Composite/read-side foreign model imports remain in Intelligence and Platform read surfaces and are intentionally handled in CA-P8.
- GitHub CI/workflow changes remain deferred by user direction.

Blockers: none.
Safe to proceed: yes.
