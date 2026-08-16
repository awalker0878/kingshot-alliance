# Account security

Status: Current  
Context: Accounts  
Implementation: `app/Contexts/Accounts`

## Purpose

Provide the account-assurance boundary used before any Player-scoped or Platform-scoped behavior is considered.

## Owns

- User registration;
- sign-in/sign-out and session establishment;
- profile and verified-email state;
- password confirmation/change/reset;
- MFA/TOTP challenge and recovery mechanisms.

## Invariants

Authentication proves the operating User account only. It does not grant Alliance/Kingdom/Event authority. A game request must separately resolve a Player that belongs to the authenticated User.

Account-security material such as passwords, MFA secrets, recovery codes and authentication tokens must not be logged or copied into documentation/evidence.

## Consumers

GameWorld consumes authenticated User identity when validating Player ownership. Platform consumes User identity plus Platform-specific grants/account-assurance requirements for cross-tenant administration.