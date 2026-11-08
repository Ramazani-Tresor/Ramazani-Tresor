<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/** Numéros uniques séquentiels (ligne de séquence verrouillée pendant l'attribution). */
class NumberingService
{
    public const DEFAULTS = [
        'payment' => ['Paiement (bordereau)', 'PAY'],
        'charge' => ['Créance', 'CRE'],
        'adjustment' => ['Ajustement / exonération', 'AJU'],
        'credit_operation' => ['Opération sur excédent', 'EXC'],
        'contract' => ['Contrat de paiement', 'CTR'],
        'receipt' => ['Reçu', 'REC'],
        'statement' => ['Relevé financier', 'REL'],
        'attestation' => ['Attestation de paiement', 'ATT'],
        'situation' => ['Situation individuelle', 'SIT'],
        'refund' => ['Remboursement', 'RMB'],
        'compensation' => ['Compensation', 'CMP'],
        'carry_forward' => ['Report', 'RPT'],
        'import' => ['Lot d’import', 'IMP'],
    ];

    public function next(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $seq = DocumentSequence::query()->where('type', $type)->lockForUpdate()->first();
            if (! $seq) {
                [$label, $prefix] = self::DEFAULTS[$type] ?? [$type, strtoupper(substr($type, 0, 3))];
                DocumentSequence::query()->insertOrIgnore([
                    'type' => $type, 'label' => $label, 'prefix' => $prefix, 'next_number' => 1, 'padding' => 6,
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
