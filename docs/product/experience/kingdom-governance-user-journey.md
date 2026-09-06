# User journey — Kingdom Governance

Status: Current implementation; verification pending quality gates.

## Normal Kingdom administration

```text
recently authenticated account
 -> active Player + Alliance/Kingdom context
 -> Governance verifies kingdom.roles.manage
 -> inspect current roles and effective assignments
 -> optionally search/filter Governors
 -> assign/revoke permanent or bounded roles
 -> owner transaction re-checks authority and target Kingdom
 -> durable audit/outbox evidence
 -> action receipt
```

## Administrator handoff

```text
effective Kingdom Admin
 -> select replacement Player in same Kingdom
 -> choose add or replace-self mode
 -> protected mutation establishes replacement first
 -> optional actor-admin revocation
 -> transaction verifies at least one effective administrator remains
 -> durable handoff evidence
```

## Break-glass recovery

```text
recently authenticated Platform Administrator
 -> open separate Platform recovery surface
 -> select Kingdom + replacement Player
 -> provide explicit recovery reason
 -> Platform authority authorizes workflow only
 -> Governance locks/repairs Player-scoped administrator assignment
 -> Operations re-provisions its own default role grants
 -> durable audit/outbox evidence identifies Platform actor
 -> Platform account still has no Kingdom game permission
```

## Authority and health review

```text
Kingdom role manager
 -> inspect effective permission/role projection
 -> optionally ask who currently holds a permission
 -> inspect bounded Governance audit history
 -> inspect health/drift findings
 -> if deterministic system-policy drift exists, explicitly reconcile
 -> owner-scoped Governance + Operations permission policies restored
```

## Temporary delegation

Future-effective roles do not authorize early. Expired/revoked roles cease authorizing immediately from time/state evaluation even before the hourly lifecycle worker records expiry evidence. Temporary administrator authority requires another administrator to survive its expiry.
