# Disaster recovery

Status: Current

A meaningful production recovery exercise must prove more than PostgreSQL restore.

## Recovery set

- database backup and integrity/provenance;
- private media/object storage recovery;
- application encryption key and required secrets;
- immutable application release identity;
- Redis/queue re-establishment and reconciliation plan;
- DNS/ingress/mail/other external dependency ownership.

## Exercise acceptance

An exercise should record recovery point/time observations, release identity, integrity verification, application readiness, authentication, representative scoped data, private media access and background-processing reconciliation.

Do not place secret values or sensitive environment topology in the repository evidence record; store non-secret evidence identifiers and accountable ownership.