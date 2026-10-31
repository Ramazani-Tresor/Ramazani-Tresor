<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\ExamSession;
use App\Models\ExamWaiver;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\ChargeService;
use App\Services\ExamClearanceService;
use App\Support\Filters;
use App\Support\Import\SpreadsheetReader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ExamController extends Controller
{
    public function __construct(private ExamClearanceService $svc) {}

    public function index(Request $request)
    {
        $year = $this->year($request);

        return view('exams.index', ['year' => $year, 'years' => AcademicYear::query()->orderByDesc('starts_on')->get(), 'sessions' => $year?->sessions ?? collect()]);
    }

    public function show(Request $request, ExamSession $session)
    {
        $filters = Filters::from($request->merge(['year' => $session->academic_year_id]), 'exams.view');
        $rows = $this->rows($session, $filters, $request->input('status'));

        return view('exams.show', ['session' => $session->load('academicYear', 'waivers.student'), 'rows' => $rows, 'filters' => $filters,
            'counts' => collect($this->svc->evaluate($session, $filters->facultyScope))->countBy('status'),
            'categories' => FeeCategory::query()->where('is_active', true)->orderBy('priority')->get()]);
    }

    public function export(Request $request, ExamSession $session, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $filters = Filters::from($request->merge(['year' => $session->academic_year_id]), 'exams.view');
        $rows = $this->rows($session, $filters, $request->input('status'));
        AuditService::log('exams.export', $session, "Export {$format} des listes de « {$session->name} »");
        if ($format === 'pdf') {
            return Pdf::loadView('exams.pdf', ['session' => $session, 'rows' => $rows])->setPaper('a4', 'landscape')->download('controle-'.$session->id.'.pdf');
        }
        $path = tempnam(sys_get_temp_dir(), 'exa');
        @unlink($path);
        $path .= '.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(["Contrôle financier — {$session->name} — {$session->academicYear->code} — conformité financière uniquement (ne vaut pas admissibilité académique)"]));
        $w->addRow(Row::fromValues(['Matricule', 'Nom', 'Promotion', 'Statut', 'Détail par devise', 'Dérogation']));
        foreach ($rows as $r) {
            $w->addRow(Row::fromValues(array_map([SpreadsheetReader::class, 'safeCell'], [$r['enrollment']->student->matricule, $r['enrollment']->student->fullName(), $r['enrollment']->promotion->code,
                status_label('clearance', $r['status']), collect($r['details'])->map(fn ($d, $c) => "{$c} : {$d['settled']} / {$d['required']} ({$d['ratio']} %)")->join(' ; '),
                $r['waiver'] ? $r['waiver']->reason.' — '.$r['waiver']->authority.' — jusqu’au '.$r['waiver']->valid_until->format('d/m/Y') : ''])));
        }
        $w->close();

        return response()->download($path, 'controle-'.$session->id.'.xlsx')->deleteFileAfterSend();
    }

    public function updateConditions(Request $request, ExamSession $session)
    {
        $d = $request->validate(['fee_category_ids' => 'nullable|array', 'fee_category_ids.*' => 'exists:fee_categories,id', 'threshold_percent' => 'required|numeric|min:0|max:100',
            'payment_deadline' => 'nullable|date', 'require_bank_confirmation' => 'nullable|boolean']);
        $old = $session->only(['fee_category_ids', 'threshold_percent', 'payment_deadline', 'require_bank_confirmation']);
        $session->update(['fee_category_ids' => array_map('intval', $d['fee_category_ids'] ?? []), 'threshold_percent' => $d['threshold_percent'],
            'payment_deadline' => $d['payment_deadline'] ?? null, 'require_bank_confirmation' => $request->boolean('require_bank_confirmation')]);
        AuditService::log('exams.conditions', $session, "Conditions financières de « {$session->name} » modifiées", $old, $session->only(array_keys($old)));

        return back()->with('success', 'Conditions enregistrées.');
    }

    public function generateCharges(Request $request, ExamSession $session, ChargeService $charges)
    {
        $d = $request->validate(['matricules' => 'nullable|string|max:20000']);
        $ids = null;
        if ($session->type === 'resit' || ! empty($d['matricules'])) {
            $list = array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($d['matricules'] ?? ''))));
            if (! $list) {
                throw new BusinessException('Pour un rattrapage, indiquez les matricules des étudiants concernés.');
            }
            $ids = Student::query()->whereIn('matricule', $list)->pluck('id')->all();
        }
        $r = $charges->generateForSession($session, $ids, $request->user());

        return back()->with('success', "Créances de session : {$r['created']} créée(s), {$r['existing']} déjà existante(s).".($r['missing'] ? ' Barème manquant : '.implode(', ', array_slice($r['missing'], 0, 5)) : ''));
    }

    public function storeWaiver(Request $request, ExamSession $session)
    {
        $d = $request->validate(['matricule' => 'required|exists:students,matricule', 'reason' => 'required|string|max:255', 'authority' => 'required|string|max:150', 'granted_on' => 'required|date', 'valid_until' => 'required|date|after_or_equal:granted_on']);
        $student = Student::query()->where('matricule', $d['matricule'])->firstOrFail();
        $this->authorizeStudent($student, 'exams.waive');
        $w = ExamWaiver::create(['student_id' => $student->id, 'exam_session_id' => $session->id, 'reason' => $d['reason'], 'authority' => $d['authority'], 'granted_on' => $d['granted_on'], 'valid_until' => $d['valid_until'], 'status' => 'active', 'recorded_by' => $request->user()->id]);
        AuditService::log('exams.waiver', $w, "Dérogation pour {$student->matricule} ({$session->name}) accordée par {$d['authority']}", null, $d, $d['reason']);

        return back()->with('success', 'Dérogation enregistrée.');
    }

    public function revokeWaiver(Request $request, ExamWaiver $waiver)
    {
        $waiver->update(['status' => 'revoked']);
        AuditService::log('exams.waiver_revoke', $waiver, 'Révocation d’une dérogation', null, null, $request->input('reason'));

        return back()->with('success', 'Dérogation révoquée.');
    }

    private function rows(ExamSession $session, Filters $filters, ?string $status): array
    {
        $rows = $this->svc->evaluate($session, $filters->facultyScope, $filters->get('promotion'));
        if ($filters->get('faculty')) {
            $rows = array_filter($rows, fn ($r) => $r['enrollment']->promotion->program->department->faculty_id === (int) $filters->get('faculty'));
        }

        return array_values($status ? array_filter($rows, fn ($r) => $r['status'] === $status) : $rows);
    }
}
