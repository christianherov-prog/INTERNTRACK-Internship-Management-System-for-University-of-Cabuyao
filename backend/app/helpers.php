<?php

if (!function_exists('audit_log')) {
    /**
     * Write an audit log entry.
     */
    function audit_log(?int $userId, string $action, array $details = []): void
    {
        try {
            \App\Models\AuditLog::create([
                'user_id'    => $userId,
                'action'     => $action,
                'new_values' => $details,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("audit_log failed: " . $e->getMessage());
        }
    }
}
