# Gift Code tests

These tests follow the [owner-first convention](../../../README.md). [Testing](../../../../docs/codebase/testing.md) owns execution commands, suite synchronization and isolation; [ADR-0043](../../../../docs/architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) records the decision.

## Resource boundaries

The adapter-only classes remain Feature tests because they exercise the real application container, configured adapters, Eloquent attribute casts and Laravel HTTP fakes. Their source models are unsaved and their scenarios do not read database rows, so they do not request database reset. Do not move them to Unit merely because database setup is absent.

| Class | Existing scenarios and required resources |
| --- | --- |
| [GiftCodeProviderFailureMatrixV3Test](Feature/GiftCodeProviderFailureMatrixV3Test.php) | Four methods covering nine HTTP-status mappings, malformed JSON/XML/HTML rejection, unstructured prose rejection and Facebook identity mismatch. Real Laravel and explicit HTTP fixtures; no database reset. |
| [GiftCodeSourceAdapterContractsTest](Feature/GiftCodeSourceAdapterContractsTest.php) | Seven methods covering registry keys, RSS/Atom/HTML parsing, nested prose rejection, document/entity rejection and observation bounds. Real registry/adapters, original HTTP fixtures and an unsaved source helper; no database reset. |
| [GiftCodeSourceAdaptersV3Test](Feature/GiftCodeSourceAdaptersV3Test.php) | Two canonical RSS/HTML ingestion methods. Retains RefreshDatabase, the persisted registeredSource helper, the actual ingestion action and assertions on stored codes and provenance. |

Both adapter-only classes enable `Http::preventStrayRequests()` before each case; every intended request must match an explicit response fixture. An unexpected request must fail, not reach a live provider or wait on a network timeout. This is class-local test isolation, not a change to production networking, timeout or rate-limit policy.

Commit `6bd54287` splits the original nine-method GiftCodeSourceAdaptersV3Test by resource requirements. All nine original methods and both original helpers remain byte-identical and present exactly once across the two resulting classes. Response documents, assertions and method names are preserved. Only imports, class organization and the new adapter-only setup hook change. The original class name continues to identify the two persisted scenarios.

This removes seven per-case database-reset requests by source analysis, not seven measured schema rebuilds or a demonstrated time saving. Both classes remain in the same owner-local Feature directory, which is already selected by full PHPUnit configuration and the specialized Gift Code workflow. No per-file discovery list or overlapping suite is added. The new file increases source-file count, not scenario count.

Other persistence boundaries remain separate. [GiftCodeBehaviorV3Test](Feature/GiftCodeBehaviorV3Test.php) and [GiftCodePullAdapterConformanceV3Test](Feature/GiftCodePullAdapterConformanceV3Test.php) retain their database setup and verify canonical ingestion, bounded source handling, idempotency and committed source state. Authorization, security, moderation and workspace classes retain their own fixtures. Do not remove their database isolation merely because the adapter-only classes do not need it.

When adding a scenario that needs persisted source state, place it with the existing database-backed contract. Keep unsaved objects local to each case and retain their application lifecycle; no process-wide model or response cache is introduced.

## Development scopes

From a prepared repository root, once test execution is authorized:

```sh
# Registration and document adapters; real Laravel, no database reset.
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Contexts/GameWorld/GiftCodes/Feature/GiftCodeSourceAdapterContractsTest.php

# Provider failures; real Laravel and explicit HTTP fixtures.
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Contexts/GameWorld/GiftCodes/Feature/GiftCodeProviderFailureMatrixV3Test.php

# Complementary persisted RSS/HTML ingestion scenarios.
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Contexts/GameWorld/GiftCodes/Feature/GiftCodeSourceAdaptersV3Test.php

# The owner's PHP behavior, including the classes above and other persistence contracts.
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/GameWorld/GiftCodes
```

The adapter-only commands are not persistence or complete application verification. Include the owner's persisted ingestion and transport cases after an adapter change, and the separately owned read-model/browser surfaces when affected. Full regression remains a separate requirement. No runner command above was executed during the source-only optimization hold. Source preservation and syntax/formatting checks do not establish runtime discovery, test-order independence or passing integration behavior.
