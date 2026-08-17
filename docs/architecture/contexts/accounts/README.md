# Accounts context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts`

Accounts owns global User identity and account security. It answers **which account is operating the application**, not which game persona has authority.

## Capabilities

```text
Accounts/
├── Identity/
├── Registration/
├── Authentication/
├── Credentials/
├── EmailVerification/
├── Profile/
└── MultiFactorAuthentication/
```

- **Identity** owns User identity.
- **Registration** owns account creation.
- **Authentication** owns sign-in/sign-out/session establishment and confirmation.
- **Credentials** owns password and credential lifecycle.
- **EmailVerification** owns verification flows/state.
- **Profile** owns account profile changes.
- **MultiFactorAuthentication** owns MFA/TOTP challenge and recovery.

## Boundary

Accounts does not own Player game state, Alliance membership/rank, Kingdom roles or Operations/Intelligence permissions. Game-domain requests resolve a Player through `GameWorld/Players`.

Platform Administrator is a User-scoped Platform grant, not an Accounts game permission.

## Physical rule

Accounts does not use context-root `Actions`, `Models`, `Services` or `Http` buckets in V3. Each implementation class belongs under the capability that owns the behavior.