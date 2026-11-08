<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Qualification;
use App\Services\AuditService;
use App\Support\Labels;
use Illuminate\Http\Request;

class QualificationController extends Controller
{
    private function rules(): array
    {
        return [
            'nature' => 'required|in:licence,master,dea,research_thesis,dea_thesis,doctoral_thesis,doctorate,other',
            'title' => 'nullable|string|max:300', 'field' => 'nullable|string|max:150', 'institution' => 'nullable|string|max:200',
            'defense_university' => 'nullable|string|max:200', 'defense_date' => 'nullable|date', 'academic_year_id' => 'nullable|exists:academic_years,id',
            'mention' => 'nullable|string|max:80', 'supervisor' => 'nullable|string|max:150', 'org_unit_id' => 'nullable|exists:org_units,id',
        ] + $this->docRules();
    }

    public function store(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee, 'qualifications.manage');
        $data = $request->validate($this->rules());
        $data['document_id'] = $this->attachDocument($request, $employee, Labels::status('qualification', $data['nature']).' — justificatif', null, null, 'DIPLOME');
        unset($data['document'], $data['document_title']);
        $data['academic_year_id'] ??= $data['defense_date'] ? AcademicYear::forDate($data['defense_date'])?->id : null;
        $q = Qualification::create($data + ['employee_id' => $employee->id, 'created_by' => $request->user()->id]);
        AuditService::log('qualifications.create', $q, "Ajout {$q->nature} au dossier {$employee->matricule}");

        return redirect()->to(route('employees.show', $employee).'#diplomes')->with('success', 'Diplôme ou travail scientifique enregistré.');
    }

    public function update(Request $request, Qualification $qualification)
    {
        $this->authorizeEmployee($qualification->employee, 'qualifications.manage');
        $data = $request->validate($this->rules());
        if ($id = $this->attachDocument($request, $qualification->employee, 'Justificatif', null, null, 'DIPLOME')) {
            $data['document_id'] = $id;
        }
        unset($data['document'], $data['document_title']);
        $qualification->update($data);
        AuditService::log('qualifications.update', $qualification, 'Mise à jour');

        return redirect()->to(route('employees.show', $qualification->employee).'#diplomes')->with('success', 'Qualification mise à jour (historisée).');
    }
}
