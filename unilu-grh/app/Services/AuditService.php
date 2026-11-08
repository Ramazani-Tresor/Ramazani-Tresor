<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Journal d'audit chaîné : chaque entrée contient l'empreinte SHA-256 de la précédente.
 * Toute modification ou suppression d'une entrée rompt la chaîne (commande `grh:audit-verify`).
 * Les valeurs sensibles (mots de passe, secrets) ne sont jamais journalisées.
 */
class AuditService
{
    private const REDACTED = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token'];

    public static function log(string $action, ?Model $subject = null, ?string $description = null, ?array $old = null, ?array $new = null, ?string $reason = null, ?int $userId = null): AuditLog
    {
        return DB::transaction(function () use ($action, $subject, $description, $old, $new, $reason, $userId) {
            $previous = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();
            $request = request();
            $data = [
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'reason' => $reason,
                'old_values' => $old ? self::clean($old) : null,
                'new_values' => $new ? self::clean($new) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
                'device_id' => mb_substr((string) $request?->header('X-Device-Id'), 0, 64) ?: null,
                'previous_hash' => $previous?->hash,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ];
            $data['hash'] = self::hash($data);

            return AuditLog::create($data);
        });
    }

    public static function hash(array $data): string
    {
        $fields = ['user_id', 'action', 'auditable_type', 'auditable_id', 'description', 'reason', 'old_values', 'new_values', 'ip_address', 'device_id', 'previous_hash', 'created_at'];
        $payload = [];
        foreach ($fields as $f) {
            $v = $data[$f] ?? null;
            if ($v instanceof \DateTimeInterface) {
                $v = $v->format('Y-m-d H:i:s');
            }
            $payload[$f] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : ($v === null ? null : (string) $v);
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /** @return array{ok: bool, checked: int, broken_at: ?int} */
    public static function verifyChain(): array
    {
        $previous = null;
        $checked = 0;
        foreach (AuditLog::query()->orderBy('id')->cursor() as $log) {
            $data = $log->getAttributes();
            $data['old_values'] = $log->old_values;
            $data['new_values'] = $log->new_values;
            $data['created_at'] = $log->created_at?->format('Y-m-d H:i:s');
            if ($log->previous_hash !== $previous || self::hash($data) !== $log->hash) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $log->id];
            }
            $previous = $log->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null];
    }

    private static function clean(array $values): array
    {
        foreach ($values as $k => $v) {
            if (in_array($k, self::REDACTED, true)) {
                $values[$k] = '[masqué]';
            }
        }
        unset($values['updated_at'], $values['created_at']);

        return $values;
    }
}
