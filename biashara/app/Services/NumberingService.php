<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Attribution de numéros de documents uniques et séquentiels (ex. VTE-2026-000123).
 * La ligne de séquence est verrouillée (SELECT ... FOR UPDATE) : deux transactions
 * concurrentes ne peuvent pas obtenir le même numéro.
 */
class NumberingService
{
    public const DEFAULTS = [
        'sale' => ['Vente', 'VTE'],
        'purchase' => ["Bon d'entrée", 'ENT'],
        'sale_return' => ['Retour client', 'RET'],
        'expense' => ['Dépense', 'DEP'],
        'inventory' => ['Inventaire', 'INV'],
        'cash_session' => ['Session de caisse', 'CAI'],
        'settlement' => ['Règlement client', 'REG'],
        'customer' => ['Client', 'CLI'],
    ];

    public function next(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $seq = DocumentSequence::query()->where('type', $type)->lockForUpdate()->first();
            if (! $seq) {
                [$label, $prefix] = self::DEFAULTS[$type] ?? [$type, strtoupper(substr($type, 0, 3))];
                DocumentSequence::query()->insertOrIgnore([
                    'type' => $type, 'label' => $label, 'prefix' => $prefix, 'next_number' => 1,
                    'padding' => 6, 'yearly_reset' => true, 'current_year' => (int) now()->year,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $seq = DocumentSequence::query()->where('type', $type)->lockForUpdate()->firstOrFail();
            }

            $year = (int) now()->year;
            if ($seq->yearly_reset && $seq->current_year !== $year) {
                $seq->next_number = 1;
                $seq->current_year = $year;
            }

            $number = $seq->next_number;
            $seq->next_number = $number + 1;
            $seq->save();

            $parts = [$seq->prefix];
            if ($seq->yearly_reset) {
                $parts[] = $year;
            }
            $parts[] = str_pad((string) $number, $seq->padding, '0', STR_PAD_LEFT);

            return implode('-', $parts);
        });
    }
}
