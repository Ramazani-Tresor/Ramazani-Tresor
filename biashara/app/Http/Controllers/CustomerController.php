<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerSettlement;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\NumberingService;
use App\Services\SettlementService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->withSum(['sales as balance' => fn ($q) => $q->where('status', 'validated')], 'amount_due')
            ->withCount('sales')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")->orWhere('code', $t)))
            ->when($request->debt, fn ($q) => $q->whereHas('sales', fn ($s) => $s->where('status', 'validated')->where('amount_due', '>', 0)))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'totalDue' => Sale::query()->where('status', 'validated')->whereNotNull('customer_id')->sum('amount_due'),
        ]);
    }

    /** Recherche rapide (point de vente). */
    public function search(Request $request)
    {
        $t = (string) $request->q;

        return Customer::query()->where('is_active', true)
            ->when($t !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")->orWhere('code', $t)))
            ->orderBy('name')->limit(15)->get(['id', 'code', 'name', 'phone']);
    }

    public function show(Customer $customer)
    {
        return view('customers.show', [
            'customer' => $customer,
            'balance' => $customer->balanceDue(),
            'openSales' => $customer->sales()->where('status', 'validated')->where('amount_due', '>', 0)->orderBy('sold_at')->get(),
            'sales' => $customer->sales()->with('user')->latest('sold_at')->paginate(15),
            'settlements' => $customer->settlements()->with(['method', 'user'])->latest('paid_at')->limit(20)->get(),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
        ]);
    }

    /** Relevé de compte : ventes (débit), règlements et paiements (crédit), retours. */
    public function statement(Request $request, Customer $customer)
    {
        [$from, $to] = $this->period($request);
        $lines = collect();
        foreach ($customer->sales()->whereBetween('sold_at', [$from, $to])->get() as $s) {
            $lines->push(['date' => $s->sold_at, 'label' => "Vente {$s->number}".($s->isCancelled() ? ' (annulée)' : ''), 'debit' => (string) $s->total, 'credit' => '0']);
        }
        foreach (Payment::query()->where('customer_id', $customer->id)->whereBetween('paid_at', [$from, $to])->with(['method', 'sale'])->get() as $p) {
            $label = match ($p->kind) {
                'sale' => "Paiement à la vente {$p->sale?->number} ({$p->method->name})",
                'debt_settlement' => "Règlement de créance — {$p->sale?->number} ({$p->method->name})",
                'refund' => "Remboursement — {$p->sale?->number}",
            };
            $isRefund = $p->kind === 'refund';
            $lines->push(['date' => $p->paid_at, 'label' => $label, 'debit' => $isRefund ? (string) Money::of($p->amount)->abs() : '0', 'credit' => $isRefund ? '0' : (string) $p->amount]);
        }
        foreach ($customer->sales()->with(['returns' => fn ($q) => $q->whereBetween('returned_at', [$from, $to])])->get()->pluck('returns')->flatten() as $r) {
            $lines->push(['date' => $r->returned_at, 'label' => ($r->is_cancellation ? 'Annulation ' : 'Retour ').$r->number, 'debit' => '0', 'credit' => (string) $r->total_value]);
        }
        $lines = $lines->sortBy('date')->values();
        $running = Money::of(0);
        $lines = $lines->map(function ($l) use (&$running) {
            $running = $running->plus(Money::of($l['debit']))->minus(Money::of($l['credit']));

            return $l + ['balance' => Money::money($running)];
        });

        return view('customers.statement', [
            'customer' => $customer, 'lines' => $lines, 'from' => $from, 'to' => $to,
            'balance' => $customer->balanceDue(), 'shop' => Settings::all(),
        ]);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer(['is_active' => true])]);
    }

    public function store(Request $request, NumberingService $numbering)
    {
        $data = $this->validated($request);
        $customer = Customer::create($data + ['code' => $numbering->next('customer')]);
        AuditService::log('customer.create', $customer, "Client « {$customer->name} » créé");
        if ($request->expectsJson()) {
            return response()->json($customer->only(['id', 'code', 'name', 'phone']), 201);
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Client enregistré.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));
        AuditService::log('customer.update', $customer, "Client « {$customer->name} » modifié");

        return redirect()->route('customers.show', $customer)->with('success', 'Client modifié.');
    }

    public function settle(Request $request, Customer $customer, SettlementService $service)
    {
        $data = $request->validate([
            'client_uuid' => 'required|uuid',
            'amount' => 'required|numeric|gt:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
            'sale_id' => 'nullable|integer',
        ]);
        $settlement = $service->settle($customer, (string) $data['amount'], PaymentMethod::findOrFail($data['payment_method_id']), $request->user(),
            $data['client_uuid'], $data['reference'] ?? null, $data['notes'] ?? null, $data['sale_id'] ?? null);

        return redirect()->route('settlements.show', $settlement)->with('success', 'Règlement enregistré.');
    }

    public function settlementReceipt(Request $request, CustomerSettlement $settlement)
    {
        return view('receipts.settlement', [
            'settlement' => $settlement->load(['customer', 'method', 'user', 'allocations.sale']),
            'balance' => $settlement->customer->balanceDue(),
            'shop' => Settings::all(),
            'format' => in_array($request->format, ['a4', '80', '58'], true) ? $request->format : Settings::get('receipt_format', '80'),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        return $data;
    }
}
