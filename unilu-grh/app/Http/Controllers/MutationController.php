<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Mutation;
use App\Services\MutationService;
use Illuminate\Http\Request;

class MutationController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $all = Mutation::query()->with(['employee', 'fromUnit', 'toUnit', 'author', 'validator'])->latest()->limit(200)->get()
            ->filter(fn ($m) => $u->canOnEmployee('mutations.manage', $m->employee) || $u->canOnEmployee('mutations.validate', $m->employee));

        return view('mutations.index', ['pending' => $all->where('status', 'draft'), 'done' => $all->where('status', '!=', 'draft')->take(60)]);
    }

    public function store(Request $request, Employee $employee, MutationService $svc)
    {
        $this->authorizeEmployee($employee, 'mutations.manage');
        $data = $request->validate([
            'kind' => 'required|in:internal,external', 'to_unit_id' => 'nullable|required_if:kind,internal|exists:org_units,id',
            'to_position_id' => 'nullable|exists:positions,id', 'destination_institution' => 'nullable|required_if:kind,external|string|max:200',
            'reason' => 'required|string|max:500', 'decision_date' => 'required|date', 'effective_date' => 'required|date', 'decision_reference' => 'required|string|max:80',
        ] + $this->docRules());
        $data['document_id'] = $this->attachDocument($request, $employee, 'Décision de mutation '.$data['decision_reference'], $data['decision_reference'], null, 'MUTATION');
        $svc->propose($employee, $data, $request->user());

        return redirect()->to(route('employees.show', $employee).'#mouvements')->with('success', 'Mutation enregistrée : effets appliqués à la validation par une autre personne.');
    }

    public function validateMutation(Request $request, Mutation $mutation, MutationService $svc)
    {
        $svc->validate($mutation, $request->user());

        return back()->with('success', $mutation->kind === 'external' ? 'Mutation externe validée : affectation clôturée, droits ordinaires arrêtés à la date d’effet, dossier conservé.' : 'Mutation interne validée : nouvelle affectation enregistrée.');
    }

    public function reject(Request $request, Mutation $mutation, MutationService $svc)
    {
        $svc->reject($mutation, $request->validate(['reason' => 'required|string|max:500'])['reason'], $request->user());

        return back()->with('success', 'Mutation rejetée.');
    }
}
