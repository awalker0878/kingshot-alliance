# Documentation standard

Status: Current

Documentation is organized by **reader intent**, while architecture itself is organized by **bounded context and capability**.

## Canonical top-level groups

```text
docs/
├── README.md
├── architecture/
├── codebase/
├── operations/
├── product/
├── governance/
└── reference/
```

Do not recreate top-level `domains/`, `adr/`, `security/`, `contexts/`, `wiki/`, `legacy/` or phase/DCP trees. ADRs belong in `architecture/decisions`; security policy belongs in Governance; bounded contexts belong in `architecture/contexts`.

## What goes where

| Area | Question |
| --- | --- |
| Architecture | What is the system, who owns the rule/data, and why? |
| Codebase | Where/how is that architecture implemented? |
| Operations | How is the application deployed, monitored and recovered? |
| Product | What outcome/capability does the user receive? |
| Governance | What rules govern changes, security and approval? |
| Reference | What lookup-oriented facts are useful without explanatory narrative? |

## Canonical ownership

Every rule should have one authoritative home. Other documents link to it instead of restating a competing version.

- business invariant/ownership: Architecture;
- implementation path/pattern: Codebase;
- procedure: Operations;
- user outcome/terminology: Product;
- change/security/approval rule: Governance;
- generated/lookup catalogue: Reference.

## Context documents

A context/capability document should normally cover purpose, ownership, actors/authority, invariants, workflow, cross-context dependencies and implementation link. Do not create mandatory `security/operations/interfaces/testing` subtrees under every capability.

## Current truth vs decisions

Living architecture describes current truth. Decision records explain why durable choices were made. Do not require a reader to reconstruct current behavior by replaying old ADR history.

## Historical documentation

Git history is the archive. Superseded phase, migration-program or old taxonomy documents are deleted from the live tree once their current useful knowledge has been incorporated.

## Maintenance trigger

Update documentation when a documented capability, ownership boundary, invariant, public/internal contract, authority rule, persistence/consistency rule, operational procedure, security requirement, configuration/recovery requirement or production status materially changes. Pure internal refactoring within the same documented contract normally needs only codebase/module-map updates if physical location changed.