<?php

declare(strict_types=1);

return [
    'active_player_session_key' => 'game_world.active_player_id',
    'gift_code_redemption_url' => env('KINGSHOT_GIFT_CODE_URL', 'https://ks-giftcode.centurygame.com/'),
    'gift_codes' => [
        // off | shadow | authoritative
        'trust_v2' => env('GIFT_CODES_TRUST_V2', 'shadow'),
        'moderation' => env('GIFT_CODES_MODERATION', false),
        'approved_source_ingestion' => env('GIFT_CODES_APPROVED_SOURCE_INGESTION', false),
        'notification_fanout' => env('GIFT_CODES_NOTIFICATION_FANOUT', false),
        'independent_evidence_threshold' => (int) env('GIFT_CODES_INDEPENDENT_EVIDENCE_THRESHOLD', 2),
        'max_redemption_attempts' => (int) env('GIFT_CODES_MAX_REDEMPTION_ATTEMPTS', 6),
        'fanout_batch_size' => (int) env('GIFT_CODES_FANOUT_BATCH_SIZE', 200),
        'catalog_page_size' => (int) env('GIFT_CODES_CATALOG_PAGE_SIZE', 25),
    ],
];
