<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\OrgUnit;
use App\Models\PrimeEntitlement;
use App\Models\PrimePayment;
use App\Models\PrimeType;
use App\Services\PrimeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PrimeController extends Controller
{
    private function visible(Request $request)
    {
        $u = $request->user();

        return fn ($x) => $u->hasGlobalPermission('primes.view') || $u->canOnEmployee('primes.view', $x->employee);
    }

    public function index(Request $request)
    {
        $q = PrimeEntitlement::query()->with(['employee', 'primeType', 'adjustments', 'payments'])->orderByDesc('period_start')->orderBy('employee_id');
        if ($request->filled('type')) {
            $q->where('prime_type_id', $request->integer('type'));
        }
        if ($request->filled('period')) {
            $q->where('period_label', $request->input('period'));
        }
        if ($request->filled('q')) {
            $q->whereHas('employee', fn ($e) => $e->search($request->input('q')));
        }
        if ($request->filled('unit')) {
            $q->whereHas('employee', fn ($e) => $e->inUnit($request->integer('unit')));
        }
        if ($request->input('balance') === 'due') {
            $q->where('status', 'due');
        }
        $u = $request->user();
        if (! $u->hasGlobalPermission('primes.view')) {
            $q->whereHas('employee', fn ($e) => $u->scopeEmployees($e, 'primes.view'));
        }
        $items = $q->paginate(40)->withQueryString();
        if ($request->input('balance') === 'due') {
            $items->setCollection($items->getCollection()->filter(fn ($e) => Money::isPositive($e->balance())));
        }

        return view('primes.index', [
            'items' => $items, 'types' => PrimeType::query()->orderBy('name')->get(),
            'periods' => PrimeEntitlement::query()->select('period_label')->distinct()->orderByDesc('period_label')->limit(36)->pluck('period_label'),
            'units' => OrgUnit::query()->orderBy('name')->get(),
        ]);
    }

    public function generateForm()
    {
        return view('primes.generate', ['types' => PrimeType::query()->where('is_active', true)->with(['rates' => fn ($q) => $q->where('is_active', true)])->get(), 'units' => OrgUnit::query()->orderBy('name')->get(), 'years' => AcademicYear::query()->orderByDesc('starts_on')->get()]);
    }

    public function generate(Request $request, PrimeService $svc)
    {
        $data = $request->validate([
            'prime_type_id' => 'required|exists:prime_types,id', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start',
            'period_label' => 'required|string|max:40', 'staff_family' => 'nullable|in:academic,scientific,administrative', 'org_unit_id' => 'nullable|exists:org_units,id',
        ]);
        $r = $svc->generate(PrimeType::query()->findOrFail($data['prime_type_id']), Carbon::parse($data['period_start']), Carbon::parse($data['period_end']), $data['period_label'], $request->user(), $data);

        return redirect()->route('primes.index', ['period' => $data['period_label']])->with('success', "Génération terminée : {$r['created']} droit(s) créé(s), {$r['existing']} déjà existant(s) (aucun doublon), {$r['no_rate']} agent(s) sans taux applicable.");
    }

    public function show(Request $request, PrimeEntitlement $entitlement)
    {
        abort_unless(($this->visible($request))($entitlement), 403);
        $entitlement->load(['employee.activeAssignment.orgUnit', 'primeType', 'rate', 'adjustments.author', 'payments.author', 'payments.validator']);

        return view('primes.show', ['e' => $entitlement]);
    }

    public function record(Request $request, PrimeEntitlement $entitlement, PrimeService $svc)
    {
        abort_unless(($this->visible($request))($entitlement), 403);
        $data = $request->validate([
            'kind' => 'required|in:advance,payment,regularization', 'amount' => 'nullable|numeric|min:0.01|max:9999999999',
            'payment_date' => 'required|date|before_or_equal:today', 'mode' => 'required|in:bank_transfer,cash,mobile_money,check,offset',
            'reference' => 'nullable|string|max:80', 'beneficiary' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:500',
        ]);
        $p = $svc->record($entitlement, $data['kind'], $data['amount'] ?? null, $data, $request->user());

        return back()->with('success', "Mouvement {$p->number} de ".money($p->amount, $p->currency).' saisi : il doit être validé par une autre personne.');
    }

    public function adjust(Request $request, PrimeEntitlement $entitlement, PrimeService $svc)
    {
        abort_unless(($this->visible($request))($entitlement), 403);
        $data = $request->validate(['amount' => 'required|numeric|not_in:0', 'reason' => 'required|string|max:500']);
        $svc->adjust($entitlement, (string) $data['amount'], $data['reason'], $request->user());

        return back()->with('success', 'Ajustement enregistré (écriture traçable, le montant initial reste visible).');
    }

    public function payments(Request $request)
    {
        $u = $request->user();
        $visible = $this->visible($request);
        $base = PrimePayment::query()->with(['employee', 'entitlement.primeType', 'author', 'validator']);

        return view('primes.payments', [
            'pending' => (clone $base)->where('status', 'pending')->latest()->get()->filter($visible),
            'recent' => (clone $base)->whereIn('status', ['validated', 'rejected', 'reversed'])->latest('updated_at')->limit(50)->get()->filter($visible),
            'advances' => PrimeEntitlement::query()->with(['employee', 'primeType', 'adjustments', 'payments'])->whereHas('payments', fn ($p) => $p->where('kind', 'advance')->where('status', 'validated'))->get()
                ->filter($visible)->filter(fn ($e) => Money::isPositive($e->balance())),
        ]);
    }

    public function validatePayment(Request $request, PrimePayment $payment, PrimeService $svc)
    {
        $svc->validate($payment, $request->user());

        return back()->with('success', "Mouvement {$payment->number} validé.");
    }

    public function rejectPayment(Request $request, PrimePayment $payment, PrimeService $svc)
    {
        $svc->reject($payment, $request->validate(['reason' => 'required|string|max:500'])['reason'], $request->user());

        return back()->with('success', 'Mouvement rejeté.');
    }

    public function reversePayment(Request $request, PrimePayment $payment, PrimeService $svc)
    {
        $rev = $svc->reverse($payment, $request->validate(['reason' => 'required|string|max:500'])['reason'], $request->user());

        return back()->with('success', "Contrepassation {$rev->number} enregistrée ; le mouvement d’origine reste visible.");
    }
}
