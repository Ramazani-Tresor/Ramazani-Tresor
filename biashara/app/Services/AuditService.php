<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function log(string $action, ?Model $subject = null, ?string $description = null, ?array $old = null, ?array $new = null, ?int $userId = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $old ? self::clean($old) : null,
            'new_values' => $new ? self::clean($new) : null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    private static function clean(array $values): array
    {
        unset($values['password'], $values['remember_token'], $values['updated_at'], $values['created_at']);

        return $values;
    }
}
