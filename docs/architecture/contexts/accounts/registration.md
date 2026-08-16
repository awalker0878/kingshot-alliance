# Accounts — Registration

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Registration`

Registration owns creation of a User account and Accounts-owned registration invariants.

## Application boundary

Registration exposes an Accounts-owned Action for account creation. HTTP controllers and cross-context onboarding workflows call that Action rather than persisting `User` directly.

When registration is part of a larger invitation/onboarding process, `Workflows/AccountOnboarding` coordinates the Accounts and Alliance owner Actions.