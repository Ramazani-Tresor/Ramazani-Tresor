<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\BankAccount;
use App\Models\ImportBatch;
use App\Models\StatementLine;
use App\Models\User;
use App\Support\Import\SpreadsheetReader;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Import des relevés bancaires (Excel, CSV, PDF texte).
 *  - Fichier source et lot conservés ; un fichier identique déjà importé est refusé.
 *  - Seuls les crédits (versements reçus) sont importés ; dates d'opération et de valeur conservées.
 *  - Empreinte par ligne (compte, date, montant, référence, libellé, rang d'occurrence) : les relevés qui se
 *    chevauchent ne sont jamais comptés deux fois.
 *  - Lignes incertaines (PDF, référence absente, date de valeur illisible…) → vérification humaine obligatoire
 *    avant tout rapprochement.
 *  - PDF : extraction de texte LOCALE (smalot/pdfparser). Les PDF scannés (images) nécessitent un OCR local
 *    non fourni : ils sont refusés avec un message explicite. Aucun envoi à un service externe.
 */
class StatementImportService
{
    public const FIELDS = [
        'operation_date' => ['Date d’opération', true],
        'value_date' => ['Date de valeur', false],
        'reference' => ['Référence', false],
        'description' => ['Libellé', false],
        'credit' => ['Montant crédit', true],
        'debit' => ['Montant débit', false],
        'currency' => ['Devise', false],
    ];

    private const ALIASES = [
        'operation_date' => ['date', 'date_operation', 'date_d_operation', 'date_op', 'operation_date'],
        'value_date' => ['date_valeur', 'date_de_valeur', 'valeur', 'value_date'],
        'reference' => ['reference', 'ref', 'numero', 'n_bordereau', 'bordereau', 'reference_operation'],
        'description' => ['libelle', 'description', 'motif', 'details', 'intitule'],
        'credit' => ['credit', 'montant_credit', 'montant', 'amount', 'entree', 'versement'],
        'debit' => ['debit', 'montant_debit', 'sortie'],
        'currency' => ['devise', 'currency', 'monnaie'],
    ];

    public function __construct(private NumberingService $numbering) {}

    public function upload(UploadedFile $file, BankAccount $account, User $user): ImportBatch
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv', 'txt', 'pdf'], true)) {
            throw new BusinessException('Format non pris en charge : .xlsx, .csv ou .pdf.');
        }
        $hash = hash_file('sha256', $file->getRealPath());
        if ($dup = ImportBatch::query()->where('type', 'bank_statement')->where('file_hash', $hash)->where('bank_account_id', $account->id)->where('status', 'completed')->first()) {
            throw new BusinessException("Ce fichier a déjà été importé (lot {$dup->number}).");
        }
        $path = $file->storeAs('imports/statements', date('Ymd_His').'_'.$hash.'.'.$ext);
        $rows = $this->rows(Storage::path($path), $ext);
        $batch = ImportBatch::create([
            'number' => $this->numbering->next('import'),
            'type' => 'bank_statement',
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_path' => $path,
            'file_hash' => $hash,
            'column_map' => $ext === 'pdf' ? ['operation_date' => 0, 'value_date' => 1, 'reference' => 2, 'description' => 3, 'credit' => 4] : $this->guessMap($rows[0] ?? []),
            'options' => ['format' => $ext],
            'status' => 'uploaded',
            'rows_total' => max(0, count($rows) - 1),
            'bank_account_id' => $account->id,
            'created_by' => $user->id,
        ]);
        AuditService::log('import.statement.upload', $batch, "Dépôt du relevé {$batch->original_name} ({$account->account_number})");

        return $batch;
    }

    public function headers(ImportBatch $batch): array
    {
        return $this->batchRows($batch)[0] ?? [];
    }

    /** @return array{rows: array, summary: array} */
    public function analyze(ImportBatch $batch, ?array $map = null): array
    {
        $map ??= $batch->column_map;
        foreach (self::FIELDS as $f => [$label, $required]) {
            if ($required && (! isset($map[$f]) || $map[$f] === '' || $map[$f] === null)) {
                throw new BusinessException("Associez la colonne obligatoire « {$label} ».");
            }
        }
        $account = BankAccount::query()->findOrFail($batch->bank_account_id);
        $isPdf = ($batch->options['format'] ?? '') === 'pdf';
        $rows = $this->batchRows($batch);
        array_shift($rows);
        $occurrences = [];
        $out = [];
        $summary = ['credits' => 0, 'debits_ignored' => 0, 'errors' => 0, 'duplicates' => 0, 'uncertain' => 0, 'total' => '0.00', 'min_date' => null, 'max_date' => null];
        foreach ($rows as $i => $raw) {
            $line = $i + 2;
            $get = fn ($f) => isset($map[$f]) && $map[$f] !== '' && $map[$f] !== null ? trim((string) ($raw[(int) $map[$f]] ?? '')) : '';
            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $errors = [];
            $uncertain = [];
            $opDate = SpreadsheetReader::parseDate($get('operation_date'));
            $valueRaw = $get('value_date');
            $valueDate = $valueRaw !== '' ? SpreadsheetReader::parseDate($valueRaw) : null;
            $credit = SpreadsheetReader::parseAmount($get('credit'));
            $debit = SpreadsheetReader::parseAmount($get('debit'));
            $currency = mb_strtoupper($get('currency')) ?: $account->currency;
            $reference = mb_substr($get('reference'), 0, 120);
            $description = mb_substr(strip_tags($get('description')), 0, 255);
            if (! $opDate) {
                $errors[] = 'Date d’opération illisible';
            }
            if ($credit === null || ! Money::isPositive($credit)) {
                if ($debit !== null && Money::isPositive($debit) || ($credit !== null && Money::isNegative($credit))) {
                    $summary['debits_ignored']++;

                    continue;
                }
                $errors[] = 'Montant crédit absent ou illisible';
            }
            if ($currency !== $account->currency) {
                $errors[] = "Devise {$currency} différente de celle du compte ({$account->currency})";
            }
            if ($valueRaw !== '' && ! $valueDate) {
                $uncertain[] = 'date de valeur illisible';
            }
            if ($reference === '') {
                $uncertain[] = 'référence absente';
            }
            if ($isPdf) {
                $uncertain[] = 'extraction PDF à confirmer';
            }
            $fingerprint = null;
            $duplicate = false;
            if (! $errors) {
                $base = implode('|', [$account->id, $opDate, Money::money($credit), PaymentService::normalizeReference($reference), mb_strtoupper(preg_replace('/\s+/', ' ', $description))]);
                $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;
                $fingerprint = hash('sha256', $base.'#'.$occurrences[$base]);
                $duplicate = StatementLine::query()->where('fingerprint', $fingerprint)->exists();
                $summary['credits']++;
                if ($duplicate) {
                    $summary['duplicates']++;
                } else {
                    $summary['total'] = Money::money(Money::add($summary['total'], $credit));
                }
                if ($uncertain) {
                    $summary['uncertain']++;
                }
                $summary['min_date'] = min($summary['min_date'] ?? $opDate, $opDate);
                $summary['max_date'] = max($summary['max_date'] ?? $opDate, $opDate);
            } else {
                $summary['errors']++;
            }
            $out[] = ['line' => $line, 'operation_date' => $opDate, 'value_date' => $valueDate, 'reference' => $reference, 'description' => $description,
                'amount' => $credit ? Money::money($credit) : null, 'currency' => $currency, 'errors' => $errors, 'uncertain' => $uncertain,
                'fingerprint' => $fingerprint, 'duplicate' => $duplicate];
        }

        return ['rows' => $out, 'summary' => $summary];
    }

    public function preview(ImportBatch $batch, array $map, ?string $periodStart, ?string $periodEnd): array
    {
        $a = $this->analyze($batch, $map);
        $batch->update([
            'column_map' => $map, 'status' => 'previewed', 'rows_valid' => $a['summary']['credits'], 'rows_error' => $a['summary']['errors'],
            'period_start' => $periodStart ?: $a['summary']['min_date'], 'period_end' => $periodEnd ?: $a['summary']['max_date'],
            'report' => array_map(fn ($r) => array_intersect_key($r, array_flip(['line', 'errors', 'uncertain', 'duplicate'])), $a['rows']),
        ]);

        return $a;
    }

    public function confirm(ImportBatch $batch, User $user): ImportBatch
    {
        if ($batch->status !== 'previewed') {
            throw new BusinessException('Prévisualisez le relevé avant de confirmer (ou ce lot a déjà été traité).');
        }
        if (! $batch->period_start || ! $batch->period_end) {
            throw new BusinessException('La période couverte par le relevé est obligatoire (couverture des rapprochements).');
        }
        $a = $this->analyze($batch, $batch->column_map);
        $stats = DB::transaction(function () use ($batch, $a) {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'previewed') {
                throw new BusinessException('Ce lot a déjà été traité.');
            }
            $stats = ['created' => 0, 'duplicates' => 0, 'errors' => 0];
            foreach ($a['rows'] as $r) {
                if ($r['errors']) {
                    $stats['errors']++;

                    continue;
                }
                if (StatementLine::query()->where('fingerprint', $r['fingerprint'])->exists()) {
                    $stats['duplicates']++;

                    continue;
                }
                StatementLine::create([
                    'import_batch_id' => $batch->id, 'bank_account_id' => $batch->bank_account_id, 'source_row' => $r['line'],
                    'operation_date' => $r['operation_date'], 'value_date' => $r['value_date'], 'reference' => $r['reference'] ?: null,
                    'reference_normalized' => PaymentService::normalizeReference($r['reference']), 'description' => $r['description'] ?: null,
                    'amount' => $r['amount'], 'currency' => $r['currency'], 'fingerprint' => $r['fingerprint'],
                    'reliability' => $r['uncertain'] ? 'uncertain' : 'reliable',
                    'reliability_note' => $r['uncertain'] ? implode(', ', $r['uncertain']) : null,
                    'review_status' => $r['uncertain'] ? 'pending' : 'not_required',
                ]);
                $stats['created']++;
            }
            $batch->update(['status' => 'completed', 'rows_created' => $stats['created'], 'rows_skipped' => $stats['duplicates'], 'rows_error' => $stats['errors']]);

            return $stats;
        });
        AuditService::log('import.statement.confirm', $batch, "Relevé {$batch->number} : {$stats['created']} ligne(s) importée(s), {$stats['duplicates']} déjà connue(s) (chevauchement), {$stats['errors']} en erreur");

        return $batch->fresh();
    }

    private function batchRows(ImportBatch $batch): array
    {
        return $this->rows(Storage::path($batch->stored_path), pathinfo($batch->stored_path, PATHINFO_EXTENSION));
    }

    private function rows(string $path, string $ext): array
    {
        if ($ext !== 'pdf') {
            return SpreadsheetReader::read($path, $ext);
        }
        try {
            $text = (new PdfParser)->parseFile($path)->getText();
        } catch (\Throwable $e) {
            throw new BusinessException('PDF illisible ou protégé : '.mb_substr($e->getMessage(), 0, 120));
        }
        if (mb_strlen(trim($text)) < 20) {
            throw new BusinessException('Ce PDF ne contient pas de texte exploitable (document scanné). L’OCR local n’est pas installé : demandez un relevé CSV/Excel à la banque.');
        }
        // Ligne type : 15/10/2026 [16/10/2026] REF123 Libellé ... 1 250,00
        $rows = [['date', 'date_valeur', 'reference', 'libelle', 'credit']];
        foreach (preg_split('/\R/u', $text) as $l) {
            $l = trim(preg_replace('/\s+/u', ' ', $l));
            if (preg_match('/^(\d{2}[\/.\-]\d{2}[\/.\-]\d{2,4})\s+(?:(\d{2}[\/.\-]\d{2}[\/.\-]\d{2,4})\s+)?(\S+)\s+(.*?)\s+(-?[\d .,]+\d)$/u', $l, $m)) {
                $rows[] = [$m[1], $m[2] ?? '', $m[3], $m[4], $m[5]];
            }
        }
        if (count($rows) === 1) {
            throw new BusinessException('Aucune opération reconnue dans ce PDF : sa structure n’est pas prise en charge. Utilisez un export CSV/Excel.');
        }

        return $rows;
    }

    private function guessMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $n = SpreadsheetReader::normalizeHeader((string) $h);
            foreach (self::ALIASES as $f => $aliases) {
                if (! isset($map[$f]) && in_array($n, $aliases, true)) {
                    $map[$f] = $idx;
                }
            }
        }

        return $map;
    }
}
