# Accounts context

Status: Current  
Implementation: `app/Contexts/Accounts`

## Purpose

Accounts owns global account identity and account security. It answers **who is operating the application**, not which game persona currently has authority.

## Owns

- User account identity;
- registration and authentication;
- sessions;
- profile and verified email state;
- password reset/change;
- MFA/TOTP and recovery.

## Does not own

- Player game identity or current Kingdom;
- Alliance membership/rank/specialist roles;
- Kingdom/game permissions;
- Platform administration policy beyond exposing the authenticated User identity.

## Key invariant

Authentication establishes a User. Game behavior must then resolve an active Player before Player-scoped authority is evaluated.