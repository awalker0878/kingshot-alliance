# Platform — DataGovernance

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/DataGovernance`

DataGovernance owns platform retention, legal hold, data export and account-deletion orchestration behavior.

## Boundary

DataGovernance coordinates lifecycle/governance obligations without taking business ownership of another context's aggregates. Context-owned deletion/export effects are executed through explicit owner contracts where required.

## Account deletion transitions

Request, cancellation and processing acquire the current Accounts lock before the deletion request lock. Platform calls the Accounts lifecycle owner inside the same database transaction, so request status, account metadata, audit and Communications notification intent commit or roll back together. Notification queuing writes durable intent only; external delivery remains outside these transactions.

Repeating a pending or blocked request preserves its original cooling-off deadline. Repeating a processed request produces no new lifecycle effects; cancellation of a completed account returns no change. A request after cancellation starts a new seven-day cooling-off period and produces a new security notification. Each real transition is atomic; retries that observe an already-applied state do not duplicate its audit or notification.

Finalization holds the Accounts lifecycle lock while obtaining the current Player set and executing owner cleanup/release/anonymization. GameWorld ownership assignment and reconciliation use the same account-before-Player order and reject finalized owners through the Accounts query contract. The ownership set therefore cannot gain another Player between enumeration and finalization.
