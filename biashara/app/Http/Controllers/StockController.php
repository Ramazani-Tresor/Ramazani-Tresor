<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()->with(['category', 'unit'])->where('type', 'product')
            ->search($request->q)
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->stock === 'low', fn ($q) => $q->lowStock())
            ->when($request->stock === 'out', fn ($q) => $q->where('stock_quantity', '<=', 0))
            ->when($request->stock === 'damaged', fn ($q) => $q->where('damaged_quantity', '>', 0))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('stock.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'showCost' => $request->user()->hasPermission('products.view_cost'),
        ]);
    }

    public function movements(Request $request)
    {
        [$from, $to] = $this->period($request);
        $movements = StockMovement::query()->with(['product', 'user'])
            ->whereBetween('occurred_at', [$from, $to])
            ->when($request->product_id, fn ($q, $v) => $q->where('product_id', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('occurred_at')->latest('id')->paginate(40)->withQueryString();

        return view('stock.movements', [
            'movements' => $movements, 'from' => $from, 'to' => $to,
            'products' => Product::query()->where('type', 'product')->orderBy('name')->get(['id', 'name', 'reference']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function exitForm()
    {
        return view('stock.exit', ['products' => Product::query()->active()->where('type', 'product')->with('unit')->orderBy('name')->get()]);
    }

    public function exitStore(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:'.implode(',', StockMovement::MANUAL_EXIT_TYPES),
            'quantity' => 'required|numeric|gt:0',
            'reason' => 'required|string|max:255',
        ]);
        $stock->manualExit(Product::findOrFail($data['product_id']), $data['type'], (string) $data['quantity'], $data['reason'], $request->user());

        return redirect()->route('stock.movements')->with('success', 'Sortie de stock enregistrée.');
    }
}
