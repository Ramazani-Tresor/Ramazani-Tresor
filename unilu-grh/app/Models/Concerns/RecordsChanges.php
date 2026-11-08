<?php

namespace App\Models\Concerns;

use App\Models\RecordChange;
use Illuminate\Support\Facades\Auth;

/**
 * Historise chaque modification, champ par champ (table record_changes).
 * Les champs listés dans $maskedHistory (coordonnées bancaires, cause de décès…) sont enregistrés comme « [modifié] ».
 */
trait RecordsChanges
{
    public static ?string $changeContext = null;

    public static function bootRecordsChanges(): void
    {
        static::created(function ($model) {
            RecordChange::create([
                'subject_type' => $model->getMorphClass(), 'subject_id' => $model->getKey(),
                'employee_id' => $model->historyEmployeeId(), 'field' => '*', 'old_value' => null,
                'new_value' => 'Création', 'user_id' => Auth::id(), 'context' => static::$changeContext, 'created_at' => now(),
            ]);
        });
        static::updated(function ($model) {
            $masked = property_exists($model, 'maskedHistory') ? $model->maskedHistory : [];
            foreach ($model->getChanges() as $field => $new) {
                if (in_array($field, ['updated_at', 'created_at', 'remember_token', 'password'], true)) {
                    continue;
                }
                $old = $model->getOriginal($field);
                $isMasked = in_array($field, $masked, true);
                RecordChange::create([
                    'subject_type' => $model->getMorphClass(), 'subject_id' => $model->getKey(),
                    'employee_id' => $model->historyEmployeeId(), 'field' => $field,
                    'old_value' => $isMasked ? ($old === null ? null : '[masqué]') : self::stringify($old),
                    'new_value' => $isMasked ? '[modifié]' : self::stringify($model->getAttribute($field)),
                    'user_id' => Auth::id(), 'context' => static::$changeContext, 'created_at' => now(),
                ]);
            }
        });
    }

    public function historyEmployeeId(): ?int
    {
        return $this->employee_id ?? null;
    }

    private static function stringify(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_bool($v)) {
            return $v ? 'oui' : 'non';
        }

        return is_scalar($v) ? mb_substr((string) $v, 0, 1000) : json_encode($v, JSON_UNESCAPED_UNICODE);
    }
}
