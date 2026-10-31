<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\ProductService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    public function index(Request $request)
    {
        $products = Product::query()->with(['category', 'unit'])
            ->search($request->q)
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->stock === 'low', fn ($q) => $q->lowStock())
            ->when($request->stock === 'out', fn ($q) => $q->where('type', 'product')->where('stock_quantity', '<=', 0))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'showCost' => $request->user()->hasPermission('products.view_cost'),
        ]);
    }

    public function show(Request $request, Product $product)
    {
        return view('products.show', [
            'product' => $product->load(['category', 'unit', 'tax']),
            'movements' => $request->user()->hasPermission('stock.view')
                ? $product->movements()->with('user')->latest('occurred_at')->latest('id')->paginate(20)
                : null,
            'showCost' => $request->user()->hasPermission('products.view_cost'),
        ]);
    }

    public function create()
    {
        return view('products.form', $this->formData(new Product([
            'type' => 'product', 'is_active' => true, 'alert_threshold' => Settings::get('default_alert_threshold', '5'),
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $request->validate(['initial_stock' => 'nullable|numeric|min:0|max:999999999']);
        $product = $this->service->create($data, $request->input('initial_stock'), $request->user());

        return redirect()->route('products.show', $product)->with('success', 'Article créé.');
    }

    public function edit(Product $product)
    {
        return view('products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product)
    {
        $this->service->update($product, $this->validated($request, $product));

        return redirect()->route('products.show', $product)->with('success', 'Article modifié. Les reçus déjà émis conservent leurs anciens prix.');
    }

    /** Archivage : un article lié à des transactions n'est jamais supprimé, il est désactivé. */
    public function toggle(Product $product)
    {
        $product->update(['is_active' => ! $product->is_active]);
        AuditService::log('product.toggle', $product, ($product->is_active ? 'Réactivation' : 'Désactivation')." de « {$product->name} »");

        return back()->with('success', $product->is_active ? 'Article réactivé.' : 'Article désactivé (historique conservé).');
    }

    public function importForm()
    {
        return view('products.import');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|max:5120|mimes:csv,txt,xlsx']);
        $file = $request->file('file');
        $result = $this->service->import($file->getRealPath(), $file->getClientOriginalExtension(), $request->user(), $request->boolean('dry_run'));

        if ($result['errors']) {
            return back()->with('import_errors', $result['errors'])->with('error', 'Import refusé : corrigez les lignes signalées. Aucune donnée n\'a été importée.');
        }
        if ($request->boolean('dry_run')) {
            return back()->with('success', "Vérification réussie : {$result['valid']} ligne(s) valide(s). Vous pouvez lancer l'import.");
        }

        return redirect()->route('products.index')->with('success', "Import terminé : {$result['created']} article(s) créé(s), {$result['updated']} mis à jour.");
    }

    public function importTemplate()
    {
        $csv = "reference;designation;type;categorie;unite;prix_achat;prix_vente;seuil_alerte;code_barres;stock_initial;description\n"
            ."EX-001;Exemple produit (fictif);produit;Alimentation;pce;1000;1500;5;;20;Ligne d'exemple à supprimer\n"
            ."EX-002;Exemple service (fictif);service;Services;forf.;0;5000;0;;;Les services n'ont pas de stock\n";

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-produits.csv"',
        ]);
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::query()->where('is_active', true)->orWhere('id', $product->category_id)->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('name')->get(),
            'taxes' => Tax::query()->where('is_active', true)->orWhere('id', $product->tax_id)->get(),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product)],
            'name' => 'required|string|max:190',
            'type' => 'required|in:product,service',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'tax_id' => 'nullable|exists:taxes,id',
            'description' => 'nullable|string|max:2000',
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products')->ignore($product)],
            'cost_price' => 'required|numeric|min:0|max:9999999999999',
            'sale_price' => 'required|numeric|min:0|max:9999999999999',
            'alert_threshold' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|max:2048',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['alert_threshold'] = $data['alert_threshold'] ?? 0;
        unset($data['photo']);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('products', 'public');
        }

        return $data;
    }
}
