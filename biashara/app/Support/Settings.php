<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Accès centralisé aux paramètres du commerce (table `settings`), avec cache.
 */
final class Settings
{
    private const CACHE_KEY = 'app_settings';

    /** Valeurs par défaut utilisées tant qu'un paramètre n'est pas enregistré. */
    public const DEFAULTS = [
        'shop_name' => 'Mon Commerce',
        'shop_address' => '',
        'shop_phone' => '',
        'shop_email' => '',
        'shop_tax_id' => '',
        'shop_logo' => '',
        'currency_code' => 'CDF',
        'currency_symbol' => 'FC',
        'secondary_currency_enabled' => '0',
        'secondary_currency_code' => 'USD',
        'secondary_currency_symbol' => '$',
        'exchange_rate' => '2800',
        'timezone' => 'Africa/Lubumbashi',
        'receipt_format' => '80',
        'receipt_footer' => 'Merci pour votre achat et à bientôt !',
        'allow_negative_stock' => '0',
        'default_alert_threshold' => '5',
        'max_discount_percent' => '20',
        'offline_sales_enabled' => '1',
        'offline_max_sale_amount' => '0',
        'backup_mysqldump_path' => 'C:\\xampp\\mysql\\bin\\mysqldump.exe',
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
