<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::query()->withCount('purchases')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function show(Supplier $supplier)
    {
        return view('suppliers.show', ['supplier' => $supplier, 'purchases' => $supplier->purchases()->latest('purchase_date')->paginate(15)]);
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));
        AuditService::log('supplier.create', $supplier, "Fournisseur « {$supplier->name} » créé");

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur enregistré.');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));
        AuditService::log('supplier.update', $supplier, "Fournisseur « {$supplier->name} » modifié");

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur modifié.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'contact_person' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
