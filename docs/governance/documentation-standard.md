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

The live documentation tree uses these groups only. Architecture decisions live in `architecture/decisions`; bounded contexts and their capabilities live in `architecture/contexts`; security and approval rules live in Governance.

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

Every rule has one authoritative home. Other documents link to it instead of restating a competing version.

- business invariant/ownership: Architecture;
- implementation path/pattern: Codebase;
- procedure: Operations;
- user outcome/terminology: Product;
- change/security/approval rule: Governance;
- generated/lookup catalogue: Reference.

## Context documents

A context/capability document normally covers purpose, ownership, actors/authority, invariants, workflow, cross-context dependencies and implementation link. Security, operations, interface and testing material is added where it provides useful context rather than as mandatory repeated subtrees.

## Current truth and decisions

Living architecture is the authoritative description of current behavior and ownership. Decision records capture durable rationale for choices that remain part of the current architecture.

## Maintenance trigger

Update documentation when a documented capability, ownership boundary, invariant, public/internal contract, authority rule, persistence/consistency rule, operational procedure, security requirement, configuration/recovery requirement or production status materially changes. Pure internal refactoring within the same documented contract normally needs only codebase/module-map updates if physical location changed.
