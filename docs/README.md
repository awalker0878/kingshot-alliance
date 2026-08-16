# Kingshot Alliance documentation

Status: Current — Architecture V2

Documentation is organized by **what the reader is trying to understand**, not by mirroring every code package.

## Where do I go?

| I want to… | Go to |
| --- | --- |
| understand the system, boundaries, ownership and invariants | [Architecture](architecture/README.md) |
| find where/how something is implemented | [Codebase](codebase/README.md) |
| deploy, monitor, troubleshoot or recover the application | [System operations](operations/README.md) |
| understand what users can do | [Product](product/README.md) |
| understand engineering/security/documentation/production rules | [Governance](governance/README.md) |
| look up permissions, events, configuration, routes or API facts | [Reference](reference/README.md) |

## Canonical structure

```text
docs/
├── architecture/
│   ├── contexts/
│   └── decisions/
├── codebase/
├── operations/
├── product/
├── governance/
└── reference/
```

### Architecture

Architecture is organized around **bounded contexts containing capabilities**. Architecture V2 currently uses Accounts, GameWorld, Alliance, Operations, Intelligence, Communications and Platform. `app/Workflows`, `app/ReadModels` and `app/Shared` are explicit composition/technical layers, not extra business contexts.

### Codebase

Codebase documentation maps that logical model onto `app/Contexts`, Workflows, ReadModels, Shared, routes, persistence, frontend and tests. Physical folders implement architecture; they do not get to redefine it.

### Operations

`docs/operations` means running the software. This is intentionally distinct from the **Operations bounded context**, which owns Events and live game coordination.

### Product

Product documentation describes implemented user outcomes and terminology. Historical phase/DCP plans are not part of the live product model.

### Governance

Governance defines how changes are engineered, documented, secured and approved for production.

### Reference

Reference contains lookup-oriented material that should be derived/generated from code where practical.

## Source-of-truth precedence

Use the narrowest authoritative source:

1. executable code/database constraints define exact runtime behavior;
2. Architecture defines logical ownership, invariants and supported collaboration;
3. Codebase documents physical implementation patterns/locations;
4. Operations defines safe run/deploy/recovery procedure;
5. Product defines user-facing outcomes/terminology;
6. Governance defines change/security/approval requirements;
7. Reference summarizes mechanical values without overriding their code definitions.

When code and documentation disagree, determine whether the code or the intended contract is wrong—then fix both as part of the same change.

## No legacy documentation tree

The previous `docs/domains`, top-level `docs/adr`, top-level `docs/security`, phase/DCP documentation and other superseded taxonomy are intentionally not retained here. Useful current knowledge has been rewritten into the new owning area. Git history is the archive.