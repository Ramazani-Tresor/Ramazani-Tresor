<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Student;
use App\Services\BalanceService;
use App\Services\PaymentService;
use App\Support\Filters;
use App\Support\Labels;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** Parcours : année → faculté → promotion → étudiants → étudiant → situation → bordereau → répartition → justificatif → soumission. */
    public function entry(Request $request, BalanceService $balances)
    {
        $filters = Filters::from($request, 'payments.create');
        $year = $filters->year;
        $students = collect();
        if ($year && ($filters->get('promotion') || $filters->get('faculty') || $filters->get('q'))) {
            $students = Student::query()->search($filters->get('q'))
                ->whereHas('enrollments', function ($e) use ($year, $filters) {
                    $e->where('academic_year_id', $year->id)->where('state', 'active')
                        ->when($filters->get('promotion'), fn ($q, $v) => $q->where('promotion_id', $v))
                        ->when($filters->get('faculty'), fn ($q, $v) => $q->whereHas('promotion.program.department', fn ($d) => $d->where('faculty_id', $v)))
                        ->when($filters->facultyScope !== null, fn ($q) => $q->whereHas('promotion.program.department', fn ($d) => $d->whereIn('faculty_id', $filters->facultyScope ?: [0])));
                })->with(['enrollments' => fn ($q) => $q->where('academic_year_id', $year->id)->with('promotion')])
                ->orderBy('last_name')->limit(60)->get();
        }
        $student = null;
        $situation = null;
        $open = collect();
        if ($request->filled('student') && $year) {
            $student = Student::query()->findOrFail($request->integer('student'));
            $this->authorizeStudent($student, 'payments.create');
            $situation = $balances->situation($student, $year->id);
            $open = collect($situation['lines'])->filter(fn ($l) => Money::isPositive($l['balance']))->values();
        }

        return view('payments.entry', [
            'filters' => $filters, 'year' => $year, 'students' => $students, 'student' => $student, 'situation' => $situation, 'open' => $open,
            'accounts' => BankAccount::query()->with(['bank', 'feeCategories'])->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'client_uuid' => 'required|uuid', 'academic_year_id' => 'required|exists:academic_years,id', 'student_id' => 'required|exists:students,id',
            'bank_account_id' => 'required|exists:bank_accounts,id', 'bank_reference' => 'required|string|max:80', 'deposit_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|gt:0|max:9999999999999', 'depositor_name' => 'nullable|string|max:150',
            'allocations' => 'nullable|array', 'allocations.*' => 'nullable|numeric|min:0', 'proof' => 'nullable|file|max:5120',
        ]);
        $account = BankAccount::query()->findOrFail($d['bank_account_id']);
        $proof = $request->hasFile('proof') ? $this->storePrivateFile($request->file('proof'), 'proofs') : null;
        $allocs = collect($d['allocations'] ?? [])->map(fn ($v, $chargeId) => ['charge_id' => (int) $chargeId, 'amount' => (string) $v])->filter(fn ($a) => Money::isPositive($a['amount'] ?: 0))->values()->all();
        [$payment, $dup] = $this->payments->submit($d + ['currency' => $account->currency], $allocs, $proof, $request->user());

        return redirect()->route('payments.show', $payment)->with($dup ? 'info' : 'success', $dup ? 'Ce bordereau avait déjà été enregistré (aucun doublon créé).' : "Bordereau {$payment->number} soumis au contrôle.".($payment->anomalies ? ' Anomalies signalées : à examiner par le contrôleur.' : ''));
    }

    public function control(Request $request)
    {
        $filters = Filters::from($request, $request->user()->hasPermission('payments.validate') ? 'payments.validate' : 'payments.check');
        $items = $this->scoped($filters)->whereIn('status', ['submitted', 'checked'])
            ->when($filters->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw('CASE WHEN anomalies IS NULL THEN 1 ELSE 0 END')->oldest('id')->paginate(30)->withQueryString();

        return view('payments.control', compact('items', 'filters'));
    }

    public function index(Request $request)
    {
        $filters = Filters::from($request, 'payments.view');
        $q = $this->scoped($filters)
            ->when($filters->get('status'), fn ($w, $v) => in_array($v, array_keys(Labels::STATUSES['bank']), true) ? $w->where('bank_status', $v) : $w->where('status', $v))
            ->when($filters->get('account'), fn ($w, $v) => $w->where('bank_account_id', $v))
            ->when($filters->get('currency'), fn ($w, $v) => $w->where('currency', $v))
            ->when($filters->get('from'), fn ($w) => $w->whereBetween('deposit_date', [$filters->get('from'), $filters->get('to') ?? now()->toDateString()]))
            ->when($filters->get('q'), fn ($w, $t) => $w->where(fn ($x) => $x->where('number', 'like', "%{$t}%")->orWhere('bank_reference', 'like', "%{$t}%")
                ->orWhereHas('student', fn ($s) => $s->search($t))));

        return view('payments.index', ['items' => $q->latest('id')->paginate(30)->withQueryString(), 'filters' => $filters]);
    }

    public function show(Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'payments.view');
        $payment->load(['student', 'bankAccount.bank', 'allocations.charge.category', 'events.user', 'matches.line', 'creditOperations', 'creator', 'checker', 'validator', 'originPayment', 'academicYear']);
        $openCharges = Charge::query()->with('category')->where('student_id', $payment->student_id)->where('academic_year_id', $payment->academic_year_id)->where('status', 'active')->get()
            ->filter(fn ($c) => Money::isPositive($c->balance()) || $payment->allocations->contains('charge_id', $c->id));

        return view('payments.show', ['payment' => $payment, 'openCharges' => $openCharges,
            'liveAnomalies' => in_array($payment->status, ['submitted', 'checked'], true) && $payment->type === 'bank_deposit'
                ? $this->payments->detectAnomalies($payment->student, $payment->bankAccount, $payment->bank_reference_normalized, (string) $payment->amount, $payment->deposit_date->toDateString(), $payment->proof_path ? ['p' => 1] : null, $payment->id) : []]);
    }

    public function proof(Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'payments.view');
        abort_unless($payment->proof_path && Storage::exists($payment->proof_path), 404);

        return Storage::response($payment->proof_path, $payment->number.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION), ['Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'", 'X-Content-Type-Options' => 'nosniff']);
    }

    public function updateAllocations(Request $request, Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'payments.view');
        $d = $request->validate(['allocations' => 'nullable|array', 'allocations.*' => 'nullable|numeric|min:0']);
        $allocs = collect($d['allocations'] ?? [])->map(fn ($v, $id) => ['charge_id' => (int) $id, 'amount' => (string) $v])->filter(fn ($a) => Money::isPositive($a['amount'] ?: 0))->values()->all();
        $this->payments->updateAllocations($payment, $allocs, $request->user());

        return back()->with('success', 'Répartition mise à jour.');
    }

    public function check(Request $request, Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'payments.check');
        $this->payments->check($payment, $request->user(), $request->input('note'));

        return back()->with('success', 'Justificatif contrôlé : le paiement peut être validé par une personne habilitée.');
    }

    public function validatePayment(Request $request, Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'payments.validate');
        $this->payments->validate($payment, $request->user(), $request->boolean('acknowledge'), $request->input('note'));

        return back()->with('success', 'Paiement validé : les affectations sont effectives. La confirmation bancaire interviendra au rapprochement.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'payments.view');
        $this->payments->reject($payment, $request->user(), (string) $request->validate(['reason' => 'required|string|max:255'])['reason']);

        return back()->with('success', 'Paiement rejeté.');
    }

    public function reverse(Request $request, Payment $payment)
    {
        $this->authorizeStudent($payment->student, 'payments.reverse');
        $this->payments->reverse($payment, $request->user(), (string) $request->validate(['reason' => 'required|string|max:255'])['reason']);

        return back()->with('success', 'Paiement contrepassé : ses affectations sont annulées, l’historique est conservé.');
    }

    private function scoped(Filters $filters)
    {
        return Payment::query()->with(['student', 'bankAccount.bank', 'creator'])
            ->when($filters->get('year'), fn ($q, $v) => $q->where('academic_year_id', $v))
            ->whereHas('student', fn ($s) => $s->inFaculties($filters->facultyScope))
            ->when($filters->get('faculty'), fn ($q, $v) => $q->whereHas('student.enrollments', fn ($e) => $e->whereColumn('enrollments.academic_year_id', 'payments.academic_year_id')->whereHas('promotion.program.department', fn ($d) => $d->where('faculty_id', $v))))
            ->when($filters->get('promotion'), fn ($q, $v) => $q->whereHas('student.enrollments', fn ($e) => $e->whereColumn('enrollments.academic_year_id', 'payments.academic_year_id')->where('promotion_id', $v)));
    }
}
