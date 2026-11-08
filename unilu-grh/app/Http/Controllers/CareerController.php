<?php

namespace App\Http\Controllers;

use App\Models\CareerEvent;
use App\Models\Employee;
use App\Services\CareerService;
use Illuminate\Http\Request;

class CareerController extends Controller
{
    public function index(Request $request, CareerService $svc)
    {
        $u = $request->user();
        $pending = CareerEvent::query()->with(['employee.grade', 'oldGrade', 'newGrade', 'newCategory', 'newMandate', 'author'])->where('status', 'draft')->latest()->get()
            ->filter(fn ($ev) => $u->canOnEmployee('career.manage', $ev->employee) || $u->canOnEmployee('career.validate', $ev->employee));
        $recent = CareerEvent::query()->with(['employee', 'newGrade', 'validator'])->whereIn('status', ['validated', 'rejected'])->latest('updated_at')->limit(30)->get()
            ->filter(fn ($ev) => $u->canOnEmployee('employees.view', $ev->employee));
        $mandates = $svc->expiringMandates((int) config('grh.alert_days_mandate'))->get()->filter(fn ($e) => $u->canOnEmployee('employees.view', $e));

        return view('career.index', compact('pending', 'recent', 'mandates'));
    }

    public function store(Request $request, Employee $employee, CareerService $svc)
    {
        $this->authorizeEmployee($employee, 'career.manage');
        $data = $request->validate([
            'type' => 'required|in:nomination,promotion,mandate_renewal,reclassification,doctorate,function_change',
            'new_grade_id' => 'nullable|exists:grades,id', 'new_category_id' => 'nullable|exists:staff_categories,id',
            'new_mandate_id' => 'nullable|exists:mandates,id', 'new_mandate_end' => 'nullable|date',
            'effective_date' => 'required|date', 'decision_date' => 'required|date', 'decision_number' => 'required|string|max:80',
            'signing_authority' => 'required|string|max:150', 'observations' => 'nullable|string|max:2000', 'allow_family_change' => 'nullable|boolean',
        ] + $this->docRules());
        $data['document_id'] = $this->attachDocument($request, $employee, 'Décision '.$data['decision_number'], $data['decision_number'], $data['signing_authority'], $data['type'] === 'promotion' ? 'PROMOTION' : 'NOMINATION');
        $svc->propose($employee, $data, $request->user());

        return redirect()->to(route('employees.show', $employee).'#carriere')->with('success', 'Décision enregistrée : elle prendra effet après validation par une autre personne habilitée.');
    }

    public function validateEvent(Request $request, CareerEvent $event, CareerService $svc)
    {
        $svc->validate($event, $request->user());

        return back()->with('success', 'Décision validée : le dossier de l’agent est mis à jour.');
    }

    public function reject(Request $request, CareerEvent $event, CareerService $svc)
    {
        $svc->reject($event, $request->validate(['reason' => 'required|string|max:500'])['reason'], $request->user());

        return back()->with('success', 'Proposition rejetée.');
    }
}
