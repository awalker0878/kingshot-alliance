# Accounts context

Status: Current  
Implementation: `app/Contexts/Accounts`

Accounts owns global account identity and security. It answers **which account is operating the application**, not which game persona has authority.

## Capabilities

- [Account security](account-security.md) — registration, authentication, verification, profile, password, MFA/TOTP and recovery.

## Boundary

Accounts owns `User` identity. It does not own Player game state, Alliance membership/rank, Kingdom roles or Operations/Intelligence permissions. After authentication, game-domain requests resolve an active Player through GameWorld/PlayerContext behavior.

Platform Administrator is a User-scoped Platform grant, not an Accounts/game permission.