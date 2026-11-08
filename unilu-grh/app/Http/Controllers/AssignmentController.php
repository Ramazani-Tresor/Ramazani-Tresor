<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Employee;
use App\Services\AssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AssignmentController extends Controller
{
    public function store(Request $request, Employee $employee, AssignmentService $svc)
    {
        $this->authorizeEmployee($employee, 'assignments.manage');
        $data = $request->validate([
            'org_unit_id' => 'required|exists:org_units,id', 'position_id' => 'nullable|exists:positions,id', 'job_function_id' => 'nullable|exists:job_functions,id',
            'is_primary' => 'nullable|boolean', 'start_date' => 'required|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'required|string|max:255', 'decision_number' => 'nullable|string|max:80', 'authority' => 'nullable|string|max:150',
        ] + $this->docRules());
        $data['is_primary'] = $request->boolean('is_primary', true);
        $data['document_id'] = $this->attachDocument($request, $employee, 'Décision d’affectation', $data['decision_number'] ?? null, $data['authority'] ?? null, 'DECISION');
        $svc->assign($employee, $data, $request->user());

        return redirect()->to(route('employees.show', $employee).'#affectations')->with('success', 'Affectation enregistrée ; l’affectation principale précédente a été clôturée.');
    }

    public function close(Request $request, Assignment $assignment, AssignmentService $svc)
    {
        $data = $request->validate(['end_date' => 'required|date', 'reason' => 'required|string|max:255']);
        $svc->closeByUser($assignment, Carbon::parse($data['end_date']), $data['reason'], $request->user());

        return back()->with('success', 'Affectation clôturée.');
    }
}
