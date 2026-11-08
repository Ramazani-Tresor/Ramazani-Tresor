<?php

namespace App\Http\Controllers;

use App\Jobs\ConfirmImportJob;
use App\Models\BankAccount;
use App\Models\ImportBatch;
use App\Models\Payment;
use App\Models\ReconciliationMatch;
use App\Models\StatementLine;
use App\Services\ReconciliationService;
use App\Services\StatementImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class BankController extends Controller
{
    public function __construct(private StatementImportService $imports, private ReconciliationService $recon) {}

    public function index()
    {
        return view('bank.index', [
            'accounts' => BankAccount::query()->with('bank')->where('is_active', true)->get(),
            'batches' => ImportBatch::query()->where('type', 'bank_statement')->with(['bankAccount.bank', 'creator'])->latest('id')->paginate(15),
            'stats' => [
                'unmatched' => StatementLine::query()->where('match_status', 'unmatched')->count(),
                'partial' => StatementLine::query()->where('match_status', 'partial')->count(),
                'review' => StatementLine::query()->where('review_status', 'pending')->count(),
                'unconfirmed' => Payment::query()->where('type', 'bank_deposit')->where('status', 'validated')->whereIn('bank_status', ['unconfirmed', 'partial'])->count(),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $d = $request->validate(['file' => 'required|file|max:20480', 'bank_account_id' => 'required|exists:bank_accounts,id']);
        $batch = $this->imports->upload($request->file('file'), BankAccount::findOrFail($d['bank_account_id']), $request->user());

        return redirect()->route('bank.batches.show', $batch)->with('info', 'Relevé déposé : vérifiez les colonnes et la période, puis prévisualisez.');
    }

    public function batch(ImportBatch $batch)
    {
        abort_unless($batch->type === 'bank_statement', 404);
        $analysis = $batch->status === 'previewed' ? $this->imports->analyze($batch) : null;

        return view('bank.batch', ['batch' => $batch->load('bankAccount.bank'), 'headers' => $this->imports->headers($batch), 'fields' => StatementImportService::FIELDS, 'analysis' => $analysis,
            'lines' => $batch->status === 'completed' ? $batch->lines()->orderBy('operation_date')->paginate(50) : null]);
    }

    public function source(ImportBatch $batch)
    {
        abort_unless($batch->type === 'bank_statement' && Storage::exists($batch->stored_path), 404);

        return Storage::download($batch->stored_path, $batch->number.'_'.$batch->original_name);
    }

    public function preview(Request $request, ImportBatch $batch)
    {
        $d = $request->validate(['period_start' => 'nullable|date', 'period_end' => 'nullable|date|after_or_equal:period_start']);
        $map = array_filter(array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, (array) $request->input('map', [])), fn ($v) => $v !== null);
        $this->imports->preview($batch, $map, $d['period_start'] ?? null, $d['period_end'] ?? null);

        return redirect()->route('bank.batches.show', $batch);
    }

    public function confirm(Request $request, ImportBatch $batch)
    {
        if ($batch->rows_total > 2000) {
            ConfirmImportJob::dispatch($batch->id, $request->user()->id);

            return back()->with('info', 'Relevé volumineux : traitement en arrière-plan.');
        }
        $done = $this->imports->confirm($batch, $request->user());

        return redirect()->route('bank.batches.show', $done)->with('success', "{$done->rows_created} ligne(s) importée(s) ; {$done->rows_skipped} déjà connue(s) (chevauchement, non recomptée(s)).");
    }

    public function autoMatch(Request $request, ImportBatch $batch)
    {
        $s = $this->recon->autoMatch($batch, $request->user());

        return back()->with('success', "Rapprochement automatique : {$s['matched']} correspondance(s) exacte(s) appliquée(s), {$s['ambiguous']} cas ambigu(s) à traiter manuellement, {$s['none']} sans correspondance.");
    }

    public function lines(Request $request)
    {
        $lines = StatementLine::query()->with('bankAccount.bank')
            ->when($request->input('status'), fn ($q, $v) => $q->where('match_status', $v))
            ->when($request->input('review'), fn ($q, $v) => $q->where('review_status', $v))
            ->when($request->integer('account'), fn ($q, $v) => $q->where('bank_account_id', $v))
            ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$t}%")->orWhere('description', 'like', "%{$t}%")))
            ->orderByDesc('operation_date')->paginate(40)->withQueryString();

        return view('bank.lines', ['lines' => $lines, 'accounts' => BankAccount::query()->with('bank')->get()]);
    }

    public function line(StatementLine $line)
    {
        return view('bank.line', ['line' => $line->load(['bankAccount.bank', 'batch', 'matches.payment.student', 'matches.creator']),
            'candidates' => in_array($line->match_status, ['unmatched', 'partial'], true) ? $this->recon->candidates($line) : collect(),
            'tolerance' => (int) setting('reconciliation_date_tolerance_days')]);
    }

    public function match(Request $request, StatementLine $line)
    {
        $d = $request->validate(['payment_id' => 'required|exists:payments,id', 'amount' => 'required|numeric|gt:0', 'note' => 'nullable|string|max:255']);
        $this->recon->match($line, Payment::findOrFail($d['payment_id']), (string) $d['amount'], $request->user(), $d['note'] ?? null);

        return back()->with('success', 'Rapprochement enregistré : le paiement est confirmé par la banque pour ce montant.');
    }

    public function unmatch(Request $request, ReconciliationMatch $match)
    {
        $this->recon->unmatch($match, $request->user(), (string) $request->validate(['reason' => 'required|string|max:255'])['reason']);

        return back()->with('success', 'Rapprochement annulé.');
    }

    public function review(Request $request, StatementLine $line)
    {
        $d = $request->validate(['decision' => 'required|in:accept,reject', 'operation_date' => 'nullable|date', 'value_date' => 'nullable|date', 'reference' => 'nullable|string|max:120', 'amount' => 'nullable|numeric|gt:0', 'note' => 'nullable|string|max:255']);
        $this->recon->review($line, $d['decision'] === 'accept', $request->user(), $d, $d['note'] ?? null);

        return back()->with('success', $d['decision'] === 'accept' ? 'Ligne vérifiée : elle peut être rapprochée.' : 'Extraction rejetée.');
    }

    public function ignore(Request $request, StatementLine $line)
    {
        $this->recon->ignore($line, $request->user(), (string) $request->validate(['reason' => 'required|string|max:255'])['reason']);

        return back()->with('success', 'Ligne écartée du rapprochement (motif conservé).');
    }

    public function report(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        return view('bank.report', ['r' => $this->recon->report($request->integer('account') ?: null, Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()),
            'from' => Carbon::parse($from), 'to' => Carbon::parse($to), 'accounts' => BankAccount::query()->with('bank')->get()]);
    }
}
