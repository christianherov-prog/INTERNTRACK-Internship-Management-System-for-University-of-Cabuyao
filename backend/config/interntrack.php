<?php

return [
    // Single source of truth for the displayed academic term (dashboards, auth user.term).
    // Override in .env without a code deploy: INTERNTRACK_CURRENT_TERM="AY 2025-2026, Sem 2"
    'current_term'     => env('INTERNTRACK_CURRENT_TERM', 'AY 2025-2026, Sem 2'),

    // CCS OJT requirement (BSIT / BSCS): uniform 500 hours — not per-program.
    // Override: INTERNTRACK_TARGET_HOURS=500
    'target_hours'     => (int) env('INTERNTRACK_TARGET_HOURS', 500),

    'default_password' => env('INTERNTRACK_DEFAULT_PASSWORD', 'interntrack123'),
    'upload_max_mb'    => env('INTERNTRACK_UPLOAD_MAX_MB', 10),
    'misd_use_mock'    => env('MISD_USE_MOCK', true),
    // Default-password first-login provision: on in local; elsewhere only if explicitly true.
    'allow_default_password_provision' => filter_var(
        env('MISD_ALLOW_DEFAULT_PASSWORD_PROVISION', env('APP_ENV') === 'local' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'misd_api_base_url'=> env('MISD_API_BASE_URL', 'http://localhost:8000/api/v1/mock-misd'),
    'misd_api_key'     => env('MISD_API_KEY', ''),
    'misd_cache_ttl'   => env('MISD_CACHE_TTL', 3600),
];
