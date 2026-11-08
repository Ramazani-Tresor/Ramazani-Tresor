<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Inventory;
use App\Models\OrgUnit;
use App\Services\AssetService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        return view('inventories.index', ['inventories' => Inventory::query()->with('orgUnit')->withCount('lines')->latest()->get(), 'units' => OrgUnit::query()->orderBy('name')->get()]);
    }

    public function store(Request $request, AssetService $svc)
    {
        $inv = $svc->openInventory($request->validate(['label' => 'required|string|max:150', 'org_unit_id' => 'nullable|exists:org_units,id', 'started_on' => 'required|date']), $request->user());

        return redirect()->route('inventories.show', $inv)->with('success', "Inventaire {$inv->reference} ouvert.");
    }

    public function show(Inventory $inventory)
    {
        $q = Asset::query()->with('category')->where('status', '!=', 'scrapped')->orderBy('code');
        if ($inventory->org_unit_id) {
            $q->whereIn('org_unit_id', OrgUnit::subtreeIds($inventory->org_unit_id));
        }

        return view('inventories.show', ['inventory' => $inventory->load('orgUnit'), 'assets' => $q->get(), 'lines' => $inventory->lines()->get()->keyBy('asset_id')]);
    }

    public function check(Request $request, Inventory $inventory, AssetService $svc)
    {
        $data = $request->validate(['asset_id' => 'required|exists:assets,id', 'found' => 'required|boolean', 'observed_condition' => 'nullable|in:new,good,fair,poor,out_of_order', 'observed_location' => 'nullable|string|max:200', 'notes' => 'nullable|string|max:300']);
        $svc->check($inventory, Asset::query()->findOrFail($data['asset_id']), (bool) $data['found'], $data['observed_condition'] ?? null, $data['observed_location'] ?? null, $data['notes'] ?? null, $request->user());

        return back()->with('success', 'Constat enregistré.');
    }

    public function close(Request $request, Inventory $inventory, AssetService $svc)
    {
        $s = $svc->closeInventory($inventory, $request->user());

        return back()->with('success', "Inventaire clôturé : {$s['checked']} bien(s) constaté(s), {$s['missing']} non retrouvé(s), {$s['condition_changed']} changement(s) d’état.");
    }
}
