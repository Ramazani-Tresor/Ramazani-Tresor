<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/** Numéros uniques séquentiels (ligne de séquence verrouillée pendant l'attribution). */
class NumberingService
{
    public const DEFAULTS = [
        'matricule' => ['Matricule du personnel (après le préfixe GRH_MATRICULE_PREFIX)', ''],
        'prime_payment' => ['Mouvement de prime (paiement, acompte, régularisation)', 'PRI'],
        'social_support' => ['Soutien social', 'SOC'],
        'candidate' => ['Dossier de candidature', 'CAN'],
        'inventory' => ['Inventaire du patrimoine', 'INV'],
        'asset' => ['Code patrimonial', 'PAT'],
        'report' => ['Édition officielle', 'EDT'],
    ];

    public function next(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $seq = DocumentSequence::query()->where('type', $type)->lockForUpdate()->first();
            if (! $seq) {
                [$label, $prefix] = self::DEFAULTS[$type] ?? [$type, strtoupper(substr($type, 0, 3))];
                DocumentSequence::query()->insertOrIgnore([
                    'type' => $type, 'label' => $label, 'prefix' => $prefix, 'next_number' => 1, 'padding' => 5,
                    'yearly_reset' => true, 'current_year' => (int) now()->year, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $seq = DocumentSequence::query()->where('type', $type)->lockForUpdate()->firstOrFail();
            }
            $year = (int) now()->year;
            if ($seq->yearly_reset && $seq->current_year !== $year) {
                $seq->next_number = 1;
                $seq->current_year = $year;
            }
            $n = $seq->next_number;
            $seq->next_number = $n + 1;
            $seq->save();

            return implode('-', array_filter([$seq->prefix, $seq->yearly_reset ? $year : null, str_pad((string) $n, $seq->padding, '0', STR_PAD_LEFT)]));
        });
    }
}
