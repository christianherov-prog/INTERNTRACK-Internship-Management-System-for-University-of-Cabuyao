<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Log an auditable action.
     *
     * @param  User|int  $user        The acting user or their ID
     * @param  string    $action      Verb describing the action (e.g. 'approved_document')
     * @param  Model     $model       The Eloquent model being acted upon
     * @param  array     $old         The previous state (snapshot of changed fields)
     * @param  array     $new         The new state (snapshot of changed fields)
     */
    public static function log(User|int $user, string $action, Model $model, array $old = [], array $new = []): void
    {
        try {
            $userId = $user instanceof User ? $user->id : $user;

            AuditLog::create([
                'user_id'    => $userId,
                'action'     => $action,
                'model_type' => get_class($model),
                'model_id'   => $model->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the main request
            \Illuminate\Support\Facades\Log::warning('AuditService failed: ' . $e->getMessage());
        }
    }
}
