<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use App\Services\InventoryService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $service) {}

    public function index()
    {
        return view('inventories.index', [
            'inventories' => Inventory::query()->with(['category', 'opener', 'validator'])->withCount('items')->latest('id')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('inventories.create', ['categories' => Category::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['category_id' => 'nullable|exists:categories,id', 'notes' => 'nullable|string|max:1000']);
        $inventory = $this->service->open($request->user(), $data['category_id'] ?? null, $data['notes'] ?? null);

        return redirect()->route('inventories.show', $inventory)->with('success', 'Inventaire ouvert. Saisissez les quantités comptées rayon par rayon.');
    }

    public function show(Request $request, Inventory $inventory)
    {
        $items = $inventory->items()->with(['product.unit', 'counter'])
            ->when($request->q, fn ($q, $t) => $q->whereHas('product', fn ($p) => $p->search($t)))
            ->when($request->filter === 'pending', fn ($q) => $q->whereNull('counted_quantity'))
            ->when($request->filter === 'gaps', fn ($q) => $q->where('difference', '!=', 0))
            ->join('products', 'products.id', '=', 'inventory_items.product_id')->orderBy('products.name')
            ->select('inventory_items.*')->paginate(50)->withQueryString();

        $all = $inventory->items()->get();

        return view('inventories.show', [
            'inventory' => $inventory->load(['category', 'opener', 'validator']),
            'items' => $items,
            'stats' => [
                'total' => $all->count(),
                'counted' => $all->whereNotNull('counted_quantity')->count(),
                'gaps' => $all->filter(fn ($i) => $i->difference !== null && ! Money::isZero($i->difference))->count(),
                'value' => Money::money(Money::add(...$all->map(fn ($i) => Money::mul($i->difference ?? 0, $i->unit_cost))->all())),
            ],
            'showCost' => $request->user()->hasPermission('products.view_cost'),
        ]);
    }

    public function count(Request $request, Inventory $inventory)
    {
        $data = $request->validate([
            'counts' => 'required|array',
            'counts.*.counted_quantity' => 'nullable|numeric|min:0',
            'counts.*.justification' => 'nullable|string|max:255',
        ]);
        $this->service->recordCounts($inventory, $data['counts'], $request->user());

        return back()->with('success', 'Comptages enregistrés.');
    }

    public function validateInventory(Request $request, Inventory $inventory)
    {
        $this->service->validate($inventory, $request->user());

        return back()->with('success', 'Inventaire validé : les ajustements de stock ont été générés.');
    }

    public function cancel(Request $request, Inventory $inventory)
    {
        $this->service->cancel($inventory, $request->user());

        return back()->with('success', 'Inventaire annulé.');
    }

    public function export(Inventory $inventory)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'inv_');
        @unlink($tmp);
        $path = $tmp.'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([Settings::get('shop_name').' — Inventaire '.$inventory->number]));
        $writer->addRow(Row::fromValues(['Référence', 'Article', 'Théorique (au relevé)', 'Compté', 'Écart', 'Coût unitaire', 'Valeur écart', 'Justification', 'Compté par', 'Date']));
        foreach ($inventory->items()->with(['product', 'counter'])->get() as $i) {
            $writer->addRow(Row::fromValues([
                $i->product->reference, $i->product->name,
                $i->theoretical_quantity !== null ? (float) $i->theoretical_quantity : '',
                $i->counted_quantity !== null ? (float) $i->counted_quantity : '',
                $i->difference !== null ? (float) $i->difference : '',
                (float) $i->unit_cost,
                $i->difference !== null ? (float) Money::money(Money::mul($i->difference, $i->unit_cost)) : '',
                (string) $i->justification, (string) $i->counter?->name, (string) $i->counted_at?->format('d/m/Y H:i'),
            ]));
        }
        $writer->close();

        return response()->download($path, "inventaire-{$inventory->number}.xlsx")->deleteFileAfterSend();
    }
}
