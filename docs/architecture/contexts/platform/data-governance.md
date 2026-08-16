# Platform — DataGovernance

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/DataGovernance`

DataGovernance owns platform retention, legal hold, data export and account-deletion orchestration behavior.

## Boundary

DataGovernance coordinates lifecycle/governance obligations without taking business ownership of another context's aggregates. Context-owned deletion/export effects are executed through explicit owner contracts where required.