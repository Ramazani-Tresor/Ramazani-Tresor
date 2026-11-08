<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\OrgUnit;
use App\Services\AssetService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    private function refs(): array
    {
        return [
            'categories' => AssetCategory::query()->where('is_active', true)->orderBy('kind')->orderBy('name')->get(),
            'units' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'buildings' => Asset::query()->whereHas('category', fn ($c) => $c->where('kind', 'immovable'))->orderBy('designation')->get(),
            'people' => Employee::query()->whereNotIn('status', Employee::ENDED_STATUSES)->orderBy('last_name')->get(['id', 'matricule', 'last_name', 'middle_name', 'first_name']),
        ];
    }

    public function index(Request $request)
    {
        $q = Asset::query()->with(['category', 'orgUnit', 'responsible', 'parent'])->orderBy('code');
        if ($request->filled('q')) {
            $t = '%'.$request->input('q').'%';
            $q->where(fn ($w) => $w->where('code', 'like', $t)->orWhere('designation', 'like', $t)->orWhere('serial_number', 'like', $t)->orWhere('location', 'like', $t));
        }
        foreach (['category' => 'asset_category_id', 'condition' => 'condition', 'status' => 'status'] as $k => $col) {
            if ($request->filled($k)) {
                $q->where($col, $request->input($k));
            }
        }
        if ($request->filled('kind')) {
            $q->whereHas('category', fn ($c) => $c->where('kind', $request->input('kind')));
        }
        if ($request->filled('unit')) {
            $q->whereIn('org_unit_id', OrgUnit::subtreeIds($request->integer('unit')));
        }

        return view('assets.index', ['assets' => $q->paginate(40)->withQueryString()] + $this->refs());
    }

    public function create()
    {
        return view('assets.form', ['asset' => new Asset(['condition' => 'good', 'currency' => setting('default_currency', 'USD'), 'quantity' => 1])] + $this->refs());
    }

    private function rules(bool $create): array
    {
        return [
            'code' => $create ? 'nullable|string|max:40|unique:assets,code' : 'prohibited',
            'asset_category_id' => $create ? 'required|exists:asset_categories,id' : 'prohibited', 'designation' => 'required|string|max:200',
            'parent_id' => 'nullable|exists:assets,id', 'location' => 'nullable|string|max:200', 'brand_model' => 'nullable|string|max:150',
            'serial_number' => 'nullable|string|max:120', 'quantity' => 'nullable|integer|min:1|max:100000', 'acquisition_date' => 'nullable|date',
            'acquisition_mode' => 'nullable|in:purchase,construction,donation,transfer,other', 'value' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3',
            'condition' => $create ? 'required|in:new,good,fair,poor,out_of_order' : 'prohibited', 'org_unit_id' => $create ? 'nullable|exists:org_units,id' : 'prohibited',
            'responsible_employee_id' => $create ? 'nullable|exists:employees,id' : 'prohibited', 'notes' => 'nullable|string|max:2000', 'reference' => 'nullable|string|max:80',
        ];
    }

    public function store(Request $request, AssetService $svc)
    {
        $a = $svc->create($request->validate($this->rules(true)), $request->user());

        return redirect()->route('assets.show', $a)->with('success', "Bien {$a->code} entré en patrimoine.");
    }

    public function show(Asset $asset)
    {
        return view('assets.show', ['asset' => $asset->load(['category', 'orgUnit', 'responsible', 'parent', 'children', 'movements.fromUnit', 'movements.toUnit', 'movements.user', 'movements.responsible'])] + $this->refs());
    }

    public function update(Request $request, Asset $asset, AssetService $svc)
    {
        $svc->update($asset, $request->validate($this->rules(false)), $request->user());

        return back()->with('success', 'Fiche du bien mise à jour.');
    }

    public function transfer(Request $request, Asset $asset, AssetService $svc)
    {
        $svc->transfer($asset, $request->validate(['to_unit_id' => 'nullable|exists:org_units,id', 'responsible_employee_id' => 'nullable|exists:employees,id', 'location' => 'nullable|string|max:200', 'movement_date' => 'required|date', 'reference' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:500']), $request->user());

        return back()->with('success', 'Affectation / transfert enregistré.');
    }

    public function condition(Request $request, Asset $asset, AssetService $svc)
    {
        $data = $request->validate(['condition' => 'required|in:new,good,fair,poor,out_of_order', 'notes' => 'nullable|string|max:500', 'movement_date' => 'nullable|date']);
        $svc->changeCondition($asset, $data['condition'], $request->user(), $data['notes'] ?? null, $data['movement_date'] ?? null);

        return back()->with('success', 'État du bien mis à jour.');
    }

    public function retire(Request $request, Asset $asset, AssetService $svc)
    {
        $data = $request->validate(['type' => 'required|in:reform,scrap', 'reference' => 'required|string|max:80', 'notes' => 'nullable|string|max:500']);
        $svc->retire($asset, $data['type'], $data['reference'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', $data['type'] === 'reform' ? 'Bien réformé.' : 'Bien mis au rebut.');
    }
}
