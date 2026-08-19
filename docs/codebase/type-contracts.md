# Type contracts

Status: Current — Architecture V3

Static analysis is a required design gate. PHPStan must pass without a baseline file, blanket suppressions or compatibility-only adapters.

## Boundary rules

- Cross-context commands and reads exchange scalar IDs, enums and immutable value objects; they do not exchange foreign Eloquent models.
- Authentication consumers use `AuthenticatedAccount` methods instead of relying on concrete-model magic properties.
- A method documented as returning a `list<T>` must normalize numeric keys with `array_values()`.
- Lookup methods that intentionally accept keyed ID collections document `array<string>`; use `list<string>` only when ordering and contiguous keys are part of the input contract.
- Array payloads crossing an Action, Query, ReadModel or HTTP boundary declare their value type or array shape.
- Inline PHPDoc with multiple tags is not used. Put each `@param` and `@return` tag on its own line so tooling parses the complete contract.

## Eloquent attributes

Laravel casts define runtime behavior. Model PHPDoc defines the corresponding static contract when an attribute is consumed directly:

- enum casts use the enum type;
- date/time casts use `Carbon` or `Carbon|null`;
- JSON/array casts declare their key and value types where known;
- computed/select-sub attributes are read through `getAttribute()` and cast at the composition edge.

When a cast changes, update the model PHPDoc and affected tests in the same pull request.

## Verification

Run the repository quality gates before merging:

```bash
make check
make test
```

The Architecture V3 workflow additionally boots routes, migrates the test schema, runs PHPStan and executes the full PHPUnit suite. A new static-analysis finding is a code defect to fix, not a reason to introduce a baseline entry.
