# Testing

Status: Current

The repository uses architecture, feature and unit tests plus static/lint/build checks.

## Architecture V2 verification

The V2 verification workflow explicitly checks:

- no runtime `App\Domain\*` imports;
- expected `app/Contexts`, `app/Workflows`, `app/ReadModels` and `app/Shared/Infrastructure` boundaries;
- Player persistence ownership;
- workflow/context authorization boundaries;
- rewritten V2 test naming/location;
- PostgreSQL migrations, Pint, Larastan and targeted V2 suites.

Rewritten V2 tests are organized under lower-case `v2` folders and use `*V2Test.php` naming where enforced by the workflow.

## General expectations

Test the behavior and invariant at the narrowest useful level. Protected mutations should cover authorization failure, scope isolation and concurrency/retry behavior where relevant. Cross-context tests should prove supported contracts rather than normalize direct persistence coupling.

Use repository commands such as `make check`, `make test` and targeted `php artisan test ...` suites as appropriate.