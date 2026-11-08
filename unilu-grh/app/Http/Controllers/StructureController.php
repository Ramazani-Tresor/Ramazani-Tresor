<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\JobFunction;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StructureController extends Controller
{
    public function index(Request $request)
    {
        $units = OrgUnit::query()->orderBy('name')->get();
        $counts = Assignment::query()->where('status', 'active')->where('is_primary', true)->selectRaw('org_unit_id, count(*) n')->groupBy('org_unit_id')->pluck('n', 'org_unit_id');
        $positions = Position::query()->where('is_active', true)->withCount(['assignments as occupied' => fn ($q) => $q->where('status', 'active')])->get();
        $stats = ['seats' => $positions->sum('seats'), 'occupied' => $positions->sum(fn ($p) => min($p->occupied, $p->seats))];

        return view('structure.index', ['tree' => $units->groupBy(fn ($u) => (int) $u->parent_id), 'counts' => $counts, 'stats' => $stats, 'units' => $units, 'vacant' => $positions->filter(fn ($p) => $p->occupied < $p->seats)->load('orgUnit')]);
    }

    public function show(OrgUnit $unit)
    {
        $ids = OrgUnit::subtreeIds($unit->id);
        $u = request()->user();
        $positions = $unit->positions()->with(['jobFunction'])->withCount(['assignments as occupied' => fn ($q) => $q->where('status', 'active')])->get();
        $staff = Assignment::query()->with(['employee.grade', 'orgUnit', 'position'])->where('status', 'active')->whereIn('org_unit_id', $ids)->get()
            ->filter(fn ($a) => $u->canOnEmployee('employees.view', $a->employee) || $u->hasPermission('employees.directory'));
        $moves = Assignment::query()->with(['employee', 'orgUnit', 'fromUnit'])->where(fn ($q) => $q->whereIn('org_unit_id', $ids)->orWhereIn('from_unit_id', $ids))->latest('start_date')->limit(20)->get()
            ->filter(fn ($a) => $u->canOnEmployee('employees.view', $a->employee));

        return view('structure.show', ['unit' => $unit->load('parent'), 'children' => $unit->children()->orderBy('name')->get(), 'positions' => $positions, 'staff' => $staff, 'moves' => $moves, 'functions' => JobFunction::query()->where('is_active', true)->orderBy('name')->get(), 'units' => OrgUnit::query()->orderBy('name')->get()]);
    }

    public function storeUnit(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'code' => 'required|string|max:30|unique:org_units,code', 'type' => ['required', Rule::in(array_keys(OrgUnit::TYPES))], 'parent_id' => 'nullable|exists:org_units,id']);
        $u = OrgUnit::create($data);
        OrgUnit::flushTree();
        AuditService::log('structure.create', $u, "Création de l’unité {$u->name}");

        return redirect()->route('structure.show', $u)->with('success', 'Unité créée.');
    }

    public function updateUnit(Request $request, OrgUnit $unit)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'type' => ['required', Rule::in(array_keys(OrgUnit::TYPES))], 'parent_id' => ['nullable', 'exists:org_units,id', Rule::notIn(OrgUnit::subtreeIds($unit->id))], 'is_active' => 'nullable|boolean']);
        $data['is_active'] = $request->boolean('is_active');
        $unit->update($data);
        OrgUnit::flushTree();
        AuditService::log('structure.update', $unit, "Modification de l’unité {$unit->name}");

        return back()->with('success', 'Unité mise à jour.');
    }

    public function storePosition(Request $request, OrgUnit $unit)
    {
        $data = $request->validate(['title' => 'required|string|max:150', 'code' => 'required|string|max:40|unique:positions,code', 'job_function_id' => 'nullable|exists:job_functions,id', 'seats' => 'required|integer|min:1|max:500']);
        $p = $unit->positions()->create($data);
        AuditService::log('positions.create', $p, "Création du poste {$p->title}");

        return back()->with('success', 'Poste créé.');
    }

    public function updatePosition(Request $request, Position $position)
    {
        $data = $request->validate(['title' => 'required|string|max:150', 'job_function_id' => 'nullable|exists:job_functions,id', 'seats' => 'required|integer|min:1|max:500']);
        $data['is_active'] = $request->boolean('is_active', true);
        $position->update($data);
        AuditService::log('positions.update', $position, "Modification du poste {$position->title}");

        return back()->with('success', 'Poste mis à jour.');
    }
}
