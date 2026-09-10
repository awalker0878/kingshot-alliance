# Gift Code tests

These tests follow the [owner-first convention](../../../README.md). [Testing](../../../../docs/codebase/testing.md) owns execution commands, suite synchronization and isolation; [ADR-0043](../../../../docs/architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) records the decision.

## Resource boundaries

[GiftCodeProviderFailureMatrixV3Test](Feature/GiftCodeProviderFailureMatrixV3Test.php) belongs in Feature because it exercises the real application container, configured adapters, Eloquent attribute casts and Laravel HTTP fakes. It does not persist its source models or read database rows, so it does not request database reset. Do not move it to Unit merely because its database setup is absent.

Its four existing methods retain the nine HTTP-status mappings, malformed JSON/XML/HTML rejection, rejection of unstructured prose as an observation, and Facebook identity mismatch behavior. The class enables `Http::preventStrayRequests()` before each case; every intended request must match an explicit response fixture. An unexpected request must fail, not reach a live provider or wait on a network timeout. This is class-local test isolation, not a change to production networking, timeout or rate-limit policy.

Persisted ingestion and delivery behavior remain separate. [GiftCodeBehaviorV3Test](Feature/GiftCodeBehaviorV3Test.php), [GiftCodeSourceAdaptersV3Test](Feature/GiftCodeSourceAdaptersV3Test.php) and [GiftCodePullAdapterConformanceV3Test](Feature/GiftCodePullAdapterConformanceV3Test.php) retain their database setup and verify canonical ingestion, bounded source handling, idempotency and committed source state. Other authorization, security, moderation and workspace classes retain their own fixtures. Do not remove their database isolation merely because the failure matrix does not need it.

When adding a scenario that needs persisted source state, place it with the existing database-backed contract. Keep the failure matrix's unsaved objects local to each case and retain its application lifecycle; no process-wide model or response cache is introduced.

## Development scopes

From a prepared repository root, once test execution is authorized:

```sh
# Adapter failure contracts only; real Laravel, explicit HTTP fixtures, no DB reset.
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Contexts/GameWorld/GiftCodes/Feature/GiftCodeProviderFailureMatrixV3Test.php

# The owner's PHP behavior, including database-backed contracts.
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/GameWorld/GiftCodes
```

The first command is not persistence or complete application verification. Include the owner's persisted ingestion and transport cases after an adapter change, and the separately owned read-model/browser surfaces when affected. Full regression remains a separate requirement. No runner command above was executed during the source-only optimization hold, and no elapsed-time saving is claimed for removing the unused reset trait.
