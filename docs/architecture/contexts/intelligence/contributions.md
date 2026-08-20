# Intelligence — Contributions

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/Contributions`

Contributions owns durable contribution facts, history and reporting behavior.

Contribution history is attributed to durable Player identity and is not rewritten merely because current Alliance membership or placement changes.

Approval review supports no more than 50 explicit Contribution record IDs per bulk command. Preview reacquires current Alliance Intelligence authority and distinguishes pending, approved, reversed and unavailable records. Commit delegates every eligible item to `ApproveContributionRecord`, so authority and state are checked again in the owning transaction and each successful record retains its normal audit and outbox evidence. The shared bulk result adds aggregate evidence and failed-record retry without replacing the immutable record history.
