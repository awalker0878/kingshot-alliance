# Accounts — Registration

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Registration`

Registration owns creation of a User account and Accounts-owned registration invariants.

## Application boundary

Registration exposes an Accounts-owned Action for account creation. HTTP controllers and cross-context onboarding workflows call that Action rather than persisting `User` directly.

When registration is part of invitation onboarding, `Workflows/AccountOnboarding` composes Accounts registration, GameWorld Player claiming and Alliance invitation acceptance atomically under [ADR-0019](../../adr/0019-atomic-account-onboarding-owner-composition.md). Any rejection rolls back all owner writes, including identity history, audit and outbox. `RegisterUser` defers verification mail until the outermost transaction commits. Existing-account acceptance uses the same owner sequence and locks the current Accounts email/lifecycle before claiming the Player.
