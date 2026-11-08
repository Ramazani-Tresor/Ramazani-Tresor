<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Paramètres institutionnels (table `settings`), aucune règle financière codée en dur. */
final class Settings
{
    private const CACHE_KEY = 'grh_settings';

    public const DEFAULTS = [
        'university_name' => 'Université de Likasi',
        'university_acronym' => 'UNILU',
        'university_motto' => '',
        'university_address' => 'Likasi, Haut-Katanga, République démocratique du Congo',
        'university_phone' => '',
        'university_email' => '',
        'country_header' => 'RÉPUBLIQUE DÉMOCRATIQUE DU CONGO',
        'ministry_header' => 'Ministère de l’Enseignement Supérieur et Universitaire',
        'signatory_1_title' => 'Le Secrétaire général administratif',
        'signatory_1_name' => '',
        'signatory_2_title' => 'Le Recteur',
        'signatory_2_name' => '',
        'timezone' => 'Africa/Lubumbashi',
        'default_currency' => 'USD',
        // Contrôles
        'segregation_of_duties' => '1',          // la validation d'une décision ou d'un paiement exige une autre personne que l'auteur
        'multiple_primary_assignments' => '0',   // règle 2 : une seule affectation principale active, sauf configuration contraire
        'require_decision_document' => '0',      // pièce numérisée obligatoire pour valider une décision
        'self_service_enabled' => '1',           // espace personnel des agents
        'max_upload_kb' => '10240',
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
