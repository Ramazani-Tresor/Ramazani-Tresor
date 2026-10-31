<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\SaleService;
use App\Services\SyncService;
use App\Support\Settings;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('pos.index', [
            'session' => $user->openCashSession(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'paymentMethods' => PaymentMethod::query()->active()->get(['id', 'name', 'kind', 'code']),
            'config' => [
                'canDiscount' => $user->hasPermission('sales.discount'),
                'canCredit' => $user->hasPermission('sales.credit'),
                'canCreateCustomer' => $user->hasPermission('customers.manage'),
                'maxDiscount' => Settings::get('max_discount_percent'),
                'offlineEnabled' => Settings::bool('offline_sales_enabled'),
                'offlineMax' => Settings::get('offline_max_sale_amount'),
                'currency' => Settings::get('currency_code'),
                'symbol' => Settings::get('currency_symbol'),
                'secondaryEnabled' => Settings::bool('secondary_currency_enabled'),
                'secondaryCode' => Settings::get('secondary_currency_code'),
                'secondarySymbol' => Settings::get('secondary_currency_symbol'),
                'exchangeRate' => Settings::get('exchange_rate'),
                'receiptFormat' => Settings::get('receipt_format'),
                'userId' => $user->id,
                'userName' => $user->name,
            ],
        ]);
    }

    /** Catalogue pour le cache hors ligne (IndexedDB). */
    public function catalog(Request $request)
    {
        $products = Product::query()->active()->with(['unit', 'tax', 'category'])->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'type' => $p->type,
                'barcode' => $p->barcode, 'category_id' => $p->category_id, 'category' => $p->category?->name,
                'unit' => $p->unit?->symbol, 'decimal' => (bool) $p->unit?->allows_decimal,
                'price' => (string) $p->sale_price,
                'tax_rate' => $p->tax && $p->tax->is_active ? (string) $p->tax->rate : '0',
                'stock' => $p->type === 'product' ? (string) $p->stock_quantity : null,
                'photo' => $p->photoUrl(),
            ]);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'products' => $products,
        ]);
    }

    /** Vente en ligne (idempotente via client_uuid). */
    public function store(Request $request, SaleService $sales, SyncService $sync)
    {
        $data = $this->validatedSale($request);
        [$sale, $duplicate] = $sales->create($data, $request->user());
        if ($request->filled('device_id')) {
            $sync->touchDevice($request->input('device_id'), $request->user());
        }

        return response()->json([
            'status' => 'synced',
            'duplicate' => $duplicate,
            'sale_id' => $sale->id,
            'sale_number' => $sale->number,
            'total' => (string) $sale->total,
            'change' => (string) $sale->change_given,
            'due' => (string) $sale->amount_due,
            'receipt_url' => route('sales.receipt', $sale),
        ], $duplicate ? 200 : 201);
    }

    public static function saleRules(): array
    {
        return [
            'client_uuid' => 'required|uuid',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|gt:0|max:1000000',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array|max:10',
            'payments.*.payment_method_id' => 'required|integer',
            'payments.*.amount' => 'nullable|numeric|min:0',
            'payments.*.currency' => 'nullable|string|size:3',
            'payments.*.currency_amount' => 'nullable|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'device_id' => 'nullable|string|max:64',
            'sold_at' => 'nullable|date',
        ];
    }

    private function validatedSale(Request $request): array
    {
        $data = $request->validate(self::saleRules());
        $data['origin'] = 'online';
        unset($data['sold_at']); // une vente en ligne est datée par le serveur

        return $data;
    }
}
