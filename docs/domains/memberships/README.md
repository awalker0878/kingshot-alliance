# Memberships domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract
**Status:** Current — R1–R5 rank model
**Code owner:** `app/Domain/Memberships`

## 1. Purpose

Memberships owns User↔Alliance membership, invitation lifecycle, membership status, and the authoritative KingShot Alliance rank.

## 2. Rank model

`AllianceMembership.rank` is one of `r1`, `r2`, `r3`, `r4`, or `r5`.

- accepted invitations create/reactivate membership at R1;
- R5 is the single Alliance owner/leader;
- R4 is officer;
- R3/R2/R1 are normal member hierarchy levels;
- specialist Authorization roles are additive and independent.

A partial unique database index prevents more than one active R5 in an Alliance. Application workflows ensure an active Alliance retains its R5.

## 3. Leadership lifecycle

Ordinary rank administration may assign R1–R4 but may not assign R5. R5 changes occur only through the leadership-transfer workflow. Transfer is atomic:

```text
current R5 -> R4
target      -> R5
```

Specialist roles on either membership are preserved.

R5 cannot leave or be deactivated through ordinary membership transitions before leadership is transferred.

## 4. Membership lifecycle

Membership status remains `invited`, `active`, `suspended`, `left`, or `removed`. Removal strips specialist roles. Reactivation returns the membership to R1; specialist responsibilities must be deliberately reassigned.

## 5. Authorization

`membership.manage` controls lower-rank member status administration. `roles.manage` controls specialist roles and R1–R4 rank administration. Rank hierarchy is enforced independently of specialist roles.

## 6. Related documentation

- [Authorization](../authorization/README.md)
- [Alliances](../alliances/README.md)
- [Events](../events/README.md)
- [`app/Domain/Memberships/README.md`](../../../app/Domain/Memberships/README.md)
