# Documentation standard

Status: Current — Architecture V3

Documentation describes the **current intended system**. It does not preserve superseded architecture descriptions, compatibility maps or historical decision narratives inside the live docs tree. Repository history provides historical traceability.

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

## What goes where

| Area | Question |
| --- | --- |
| Architecture | What are the bounded contexts/capabilities, ownership boundaries and invariants? |
| Codebase | How and where is the current architecture implemented? |
| Operations | How is the application deployed, monitored and recovered? |
| Product | What outcome/capability does the user receive? |
| Governance | What rules govern engineering, security, verification and approval? |
| Reference | What lookup-oriented facts are useful without explanatory narrative? |

## Architecture documentation

Architecture is organized around seven bounded contexts containing capabilities. Capability documentation should use the current capability name and ownership boundary.

Do not keep pages whose purpose is to explain a removed package, previous module name, prior architecture version or migration path. When a concept is replaced, update or replace the authoritative current document and remove the obsolete page.

## Codebase documentation

Codebase docs map the current architecture to the current intended source tree. Architecture V3 is capability-first:

```text
app/Contexts/<Context>/<Capability>/...
```

Do not document root technical buckets as accepted current structure.

## Canonical ownership

Every rule has one authoritative home. Other documents link to it rather than maintaining a competing copy.

- business invariant/ownership: Architecture;
- implementation pattern/path: Codebase;
- operating procedure: Operations;
- user outcome/terminology: Product;
- change/security/approval rule: Governance;
- mechanical lookup values: Reference.

## Maintenance trigger

Update documentation when a capability, ownership boundary, invariant, source structure, authority rule, integration contract, persistence/consistency rule, operational procedure, security requirement or production status materially changes.

Architecture and implementation changes are incomplete until stale documentation describing removed structures has been deleted or rewritten.