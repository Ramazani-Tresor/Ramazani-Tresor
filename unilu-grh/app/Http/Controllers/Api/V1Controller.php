<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\OrgUnit;
use App\Services\StatsService;
use Illuminate\Http\Request;

/** API v1 (lecture seule) : données minimales, périmètre de l'utilisateur, jamais de données bancaires ni de cause de décès. */
class V1Controller extends Controller
{
    private function present(Employee $e, bool $full): array
    {
        $base = ['matricule' => $e->matricule, 'nom' => $e->last_name, 'postnom' => $e->middle_name, 'prenom' => $e->first_name, 'affectation' => $e->activeAssignment?->orgUnit?->code];
        if (! $full) {
            return $base;
        }

        return $base + [
            'sexe' => $e->sex, 'famille' => $e->staff_family, 'categorie' => $e->category?->code, 'grade' => $e->grade?->code, 'mandat' => $e->mandate?->code,
            'fin_mandat' => $e->mandate_end?->toDateString(), 'fonction' => $e->jobFunction?->code, 'situation' => $e->status,
            'date_engagement' => $e->engagement_date?->toDateString(), 'courriel' => $e->email,
        ];
    }

    public function employees(Request $request)
    {
        $u = $request->user();
        $full = $u->hasPermission('employees.view');
        $q = Employee::query()->with(['activeAssignment.orgUnit', 'grade', 'category', 'mandate', 'jobFunction'])->search($request->input('q'));
        $u->scopeEmployees($q, $full ? 'employees.view' : 'employees.directory');
        if ($request->filled('situation')) {
            $q->where('status', $request->input('situation'));
        }
        $page = $q->orderBy('matricule')->paginate(min(100, max(1, $request->integer('par_page', 50))));

        return response()->json(['data' => collect($page->items())->map(fn ($e) => $this->present($e, $full)), 'meta' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function employee(Request $request, string $matricule)
    {
        $e = Employee::query()->with(['activeAssignment.orgUnit', 'grade', 'category', 'mandate', 'jobFunction'])->where('matricule', $matricule)->firstOrFail();
        abort_unless($request->user()->canOnEmployee('employees.view', $e), 403);

        return response()->json(['data' => $this->present($e, true) + [
            'carriere' => $e->careerEvents()->with(['newGrade', 'newMandate'])->where('status', 'validated')->orderBy('effective_date')->get()->map(fn ($c) => ['type' => $c->type, 'date_effet' => $c->effective_date->toDateString(), 'decision' => $c->decision_number, 'nouveau_grade' => $c->newGrade?->code, 'nouveau_mandat' => $c->newMandate?->code]),
            'affectations' => $e->assignments()->with('orgUnit')->orderBy('start_date')->get()->map(fn ($a) => ['unite' => $a->orgUnit?->code, 'debut' => $a->start_date->toDateString(), 'fin' => $a->end_date?->toDateString(), 'statut' => $a->status]),
        ]]);
    }

    public function units()
    {
        return response()->json(['data' => OrgUnit::query()->orderBy('code')->get()->map(fn ($u) => ['id' => $u->id, 'code' => $u->code, 'nom' => $u->name, 'type' => $u->type, 'parent_id' => $u->parent_id, 'active' => $u->is_active])]);
    }

    public function stats(Request $request, StatsService $stats)
    {
        $s = $stats->dashboard($request->user(), array_filter($request->only(['year', 'unit', 'family', 'category', 'status'])));
        unset($s['year']);

        return response()->json(['data' => $s]);
    }

    public function assets(Request $request)
    {
        $page = Asset::query()->with(['category', 'orgUnit'])->orderBy('code')->paginate(100);

        return response()->json(['data' => collect($page->items())->map(fn ($a) => ['code' => $a->code, 'designation' => $a->designation, 'categorie' => $a->category->code, 'unite' => $a->orgUnit?->code, 'etat' => $a->condition, 'statut' => $a->status, 'quantite' => $a->quantity]), 'meta' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()]]);
    }
}
