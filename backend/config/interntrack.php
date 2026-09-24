<?php

return [
    // Single source of truth for the displayed academic term (dashboards, auth user.term).
    // Override in .env without a code deploy: INTERNTRACK_CURRENT_TERM="AY 2025-2026, Sem 2"
    'current_term'     => env('INTERNTRACK_CURRENT_TERM', 'AY 2025-2026, Sem 2'),

    // Legacy last-resort fallback only. Required internship hours come from
    // program_hte_requirements via ProgramRequirementService — never assume 500
    // in application code. Seeders/tests may still read this value.
    'target_hours'     => (int) env('INTERNTRACK_TARGET_HOURS', 500),

    'default_password' => env('INTERNTRACK_DEFAULT_PASSWORD', 'InternTrack123!'),

    // Global upload policy (per-file). Module MIME rules stay separate.
    // PHP upload_max_filesize / post_max_size must be >= these values.
    'upload_max_mb' => (int) env('INTERNTRACK_UPLOAD_MAX_MB', env('INTERNTRACK_MAX_UPLOAD_MB', 10)),
    'upload_max_request_mb' => (int) env('INTERNTRACK_UPLOAD_MAX_REQUEST_MB', 30),
    'upload_max_files' => (int) env('INTERNTRACK_UPLOAD_MAX_FILES', 5),

    'misd_use_mock'    => env('MISD_USE_MOCK', true),
    // Default-password first-login provision: on in local; elsewhere only if explicitly true.
    'allow_default_password_provision' => filter_var(
        env('MISD_ALLOW_DEFAULT_PASSWORD_PROVISION', env('APP_ENV') === 'local' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'misd_api_base_url'=> env('MISD_API_BASE_URL', 'http://localhost:8000/api/v1/mock-misd'),
    'misd_api_key'     => env('MISD_API_KEY', ''),
    'misd_cache_ttl'   => env('MISD_CACHE_TTL', 3600),

    // Industry Supervisor narrative feedback (journal_entries.supervisor_feedback is TEXT).
    'supervisor_feedback_min_length' => (int) env('INTERNTRACK_FEEDBACK_MIN_LENGTH', 5),
    'supervisor_feedback_max_length' => (int) env('INTERNTRACK_FEEDBACK_MAX_LENGTH', 1000),

    // Account lockout: consecutive failed sign-ins before the account is locked.
    'max_failed_logins' => (int) env('INTERNTRACK_MAX_FAILED_LOGINS', 3),
    // Shown in the lockout email; account unlocks are done by Admin/MISD.
    'account_support_contact' => env('INTERNTRACK_ACCOUNT_SUPPORT_CONTACT', 'the University MISD office or your InternTrack system administrator'),

    // Browser session cookie that carries the Sanctum token (HttpOnly, shared by all tabs).
    'auth_cookie' => env('INTERNTRACK_AUTH_COOKIE', 'interntrack_token'),
];
