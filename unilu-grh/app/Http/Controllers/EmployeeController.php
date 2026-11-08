<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\JobFunction;
use App\Models\KinshipDegree;
use App\Models\Mandate;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\RecordChange;
use App\Models\SituationType;
use App\Models\StaffCategory;
use App\Models\SupportType;
use App\Services\AssignmentService;
use App\Services\AuditService;
use App\Services\DocumentStore;
use App\Services\EmployeeService;
use App\Services\PrimeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public static function refs(): array
    {
        return [
            'grades' => Grade::query()->where('is_active', true)->orderBy('family')->orderByDesc('rank')->get(),
            'categories' => StaffCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'mandates' => Mandate::query()->where('is_active', true)->orderBy('sequence')->get(),
            'functions' => JobFunction::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::query()->where('is_active', true)->orderBy('title')->get(),
            'situationTypes' => SituationType::query()->where('is_active', true)->orderBy('kind')->orderBy('name')->get(),
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(),
            'supportTypes' => SupportType::query()->where('is_active', true)->orderBy('name')->get(),
            'kinships' => KinshipDegree::query()->where('is_active', true)->get(),
            'adminGrades' => Grade::query()->where('family', 'administrative')->where('is_active', true)->orderByDesc('rank')->get(),
        ];
    }

    private function rules(?Employee $e = null): array
    {
        return [
            'matricule' => ['nullable', 'string', 'max:40', Rule::unique('employees', 'matricule')->ignore($e?->id)],
            'last_name' => 'required|string|max:120', 'middle_name' => 'nullable|string|max:120', 'first_name' => 'nullable|string|max:120',
            'sex' => 'required|in:M,F', 'birth_date' => 'nullable|date|before:today', 'birth_place' => 'nullable|string|max:120',
            'nationality' => 'nullable|string|max:80', 'address' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:150', 'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'children_count' => 'nullable|integer|min:0|max:40', 'family_notes' => 'nullable|string|max:1000',
            'emergency_contact_name' => 'nullable|string|max:150', 'emergency_contact_phone' => 'nullable|string|max:40', 'emergency_contact_relation' => 'nullable|string|max:80',
            'first_service_date' => 'nullable|date', 'engagement_date' => 'nullable|date',
            'staff_family' => 'required|in:academic,scientific,administrative', 'staff_category_id' => 'nullable|exists:staff_categories,id',
            'grade_id' => 'nullable|exists:grades,id', 'mandate_id' => 'nullable|exists:mandates,id', 'mandate_start' => 'nullable|date', 'mandate_end' => 'nullable|date|after_or_equal:mandate_start',
            'assistant_track' => 'nullable|in:teaching,research', 'admin_grade_id' => 'nullable|exists:grades,id', 'job_function_id' => 'nullable|exists:job_functions,id',
            'bank_name' => 'nullable|string|max:120', 'bank_account' => 'nullable|string|max:60', 'bank_holder' => 'nullable|string|max:150',
            'org_unit_id' => 'nullable|exists:org_units,id', 'position_id' => 'nullable|exists:positions,id',
        ];
    }

    public function index()
    {
        return view('employees.index');
    }

    public function create()
    {
        return view('employees.form', ['employee' => new Employee(['staff_family' => 'scientific', 'nationality' => 'Congolaise', 'sex' => 'M'])] + self::refs());
    }

    public function store(Request $request, EmployeeService $svc, AssignmentService $assign)
    {
        $data = $request->validate($this->rules());
        $e = $svc->create($data, $request->user());
        if (! empty($data['org_unit_id'])) {
            $assign->assign($e, ['org_unit_id' => $data['org_unit_id'], 'position_id' => $data['position_id'] ?? null, 'start_date' => $data['engagement_date'] ?? now()->toDateString(), 'reason' => 'Affectation à l’ouverture du dossier'], $request->user(), true, false);
        }

        return redirect()->route('employees.show', $e)->with('success', "Dossier {$e->matricule} créé.");
    }

    public function show(Request $request, Employee $employee, PrimeService $primes)
    {
        $this->authorizeEmployee($employee, 'employees.view');
        $u = $request->user();
        $employee->load(['grade', 'category', 'mandate', 'adminGrade', 'jobFunction', 'activeAssignment.orgUnit', 'activeAssignment.position', 'death', 'user']);
        $data = [
            'employee' => $employee,
            'career' => $employee->careerEvents()->with(['oldGrade', 'newGrade', 'oldCategory', 'newCategory', 'oldMandate', 'newMandate', 'document', 'author', 'validator'])->orderByDesc('effective_date')->get(),
            'qualifications' => $employee->qualifications()->with(['academicYear', 'orgUnit', 'document'])->orderByDesc('defense_date')->get(),
            'assignments' => $employee->assignments()->with(['orgUnit', 'fromUnit', 'position', 'jobFunction', 'document'])->orderByDesc('start_date')->get(),
            'situations' => $employee->situations()->with(['type', 'document'])->orderByDesc('start_date')->get(),
            'mutations' => $employee->mutations()->with(['fromUnit', 'toUnit'])->orderByDesc('effective_date')->get(),
            'documents' => $u->canOnEmployee('employees.documents', $employee) || $u->canOnEmployee('employees.view', $employee) ? $employee->documents()->with('type')->orderByDesc('document_date')->get() : collect(),
            'canFinance' => $u->canOnEmployee('primes.view', $employee) || $u->hasGlobalPermission('primes.view'),
            'canBank' => $u->canOnEmployee('employees.bank', $employee),
            'canHistory' => $u->canOnEmployee('employees.history', $employee),
        ];
        if ($data['canFinance']) {
            $data['summary'] = $primes->summary($employee);
            $data['entitlements'] = $employee->entitlements()->with(['primeType', 'adjustments', 'payments'])->orderByDesc('period_start')->limit(36)->get();
            $data['supports'] = $employee->socialSupports()->with(['type', 'kinship'])->latest()->get();
        }
        if ($data['canHistory']) {
            $data['history'] = RecordChange::query()->with('user')->where('employee_id', $employee->id)->orderByDesc('id')->limit(200)->get();
        }

        return view('employees.show', $data + self::refs());
    }

    public function edit(Employee $employee)
    {
        $this->authorizeEmployee($employee, 'employees.manage');

        return view('employees.form', ['employee' => $employee] + self::refs());
    }

    public function update(Request $request, Employee $employee, EmployeeService $svc)
    {
        $this->authorizeEmployee($employee, 'employees.manage');
        $svc->update($employee, $request->validate($this->rules($employee)), $request->user());

        return redirect()->route('employees.show', $employee)->with('success', 'Dossier mis à jour (modifications historisées).');
    }

    public function bank(Request $request, Employee $employee, EmployeeService $svc)
    {
        $this->authorizeEmployee($employee, 'employees.bank');
        $svc->updateBank($employee, $request->validate(['bank_name' => 'nullable|string|max:120', 'bank_account' => 'nullable|string|max:60', 'bank_holder' => 'nullable|string|max:150']), $request->user());

        return redirect()->to(route('employees.show', $employee).'#finances')->with('success', 'Coordonnées bancaires enregistrées (chiffrées).');
    }

    public function photo(Request $request, Employee $employee, DocumentStore $store, EmployeeService $svc)
    {
        $this->authorizeEmployee($employee, 'employees.manage');
        $request->validate(['photo' => 'required|file|max:3072']);
        $raw = $store->storeRaw($request->file('photo'), 'photos', ['image/jpeg' => 'jpg', 'image/png' => 'png']);
        $svc->setPhoto($employee, $raw['path'], $request->user());

        return back()->with('success', 'Photo d’identité enregistrée.');
    }

    public function photoFile(Request $request, Employee $employee)
    {
        $u = $request->user();
        abort_unless($u->canOnEmployee('employees.view', $employee) || $u->employee_id === $employee->id, 403);
        abort_unless($employee->photo_path && Storage::disk('local')->exists($employee->photo_path), 404);

        return response()->file(Storage::disk('local')->path($employee->photo_path), ['Cache-Control' => 'private, max-age=600']);
    }

    public function sheet(Request $request, Employee $employee, PrimeService $primes)
    {
        $this->authorizeEmployee($employee, 'employees.view');

        return $this->renderSheet($employee, $request->user()->canOnEmployee('primes.view', $employee) || $request->user()->hasGlobalPermission('primes.view'), $primes);
    }

    public static function renderSheet(Employee $employee, bool $withFinance, PrimeService $primes)
    {
        $employee->load(['grade', 'category', 'mandate', 'adminGrade', 'jobFunction', 'activeAssignment.orgUnit', 'activeAssignment.position']);
        $pdf = Pdf::loadView('pdf.employee-sheet', [
            'e' => $employee,
            'career' => $employee->careerEvents()->with(['newGrade', 'newCategory', 'newMandate'])->where('status', 'validated')->orderBy('effective_date')->get(),
            'qualifications' => $employee->qualifications()->with('academicYear')->orderBy('defense_date')->get(),
            'assignments' => $employee->assignments()->with(['orgUnit', 'position'])->orderBy('start_date')->get(),
            'summary' => $withFinance ? $primes->summary($employee) : null,
            'reference' => 'FIC-'.$employee->matricule.'-'.now()->format('YmdHi'),
        ])->setPaper('a4');
        AuditService::log('reports.sheet', $employee, "Fiche individuelle de {$employee->matricule}");

        return $pdf->stream('fiche-'.$employee->matricule.'.pdf');
    }
}
