<?php

declare(strict_types=1);

return [
    'active_player_session_key' => 'game_world.active_player_id',
    'gift_code_redemption_url' => env('KINGSHOT_GIFT_CODE_URL', 'https://ks-giftcode.centurygame.com/'),
    'gift_codes' => [
        'moderation' => env('GIFT_CODES_MODERATION', false),
        'approved_source_ingestion' => env('GIFT_CODES_APPROVED_SOURCE_INGESTION', false),
        'notification_fanout' => env('GIFT_CODES_NOTIFICATION_FANOUT', false),
        'redemption_workspace' => env('GIFT_CODES_REDEMPTION_WORKSPACE', false),
        'redemption_intelligence' => env('GIFT_CODES_REDEMPTION_INTELLIGENCE', false),
        'alliance_coverage' => env('GIFT_CODES_ALLIANCE_COVERAGE', false),
        'contributor_reputation' => env('GIFT_CODES_CONTRIBUTOR_REPUTATION', false),
        'source_webhook_ingestion' => env('GIFT_CODES_SOURCE_WEBHOOK_INGESTION', false),
        'source_webhook_secret' => env('GIFT_CODES_SOURCE_WEBHOOK_SECRET'),
        'x_bearer_token' => env('GIFT_CODES_X_BEARER_TOKEN'),
        'independent_evidence_threshold' => (int) env('GIFT_CODES_INDEPENDENT_EVIDENCE_THRESHOLD', 2),
        'max_redemption_attempts' => (int) env('GIFT_CODES_MAX_REDEMPTION_ATTEMPTS', 6),
        'fanout_batch_size' => (int) env('GIFT_CODES_FANOUT_BATCH_SIZE', 200),
        'catalog_page_size' => (int) env('GIFT_CODES_CATALOG_PAGE_SIZE', 25),
        'workspace_page_size' => (int) env('GIFT_CODES_WORKSPACE_PAGE_SIZE', 25),
        'max_session_codes' => (int) env('GIFT_CODES_MAX_SESSION_CODES', 100),
        'max_session_governors' => (int) env('GIFT_CODES_MAX_SESSION_GOVERNORS', 50),
        'max_session_items' => (int) env('GIFT_CODES_MAX_SESSION_ITEMS', 500),
        'intelligence_min_samples' => (int) env('GIFT_CODES_INTELLIGENCE_MIN_SAMPLES', 5),
        'intelligence_min_accounts' => (int) env('GIFT_CODES_INTELLIGENCE_MIN_ACCOUNTS', 5),
        'intelligence_window_hours' => (int) env('GIFT_CODES_INTELLIGENCE_WINDOW_HOURS', 168),
        'reminder_horizon_days' => (int) env('GIFT_CODES_REMINDER_HORIZON_DAYS', 30),
        'source_webhook_clock_skew_seconds' => (int) env('GIFT_CODES_SOURCE_WEBHOOK_CLOCK_SKEW_SECONDS', 300),
        'ingestion_batch_size' => (int) env('GIFT_CODES_INGESTION_BATCH_SIZE', 100),
        'ingestion_timeout_seconds' => (int) env('GIFT_CODES_INGESTION_TIMEOUT_SECONDS', 10),
        'transition_campaigns_per_run' => (int) env('GIFT_CODES_TRANSITION_CAMPAIGNS_PER_RUN', 10),
        'max_governors_per_account' => (int) env('GIFT_CODES_MAX_GOVERNORS_PER_ACCOUNT', 50),
    ],
];