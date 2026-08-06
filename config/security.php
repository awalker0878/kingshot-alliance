<?php

declare(strict_types=1);

return [
    'csp_enabled' => (bool) env('SECURITY_CSP_ENABLED', false),
    'content_security_policy' => env(
        'SECURITY_CONTENT_SECURITY_POLICY',
        "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
    ),
];
