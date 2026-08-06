# ADR 0007: Pin the Parallel Test Runner to the PHPUnit 12-Compatible Release

- **Status:** Accepted
- **Date:** 2026-08-06

## Context

Laravel 13 uses the PHPUnit 12 line in its supported starter baseline. ParaTest releases after 7.20.0 moved to PHPUnit 13, which is not compatible with that baseline.

## Decision

Use PHPUnit 12.5 and pin `brianium/paratest` to `7.20.0`. Keep serial and parallel Composer scripts so failures can be reproduced without worker concurrency.

## Consequences

- CI can exercise Laravel parallel testing without resolving an incompatible PHPUnit major.
- Dependabot must not automatically advance ParaTest until Laravel supports the corresponding PHPUnit major.
- The pin is reviewed when the Laravel testing dependency constraint changes.
