<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Paramètres institutionnels (table `settings`), aucune règle financière codée en dur. */
final class Settings
{
    private const CACHE_KEY = 'karo_settings';

    public const DEFAULTS = [
        'university_name' => 'Université (à paramétrer)',
        'university_acronym' => '',
        'university_address' => '',
        'university_phone' => '',
        'university_email' => '',
        'university_logo' => '',
        'signatory_1_name' => '',
        'signatory_1_title' => 'Administrateur du budget',
        'signatory_2_name' => '',
        'signatory_2_title' => 'Comptable',
        'timezone' => 'Africa/Lubumbashi',
        // Contrôles et circuits
        'segregation_of_duties' => '1',          // le validateur doit être différent de l'auteur de saisie
        'require_proof_for_submission' => '1',
        'require_check_before_validation' => '1',
        'balance_counts_unconfirmed' => '1',      // un règlement validé non encore confirmé par la banque réduit le solde
        'adjustment_requires_approval' => '1',
        'reconciliation_date_tolerance_days' => '3',
        'reconciliation_auto_exact' => '1',
        'statement_reference_required' => '0',
        // Documents
        'document_verification_enabled' => '1',
        'contract_default_conditions' => "L'étudiant s'engage à respecter l'échéancier ci-dessus. Tout retard peut entraîner l'application des mesures prévues par le règlement financier de l'université.",
        // Hors connexion
        'offline_enabled' => '1',
        'offline_ttl_hours' => '72',
        'offline_max_payload_kb' => '2048',
        // Portail étudiant
        'student_portal_enabled' => '0',
        // Démonstration
        'demo_mode' => '0',
    ];

    private static ?array $loaded = null;

    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')->all();
        });

        return self::$loaded = array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function bool(string $key): bool
    {
        return (bool) (int) self::get($key, '0');
    }

    public static function int(string $key): int
    {
        return (int) self::get($key, '0');
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$loaded = null;
    }
}
