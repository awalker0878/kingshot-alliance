# Context-owned tests

Use `tests/Contexts/<Context>/<Capability>/<Tier>`: Accounts, Alliance, GameWorld, Operations, Intelligence, Communications and Platform keep the same ownership boundaries as the application. A capability folder brings its Unit, Feature, Integration, Architecture, Frontend and Browser coverage together where those types exist.

Examples include `Alliance/Recruitment/Feature`, `Alliance/Recruitment/Integration/Concurrency`, `GameWorld/KingdomTransfers/Architecture` and `Intelligence/Evidence/Unit`. Do not create empty execution folders or duplicate a test under every owner it reads.

Source boundaries stay with the owner of the invariant. `Communications/Delivery/Architecture` protects generic delivery policy; `GameWorld/Kingdoms/Architecture` protects active/historical resolution; `Operations/Events/Architecture` protects catalogue and workflow-dimension boundaries.

`Intelligence/Evidence/Architecture/EvidenceReferenceContractTest.php` inspects the family-neutral interface without application startup. Its sibling `EvidenceReferenceBindingTest.php` verifies both real application bindings. `GameWorld/KingdomTransfers/Architecture/TransferEvidenceWriteBoundaryTest.php` checks source ownership and fingerprints, while the corresponding Feature guard test retains Laravel validation. These are complementary contracts, not duplicates to merge for speed.

Select an owner with `vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Alliance/Recruitment`, or append a type such as `Feature`. An owner selection does not include its separately owned read models, cross-owner workflows or browser runner. Include them when affected, and broaden for shared infrastructure changes.

Repository-wide architecture contracts live under `tests/System/Architecture`, not an arbitrary business context. See [test navigation](../README.md) and [Testing](../../docs/codebase/testing.md).
