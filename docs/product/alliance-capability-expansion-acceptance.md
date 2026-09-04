# Alliance Capability Expansion — Acceptance

Status: Selected extension — implementation in progress

## ACE-01 Documentation and ownership

- Canonical product, architecture, frontend and delivery-ledger documents agree on owner boundaries.
- No new Alliance top-level bounded context exists for history, governance or screenshot import.
- Fresh-deployment implementation contains no compatibility shims, dual reads/writes or legacy aliases.

## ACE-02 Alliance Settings

- An active Player with current `alliance.manage` in the concrete Alliance may update Alliance `name`, `slug`, `language` and `timezone`.
- An ordinary member, inactive membership, wrong Alliance, wrong Kingdom or Platform Administrator without game-domain authority cannot update settings.
- Slug normalization, reserved-path rejection and uniqueness are deterministic.
- Language is an application-supported locale and timezone is a valid IANA timezone.
- Kingdom association and platform lifecycle controls are excluded from generic officer settings.
- The owner transaction records durable audit/outbox evidence with old/new values.

## ACE-03 Specialist roles

- Authorized officers can create, rename, change permissions and archive Alliance-local non-system roles.
- System roles cannot be archived or have protected semantics changed.
- Role keys remain stable after creation.
- Permission delegation is limited to the actor's current effective Alliance permissions.
- Self-escalation and cross-Alliance role mutation/assignment are rejected.
- Inactive/archived roles cannot receive new assignments.

## ACE-04 Membership governance history

- Authorized officers can view bounded chronological membership/invitation/rank/role/leadership facts for a member.
- History is composed from owner audit/outbox evidence and does not create a second authoritative state machine.
- Actor, target, timestamp, old/new values and owner source are shown when present.
- Cross-Alliance history is not retrievable.

## ACE-05 Roster screenshot evidence

- Roster screenshot intake is available only from the authorized Alliance Roster workflow.
- Upload and processing preserve immutable Evidence provenance and duplicate handling.
- Human review/correction is required before destination commit.
- Accepted facts create immutable Roster observations with screenshot/review provenance and exactly-once destination idempotency.
- Unsupported or absent fields remain unknown/missing.
- Reconciliation is factual only and never directly mutates Alliance Membership.
- Ambiguous identity requires review rather than guessing.

## ACE-06 Bulk rank/role operations

- No more than 50 explicit membership IDs may be previewed/committed.
- Preview returns stable `ready`, `blocked` or `skipped` results per target.
- Commit repeats current authority and invariant checks through the single-target owner Actions.
- State changed after preview is handled as a per-target failure/skip, never trusted from preview.
- R5 remains leadership-transfer only.
- Failed targets can be selected for retry without reapplying successful targets.

## ACE-07 Recruitment re-entry controls

- Controls are Alliance-local, recruiter-private and one of `normal`, `do_not_invite`, `reapply_after`, `review_required`.
- Optional reason/review date are audited.
- Conversion/invitation respects active controls.
- Expired `reapply_after` controls no longer block once their date is reached.
- Duplicate merge preserves the stricter unresolved control deterministically.
- Existing retention/anonymization policy remains authoritative.
- No global blacklist or public exposure is introduced.

## ACE-08 Alliance governance timeline

- Authorized officers can read a bounded chronological timeline of consequential settings, membership, role, leadership, recruitment, Content and Integration administration.
- Timeline entries retain owner source and handoff links where supported.
- Timeline owns no domain truth and performs no writes.
- Ordering/filtering and pagination are deterministic and scope bound.

## ACE-09 Composition integration

- Alliance Hall links/settings/actions are permission aware.
- Member Capability Profile exposes factual membership governance history.
- Command Overview adds only actionable reconciliation/recruitment reason codes backed by concrete owner state.
- Alliance Assistant may answer bounded factual settings/history/reconciliation questions and remains read-only; mutations return owner-workflow handoff only.

## ACE-10 Quality and release

- PHP tests, Pint, PHPStan, frontend lint/format/type/build and Architecture V3 checks pass.
- Changed pages have localization, keyboard/screen-reader/mobile coverage and visual regression where repository policy requires it.
- CodeQL and dependency review pass.
- Clean-database/fresh-schema execution passes.
- Relevant production image/container, staging and backup/restore gates pass where required by repository policy.
- Delivery ledger and capability catalogue are reconciled to the exact verified candidate before promotion to current complete.