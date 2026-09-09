# Context-owned architecture contracts

Use `Contexts/<Context>/<Capability>` for a boundary owned by that capability. Tests may inspect consumers in other owners without creating duplicate copies under those consumers. Repository-wide naming, dependency, scheduler and persistence rules remain at the Architecture root rather than being assigned to an arbitrary business owner.

| Owner | Focused contracts |
| --- | --- |
| Communications/Delivery | `DeliveryBoundaryTest`: generic delivery only; no source-domain reminder policy. |
| GameWorld/Kingdoms | `KingdomOperationalReadBoundaryTest`: active versus historical kingdom resolution across consumers. |
| Operations/Events | `EventTypeOnboardingBoundaryTest`: catalogue/profile/workflow-dimension boundaries. |
| Intelligence/Evidence | `TransferEvidenceBoundaryTest`: evidence/transfer ownership, destination writers and scoped UI/API boundaries. |
| Intelligence/Evidence | `EvidenceReferenceContractTest`: pure reflection of the family-neutral reference interface. |
| Intelligence/Evidence | `GovernorProgressionEvidenceBindingTest`: real application-container registration of the dedicated progression reference contract. |

The last two classes deliberately separate resource requirements. They contain the two unchanged methods formerly in `GovernorProgressionEvidenceReferenceBoundaryV3Test`; one no longer boots Laravel, while the binding test still does. Both stay in Architecture and in the existing Intelligence path selection. No binding assertion has been replaced with a source-string check or mock.

Existing Alliance/Content HTTP and Platform/Integrations contracts continue using their actual application boundaries. Folder placement does not imply that every Architecture test is pure or that a source assertion replaces an integration check.

Run `composer test:architecture` for the complete tier, or select the exact owner path with `vendor/bin/phpunit --fail-on-empty-test-suite tests/Architecture/Contexts/Intelligence/Evidence`. Add the corresponding Feature/Integration paths for behavioral changes; see [Testing](../../../docs/codebase/testing.md). These organization changes are source-checked only while test execution is paused.
