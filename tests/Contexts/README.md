# Context-owned architecture contracts

Use `Contexts/<Context>/<Capability>` for a boundary owned by that capability. Tests may inspect consumers in other owners without creating duplicate copies under those consumers. Repository-wide naming, dependency, scheduler and persistence rules remain at the Architecture root rather than being assigned to an arbitrary business owner.

| Owner | Focused contracts |
| --- | --- |
| Communications/Delivery | `DeliveryBoundaryTest`: generic delivery only; no source-domain reminder policy. |
| GameWorld/Kingdoms | `KingdomOperationalReadBoundaryTest`: active versus historical kingdom resolution across consumers. |
| GameWorld/KingdomTransfers | `TransferEvidenceWriteBoundaryTest`: every evidence-accepting writer uses the guard and preserves provenance in its idempotency fingerprint. |
| Operations/Events | `EventTypeOnboardingBoundaryTest`: catalogue/profile/workflow-dimension boundaries. |
| Intelligence/Evidence | `TransferEvidenceBoundaryTest`: evidence/transfer ownership, destination writers and scoped UI/API boundaries. |
| Intelligence/Evidence | `EvidenceReferenceContractTest`: pure reflection of the family-neutral reference interface. |
| Intelligence/Evidence | `EvidenceReferenceBindingTest`: real application-container registration of both the general and dedicated progression reference contracts. |

Reflection/source and application binding deliberately retain separate resource requirements. `EvidenceReferenceBindingTest` consolidates the general contract binding formerly embedded in the Transfer Feature class with the unchanged progression binding method; both still resolve the real Laravel container. `TransferEvidenceWriteBoundaryTest` owns the two unchanged source contracts, using repository-relative paths without Laravel startup. No binding assertion has been replaced with a source-string check or mock.

`Feature/Contexts/GameWorld/KingdomTransfers/TransferEvidenceReferenceGuardTest` retains the two behavioral methods and their original lookup fixture. It still boots Laravel because rejection goes through the real validation service. Its dependent evidence-pipeline, persistence, authorization and concurrency tests remain separate and must be included for behavioral changes.

Existing Alliance/Content HTTP and Platform/Integrations contracts continue using their actual application boundaries. Folder placement does not imply that every Architecture test is pure or that a source assertion replaces an integration check.

Run `composer test:architecture` for the complete tier, or select the exact owner path with `vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Intelligence/Evidence/Architecture`. Add the corresponding Feature/Integration paths for behavioral changes; see [Testing](../../docs/codebase/testing.md). These organization changes are source-checked only while test execution is paused.
