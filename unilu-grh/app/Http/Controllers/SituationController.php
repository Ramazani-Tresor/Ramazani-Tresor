<?php

namespace App\Http\Controllers;

use App\Models\AdministrativeSituation;
use App\Models\Employee;
use App\Services\SituationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SituationController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $visible = fn ($s) => $u->canOnEmployee('situations.manage', $s->employee) || $u->canOnEmployee('situations.validate', $s->employee);
        $base = AdministrativeSituation::query()->with(['employee.activeAssignment.orgUnit', 'type', 'author']);
        $days = (int) config('grh.alert_days_situation');

        return view('situations.index', [
            'pending' => (clone $base)->where('status', 'requested')->latest()->get()->filter($visible),
            'ongoing' => (clone $base)->where('status', 'approved')->orderBy('planned_end_date')->get()->filter($visible),
            'expiring' => (clone $base)->where('status', 'approved')->whereNotNull('planned_end_date')->whereDate('planned_end_date', '<=', now()->addDays($days))->get()->filter($visible),
            'closed' => (clone $base)->whereIn('status', ['closed', 'rejected'])->latest('updated_at')->limit(40)->get()->filter($visible),
        ]);
    }

    public function store(Request $request, Employee $employee, SituationService $svc)
    {
        $this->authorizeEmployee($employee, 'situations.manage');
        $data = $request->validate([
            'situation_type_id' => 'required|exists:situation_types,id', 'start_date' => 'required|date', 'planned_end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500', 'decision_reference' => 'nullable|string|max:80', 'authority' => 'nullable|string|max:150',
        ] + $this->docRules());
        $data['document_id'] = $this->attachDocument($request, $employee, 'Justificatif de situation', $data['decision_reference'] ?? null, $data['authority'] ?? null, 'CONGE');
        $s = $svc->request($employee, $data, $request->user());

        return redirect()->to(route('employees.show', $employee).'#situations')->with('success', $s->status === 'approved' ? 'Situation enregistrée et appliquée.' : 'Demande enregistrée : en attente d’approbation.');
    }

    public function approve(Request $request, AdministrativeSituation $situation, SituationService $svc)
    {
        $svc->approve($situation, $request->user());

        return back()->with('success', 'Situation approuvée : statut et droits financiers mis à jour selon les règles paramétrées.');
    }

    public function reject(Request $request, AdministrativeSituation $situation, SituationService $svc)
    {
        $svc->reject($situation, $request->validate(['reason' => 'required|string|max:500'])['reason'], $request->user());

        return back()->with('success', 'Demande rejetée.');
    }

    public function close(Request $request, AdministrativeSituation $situation, SituationService $svc)
    {
        $data = $request->validate(['end_date' => 'required|date', 'note' => 'nullable|string|max:500']);
        $svc->close($situation, Carbon::parse($data['end_date']), $request->user(), true, $data['note'] ?? null);

        return back()->with('success', 'Situation clôturée ; l’agent est réintégré en activité.');
    }

    public function extend(Request $request, AdministrativeSituation $situation, SituationService $svc)
    {
        $this->authorizeEmployee($situation->employee, 'situations.manage');
        $data = $request->validate(['new_end' => 'required|date', 'reason' => 'required|string|max:500', 'decision_reference' => 'nullable|string|max:80']);
        $svc->extend($situation, Carbon::parse($data['new_end']), $data, $request->user());

        return back()->with('success', 'Prolongation demandée : en attente d’approbation.');
    }
}
