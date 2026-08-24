<?php

declare(strict_types=1);

return [
    'provider' => env('ALLIANCE_ASSISTANT_PROVIDER', 'deterministic'),
    'max_question_length' => (int) env('ALLIANCE_ASSISTANT_MAX_QUESTION_LENGTH', 500),
    'rate_limit_per_minute' => (int) env('ALLIANCE_ASSISTANT_RATE_LIMIT_PER_MINUTE', 30),
    'event_past_days' => (int) env('ALLIANCE_ASSISTANT_EVENT_PAST_DAYS', 0),
    'event_future_days' => (int) env('ALLIANCE_ASSISTANT_EVENT_FUTURE_DAYS', 90),
    'content_result_limit' => (int) env('ALLIANCE_ASSISTANT_CONTENT_RESULT_LIMIT', 5),
    'observation_result_limit' => (int) env('ALLIANCE_ASSISTANT_OBSERVATION_RESULT_LIMIT', 5),
];
