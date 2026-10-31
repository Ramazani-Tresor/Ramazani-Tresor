<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $service) {}

    public function index(Request $request)
    {
        $purchases = Purchase::query()->with(['supplier', 'user'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('supplier_reference', 'like', "%{$t}%")))
            ->latest('purchase_date')->latest('id')->paginate(20)->withQueryString();

        return view('purchases.index', ['purchases' => $purchases, 'suppliers' => Supplier::query()->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('purchases.form', $this->formData(new Purchase(['purchase_date' => now()])));
    }

    public function store(Request $request)
    {
        [$header, $items] = $this->validated($request);
        $purchase = $this->service->saveDraft(null, $header, $items, $request->user());
        if ($request->input('action') === 'validate' && $request->user()->hasPermission('purchases.validate')) {
            $this->service->validate($purchase, $request->user());

            return redirect()->route('purchases.show', $purchase)->with('success', 'Bon d\'entrée validé : le stock a été mis à jour.');
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Bon d\'entrée enregistré en brouillon.');
    }

    public function show(Purchase $purchase)
    {
        return view('purchases.show', ['purchase' => $purchase->load(['items.product.unit', 'supplier', 'user', 'validator'])]);
    }

    public function print(Purchase $purchase)
    {
        return view('purchases.print', ['purchase' => $purchase->load(['items.product.unit', 'supplier', 'user', 'validator'])]);
    }

    public function edit(Purchase $purchase)
    {
        abort_unless($purchase->isDraft(), 403, 'Un bon validé ne peut plus être modifié.');

        return view('purchases.form', $this->formData($purchase->load('items.product')));
    }

    public function update(Request $request, Purchase $purchase)
    {
        [$header, $items] = $this->validated($request);
        $this->service->saveDraft($purchase, $header, $items, $request->user());
        if ($request->input('action') === 'validate' && $request->user()->hasPermission('purchases.validate')) {
            $this->service->validate($purchase, $request->user());

            return redirect()->route('purchases.show', $purchase)->with('success', 'Bon d\'entrée validé : le stock a été mis à jour.');
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Brouillon mis à jour.');
    }

    public function validatePurchase(Request $request, Purchase $purchase)
    {
        $this->service->validate($purchase, $request->user());

        return back()->with('success', 'Bon d\'entrée validé : le stock a été mis à jour.');
    }

    public function cancel(Request $request, Purchase $purchase)
    {
        $this->service->cancel($purchase, $request->user());

        return back()->with('success', 'Brouillon annulé.');
    }

    private function formData(Purchase $purchase): array
    {
        return [
            'purchase' => $purchase,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->active()->where('type', 'product')->orderBy('name')->get(['id', 'reference', 'name', 'cost_price', 'average_cost']),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_reference' => 'nullable|string|max:80',
            'purchase_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);
        $header = [
            'supplier_id' => $data['supplier_id'],
            'supplier_reference' => $data['supplier_reference'] ?? null,
            'purchase_date' => $data['purchase_date'],
            'notes' => $data['notes'] ?? null,
            'paid_from_cash' => $request->boolean('paid_from_cash'),
        ];

        return [$header, array_values($data['items'])];
    }
}
