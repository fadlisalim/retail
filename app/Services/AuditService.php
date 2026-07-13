<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Records privileged actions (moderation, status changes, setting edits). */
class AuditService
{
    public function log(string $action, ?Model $subject = null, array $old = [], array $new = [], ?User $actor = null): void
    {
        AuditLog::create([
            'user_id' => ($actor ?? auth()->user())?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
