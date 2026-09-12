# Shared test support

[ADR-0043](../../docs/architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) reserves this root for genuinely cross-owner test support. Place area-specific data and helpers under the owner's Fixtures or Support folder, outside execution-type directories. These files are not independent suites and must not be named as discoverable test classes.

## Intentional shared residents

| Helper | Why it stays shared |
| --- | --- |
| `ScenarioFactory` | Builds common account, Player and Alliance scenarios used across contexts, read models, workflows and acceptance contracts. |
| `RepositoryPath` | Resolves source paths for multiple independent source-contract owners without application startup or content caching. |
| `MigrationReferenceData` | Supports the shared test lifecycle and worker-specific migration reference restoration; it is not owned by the business catalogues it preserves. |

The common TestCase remains at the test root. Its isolation checks live in Shared/Testing/Integration. The earlier reference-reset repair still needs runtime verification; file placement does not certify it. Do not add global fixture seeding or reuse mutable test state to justify a shared helper.

## Owner-specific support

The WebAuthn assertion and registration helpers live in [Accounts/Authentication/Support](../Contexts/Accounts/Authentication/Support). Tests under Accounts/Identity import that owning helper explicitly when required. The collection builder lives in [RecruitmentManagement/Support](../ReadModels/RecruitmentManagement/Support).

Versioned extraction corpora live in [Intelligence/Evidence/Fixtures](../Contexts/Intelligence/Evidence/Fixtures), and the PHP/browser geometry corpus lives in [GameWorld/KingdomMaps/Fixtures](../Contexts/GameWorld/KingdomMaps/Fixtures). Visual fixtures live beside their rendered surface; cross-capability visual setup lives in [System/Acceptance/Fixtures](../System/Acceptance/Fixtures).

Keep meaningful corpus/protocol versions, fixture identities, resource boundaries and consumer references intact during a move. Cross-owner reuse does not require copying a fixture into both owners. Source/hash checks do not replace runner discovery, fixture execution or order/isolation verification.
