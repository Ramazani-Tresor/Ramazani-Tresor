<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\ChargeAdjustment;
use App\Models\Enrollment;
use App\Models\FeeCategory;
use App\Models\IssuedDocument;
use App\Models\Promotion;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\AuditService;
use App\Services\BalanceService;
use App\Services\ContractService;
use App\Services\YearGuard;
use App\Support\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $filters = Filters::from($request, 'students.view');
        $yearId = $filters->get('year');
        $students = Student::query()
            ->with(['enrollments' => fn ($q) => $q->where('academic_year_id', $yearId)->with('promotion')])
            ->search($filters->get('q'))
            ->inFaculties($filters->facultyScope, $request->boolean('all_years') ? null : $yearId)
            ->when($filters->get('faculty'), fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_year_id', $yearId)->whereHas('promotion.program.department', fn ($d) => $d->where('faculty_id', $v))))
            ->when($filters->get('promotion'), fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_year_id', $yearId)->where('promotion_id', $v)))
            ->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();

        return view('students.index', compact('students', 'filters'));
    }

    public function show(Request $request, Student $student, BalanceService $balances, ContractService $contracts)
    {
        $this->authorizeStudent($student, 'students.view');
        $years = AcademicYear::query()->whereIn('id', $student->enrollments()->pluck('academic_year_id')->merge($student->charges()->pluck('academic_year_id')))->orderByDesc('starts_on')->get();
        $year = $request->filled('year') ? $years->firstWhere('id', $request->integer('year')) : ($years->firstWhere('is_current', true) ?? $years->first());
        $situation = $balances->situation($student, $year?->id);

        return view('students.show', [
            'student' => $student->load(['enrollments.promotion.program.department.faculty', 'enrollments.academicYear', 'documents.uploader']),
            'years' => $years, 'year' => $year, 'situation' => $situation,
            'payments' => $student->payments()->with('bankAccount.bank')->when($year, fn ($q) => $q->where('academic_year_id', $year->id))->latest('id')->get(),
            'adjustments' => ChargeAdjustment::query()->with(['charge.category', 'requester', 'approver'])->whereHas('charge', fn ($q) => $q->where('student_id', $student->id)->when($year, fn ($w) => $w->where('academic_year_id', $year->id)))->latest('id')->get(),
            'contracts' => $student->contracts()->with('installments')->latest('id')->get()->map(fn ($c) => ['c' => $c, 'compliance' => $contracts->compliance($c)]),
            'issued' => IssuedDocument::query()->where('student_id', $student->id)->latest('id')->get(),
            'manualCategories' => FeeCategory::query()->where('is_active', true)->where('trigger', 'manual')->get(),
            'allCategories' => FeeCategory::query()->where('is_active', true)->orderBy('priority')->get(),
            'promotions' => Promotion::query()->where('is_active', true)->orderBy('code')->get(),
            'openYears' => AcademicYear::query()->where('status', 'open')->get(),
        ]);
    }

    public function create()
    {
        return view('students.form', ['student' => new Student, 'promotions' => $this->promotionsInScope(), 'years' => AcademicYear::query()->where('status', 'open')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $enr = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'promotion_id' => ['required', Rule::in($this->promotionsInScope()->pluck('id'))],
            'status' => 'required|in:new,returning,finalist',
        ]);
        YearGuard::assertWritable((int) $enr['academic_year_id']);
        $student = DB::transaction(function () use ($data, $enr) {
            $student = Student::create($data + ['is_active' => true]);
            Enrollment::create($enr + ['student_id' => $student->id, 'state' => 'active', 'enrolled_on' => now()->toDateString()]);

            return $student;
        });
        AuditService::log('student.create', $student, "Création du dossier {$student->matricule}");

        return redirect()->route('students.show', $student)->with('success', 'Dossier créé. Générez ses créances depuis le dossier.');
    }

    public function edit(Student $student)
    {
        $this->authorizeStudent($student, 'students.manage');

        return view('students.form', ['student' => $student, 'promotions' => collect(), 'years' => collect()]);
    }

    public function update(Request $request, Student $student)
    {
        $this->authorizeStudent($student, 'students.manage');
        $old = $student->only(['matricule', 'last_name', 'post_name', 'first_name', 'phone', 'email', 'address']);
        $student->update($this->validated($request, $student));
        AuditService::log('student.update', $student, "Modification du dossier {$student->matricule}", $old, $student->only(array_keys($old)));

        return redirect()->route('students.show', $student)->with('success', 'Dossier mis à jour.');
    }

    public function enroll(Request $request, Student $student)
    {
        $this->authorizeStudent($student, 'students.manage');
        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'promotion_id' => ['required', Rule::in($this->promotionsInScope()->pluck('id'))],
            'status' => 'required|in:new,returning,finalist',
        ]);
        YearGuard::assertWritable((int) $data['academic_year_id']);
        if ($student->enrollments()->where('academic_year_id', $data['academic_year_id'])->exists()) {
            throw new BusinessException('L’étudiant est déjà inscrit pour cette année.');
        }
        $e = Enrollment::create($data + ['student_id' => $student->id, 'state' => 'active', 'enrolled_on' => now()->toDateString()]);
        AuditService::log('enrollment.create', $e, "Inscription de {$student->matricule}");

        return back()->with('success', 'Inscription enregistrée.');
    }

    public function uploadDocument(Request $request, Student $student)
    {
        $this->authorizeStudent($student, 'students.manage');
        $request->validate(['label' => 'required|string|max:150', 'file' => 'required|file|max:5120']);
        $f = $this->storePrivateFile($request->file('file'), 'students/'.$student->id);
        $doc = StudentDocument::create(['student_id' => $student->id, 'label' => $request->input('label'), 'path' => $f['path'], 'mime' => $f['mime'], 'size' => $f['size'], 'uploaded_by' => $request->user()->id]);
        AuditService::log('student.document', $doc, "Pièce « {$doc->label} » ajoutée au dossier {$student->matricule}");

        return back()->with('success', 'Pièce ajoutée au dossier.');
    }

    public function downloadDocument(Student $student, StudentDocument $document)
    {
        $this->authorizeStudent($student, 'students.view');
        abort_unless($document->student_id === $student->id, 404);

        return Storage::download($document->path, $document->label.'.'.pathinfo($document->path, PATHINFO_EXTENSION));
    }

    private function promotionsInScope()
    {
        $scope = $this->user()->facultyScope('students.manage');

        return Promotion::query()->with('program.department')->where('is_active', true)
            ->when($scope !== null, fn ($q) => $q->whereHas('program.department', fn ($d) => $d->whereIn('faculty_id', $scope ?: [0])))->orderBy('code')->get();
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'matricule' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/\-.]{2,39}$/', Rule::unique('students')->ignore($student)],
            'last_name' => 'required|string|max:100', 'post_name' => 'nullable|string|max:100', 'first_name' => 'required|string|max:100',
            'gender' => 'nullable|in:M,F', 'birth_date' => 'nullable|date|before:today', 'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:150', 'address' => 'nullable|string|max:255',
        ]);
    }
}
