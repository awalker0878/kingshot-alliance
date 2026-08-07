<?php

declare(strict_types=1);

return [
    'registration_mode' => env('REGISTRATION_MODE', 'open'),
    'active_alliance_session_key' => 'identity.active_alliance_id',
    'invitation_ttl_hours' => (int) env('INVITATION_TTL_HOURS', 72),
];
