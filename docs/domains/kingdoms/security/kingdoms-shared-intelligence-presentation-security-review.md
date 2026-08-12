# KINGDOMS-005 Slice D presentation security review

[← Kingdoms security profile](README.md)

**Scope:** `K5-P4` / Slice D — first-party shared-intelligence presentation and management  
**Status:** Complete  
**Runtime candidate:** `9a095ae62e9b913ece6d619c3744574f0b91fd6f`

## 1. Review purpose

Slice D exposes the accepted P1–P3 cross-tenant sharing contracts through first-party pages. The primary new risks are page-prop overexposure, management-state leakage to ordinary members, plaintext invitation persistence, client-side history-window expansion, accessibility regressions that make consent controls ambiguous, and accidental widening of recipient authority.

P4 is acceptable because the UI reuses accepted domain queries/mutations, keeps management data manager-only, projects only reviewed safe fields, retains opaque bounded history navigation and does not introduce a new public integration boundary.

## 2. Authorization and presentation split

The member-safe sharing page is available within the active Alliance tenant and presents only accepted safe current/history facts. Management capability is not inferred from page presence; a separate `canManage` flag is computed from the existing Kingdom management authorization.

The management page requires `kingdom.manage`. A normal member is forbidden from that workspace.

Consent/grant mutations continue to execute through the accepted P1/P2 authorization paths, including recent-password protection where defined. The Vue pages do not implement an independent authorization model.

## 3. Safe page-prop boundary

Member props are limited to active Alliance display context, `canManage`, accepted current facts and one selected bounded history projection.

Manager props are limited to bounded agreement/grant/display metadata required to operate sharing.

Focused tests prove page props exclude:

- invitation token hashes;
- invitation plaintext after the creation response;
- source manager notes;
- observation/source-tracking private IDs not part of the accepted projection;
- actor identities;
- correction/invalidation reason/linkage;
- K4 adapter/subscription/batch/candidate/cursor/source provenance;
- raw source responses/secrets;
- Audit/outbox internals; and
- unrelated roster/transfer/diplomacy/contact data.

P4 explicitly constructs reviewed arrays; it does not serialize source models into Inertia props.

## 4. One-time invitation secret handling

Invitation plaintext is a capability-bearing secret and is therefore handled separately from ordinary page state.

Creation uses the authenticated JSON endpoint with CSRF protection. The returned plaintext token:

- exists only in the creation response and Vue component memory;
- is never placed in session flash data;
- is never an Inertia prop;
- can be copied through typed browser clipboard access;
- can be explicitly cleared from component memory; and
- is absent from subsequent server-rendered manager-page props.

The database stores only the one-way hash while an invitation is pending.

## 5. Terminal secret erasure

Protected P4 validation exposed that the accepted P1 schema/action combination retained `invitation_token_hash` after an invitation became consumed or terminal.

Although the retained value was one-way hashed rather than plaintext, retaining an unusable capability identifier provided no runtime benefit and increased unnecessary secret-derived data lifetime.

P4 therefore clears the stored hash on:

- acceptance;
- decline; and
- revoke.

Recipient leave occurs only after acceptance, where the hash has already been erased.

This is lifecycle minimization of the existing P1 secret, not a new retention-management feature or expansion of sharing authority.

## 6. Forward schema and rollback security

The accepted P1 migration created the invitation hash column as non-nullable. P4 does not rewrite that accepted historical migration.

Instead, the forward `030000` migration makes the column nullable. Rollback to the historical schema replaces null terminal hashes with deterministic per-share retired placeholders before restoring the non-null constraint. Reapplication recognizes only the expected retired placeholder for terminal rows and converts it back to null.

Security properties of this design:

- no original invitation secret or original token hash is reconstructed during rollback;
- retired placeholders are deterministic database compatibility values, not valid invitation credentials;
- pending invitation hashes remain unchanged and uniquely addressable;
- terminal state/recipient binding survives rollback/reapply; and
- reapplication restores the minimized null state.

Focused migration tests and the full Kingdoms dependency-order round trip cover this behavior.

## 7. History anti-extraction boundary

P4 does not expose P3's internal fixed `asOf` as a client-editable field.

The recipient UI can request history only by a share target already obtained from the safe current projection and can continue only with the server-issued opaque encrypted cursor.

The UI cannot directly choose source observation IDs, offsets, historical timestamps or cumulative counters. Each continuation request re-runs live agreement/grant/context authorization and remains bounded to 50 rows/page and 250 accepted observations/traversal.

This preserves the P3 defense against repeated arbitrary historical windows.

## 8. Management-state and recipient-authority boundary

Ordinary member presentation does not expose consent-token state, agreement lifecycle controls, grant management or source trackable-target inventory.

Recipient presentation remains read-only with respect to source intelligence. P4 adds no action to:

- mutate source observations;
- convert received facts into recipient tracking;
- copy received history into canonical recipient observation rows;
- re-share received observations as source-owned targets; or
- enumerate other Alliances through a tenant directory/search surface.

Source ownership remains the anti-transitive-sharing anchor.

## 9. Browser and CSRF posture

The app shell supplies the standard CSRF meta token used by the one-time invitation JSON POST. Mutations performed through Inertia continue to use the framework's same-origin/CSRF protections.

The invitation token input disables autocomplete and spellcheck and is not persisted by server page state. Clipboard access is explicit user action; clearing/navigation removes the component-memory copy.

P4 does not add browser local storage, session storage, cookies or client-side analytics for sharing secrets/data.

## 10. Accessibility as a security/usability control

Consent and sharing controls use native labelled form controls and buttons. Tables use captions and bounded horizontal overflow. Invitation creation feedback uses status/alert semantics.

These controls reduce ambiguity around which Alliance action is being performed and make security-relevant consent/revoke/remove operations usable through standard assistive technology semantics.

The existing Kingdoms accessibility architecture suite enforces the new page source contracts, including absence of an `asOf` control.

## 11. Logging, public exposure and residual risk

P4 adds no public API, webhook, anonymous feed, external integration credential or third-party sharing transport.

Invitation plaintext, history cursors and shared observation payloads must not be added to general Audit/outbox/logging beyond the already-reviewed metadata-only business events.

Residual operational risks—long-lived terminal agreement/grant records, realistic-volume workload behavior, authorization-safe caching and retention cleanup—remain P5 scope. P5 must not weaken live authorization, history bounds or one-time-secret erasure.

## 12. Validation evidence

Runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f` passed:

- Dependency Review `31569202741`;
- CodeQL `31569202422`;
- CI `31569202418`;
- Pint 556 files;
- PHPStan/Larastan 393/393 with zero errors;
- 448 tests / 10,160 assertions;
- clean migrations and nullable-hash rollback/reapply evidence;
- frontend dependency audit, ESLint, locked Prettier/Tailwind check, Vue typecheck and production build;
- immutable image build;
- ephemeral staging deployment;
- backup/restore demonstration;
- image vulnerability scan; and
- staging cleanup.

Focused tests prove member/manager prop isolation, manager-only access, non-persistence of invitation plaintext, terminal hash erasure, fail-closed invitation lifecycle/context changes, bounded opaque history navigation and accessible first-party controls.

See [Slice D validation](../product/kingdoms-shared-intelligence-slice-d-validation.md) and [living shared-intelligence contract](../shared-intelligence.md).
