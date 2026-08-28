<?php

declare(strict_types=1);

return [
    /*
     * KINGDOMS-004 source adapters are explicit repository/operator-approved
     * implementations. The production allowlist remains intentionally empty:
     * generic ingestion infrastructure does not approve a real source.
     */
    'ingestion_adapters' => [],

    /*
     * Operational K4 data is intentionally shorter-lived than canonical K1/K3
     * history. Promoted canonical snapshots/observations retain their bounded
     * provenance independently of these operational retention windows.
     */
    'ingestion_retention' => [
        'payload_days' => 30,
        'terminal_candidate_days' => 90,
        'quarantined_candidate_days' => 180,
        'batch_days' => 90,
        'disabled_compaction_days' => 30,
    ],

    /*
     * These thresholds support operator/monitoring health checks only. They do
     * not change promotion identity, tenancy, source approval, or retry rules.
     */
    'ingestion_health' => [
        'overdue_minutes' => 5,
        'stale_pending_minutes' => 15,
        'quarantined_threshold' => 25,
        'recent_failure_minutes' => 60,
    ],

    /*
     * Progression observation freshness is presentation metadata only. An
     * observation older than this threshold remains an immutable factual
     * observation with its original provenance and dataset pin.
     */
    'progression' => [
        'observation_stale_after_days' => 30,
    ],

    /*
     * Intelligence Change Detection derives read-side signals from owner
     * histories. These are presentation/materiality rules only: they never
     * rewrite source observations, create strategic intent, or establish a new
     * signal truth store. Bump rule_version when derivation semantics change.
     */
    'change_detection' => [
        'rule_version' => '1',
        'alliance_power_absolute' => 100_000_000,
        'alliance_power_percent' => 5.0,
        'member_count_absolute' => 3,
        'alliance_observation_stale_days' => 30,
        'progression_observation_stale_days' => 30,
        'transfer_expiring_days' => 7,
        'bear_hunt_minimum_runs' => 3,
        'recent_days' => 45,
        'max_signals' => 20,
    ],

    /*
     * KINGDOMS-005 invitation tokens bootstrap two-party human consent only.
     * They are one-time secrets, hash-only while pending, and do not grant data
     * access until a manager accepts under a valid same-Kingdom context.
     */
    'shared_intelligence' => [
        'invitation_ttl_hours' => 72,
    ],

    /*
     * K5 operational consent/grant metadata is shorter-lived than canonical K3
     * observations and durable Audit/outbox evidence. Active shares/grants and
     * canonical source observations are never eligible for this cleanup.
     */
    'shared_intelligence_retention' => [
        'expired_invitation_days' => 30,
        'terminal_share_days' => 180,
        'removed_target_days' => 90,
    ],
];
