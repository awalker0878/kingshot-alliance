# ADR-0014: Model authentication as sign-in methods attached to Kingshot Alliance Users

Status: Accepted

Date: 2026-09-02

## Context

ADR-0013 intentionally used exclusive `password|google` account types to avoid ambiguous linking while Accounts was first being hardened. The application remains undeployed with no Accounts compatibility burden, and the next product contract requires intentional Google attachment, optional local passwords, and passkeys. Keeping a primary authentication-type discriminator would make those capabilities contradictory and would force unsafe type conversion semantics.

## Decision

1. `User` is the permanent Kingshot Alliance application identity.
2. Password, Google and passkeys are sign-in methods attached to the User.
3. No `authentication_type` or equivalent primary-method discriminator exists in the canonical schema.
4. A User may have zero-or-one password, zero-or-one Google provider identity and zero-or-many passkeys, subject to the invariant that active Users always retain at least one usable method.
5. Google provider identity is keyed by `provider + provider_subject`; provider email is metadata only.
6. Matching email never automatically links, merges or authenticates an existing User.
7. Google connection is an authenticated recent-proof operation targeting the currently authenticated User.
8. Credential removal is rejected if no usable method would remain.
9. Account email remains an Accounts-owned verified address independent of provider email.
10. TOTP remains optional MFA, not another primary sign-in method.
11. User-verifying passkeys satisfy authentication without an additional TOTP prompt; password and Google continue through TOTP when configured.
12. Account merging is outside this program.
13. The schema is fresh: canonical create migrations are changed directly and no compatibility/backfill path is introduced.

## Consequences

- ADR-0013 remains historical evidence for the completed Accounts expansion but its exclusive-primary-authentication decision is superseded by this ADR.
- Authentication availability is derived from actual credential state through an Accounts-owned sign-in-method policy.
- Password reset depends on password existence, not an account type.
- Google email updates cannot mutate the Kingshot Alliance account email.
- Recent authentication becomes method-agnostic.
- Passkeys can be added without reclassifying the User.
- Existing game/Alliance/Kingdom authorization boundaries remain unchanged.
