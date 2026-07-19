<?php

return [
    'current_term'    => env('INTERNTRACK_CURRENT_TERM', 'AY 2024-2025, Sem 2'),
    'default_password'=> env('INTERNTRACK_DEFAULT_PASSWORD', 'interntrack123'),
    'upload_max_mb'   => env('INTERNTRACK_UPLOAD_MAX_MB', 10),
    'misd_use_mock'   => env('MISD_USE_MOCK', true),
    'misd_api_base_url'=> env('MISD_API_BASE_URL', 'http://localhost:8000/api/v1/mock-misd'),
    'misd_api_key'    => env('MISD_API_KEY', ''),
    'misd_cache_ttl'  => env('MISD_CACHE_TTL', 3600),
];
