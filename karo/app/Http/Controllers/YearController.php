<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\ExamSession;
use App\Models\Semester;
use App\Services\AuditService;
use App\Services\ClosingService;
use Illuminate\Http\Request;

class YearController extends Controller
{
    public function __construct(private ClosingService $closing) {}

    public function index()
    {
        return view('years.index', ['years' => AcademicYear::query()->with(['previous', 'closer'])->withCount('sessions')->orderByDesc('starts_on')->get()]);
    }

    public function show(AcademicYear $year)
    {
        return view('years.show', ['year' => $year->load(['sessions', 'semesters', 'previous', 'closer']), 'check' => $year->status === 'open' ? $this->closing->checklist($year) : null]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['code' => 'required|string|max:20|unique:academic_years,code', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after:starts_on', 'previous_year_id' => 'nullable|exists:academic_years,id|unique:academic_years,previous_year_id']);
        $y = AcademicYear::create($d + ['status' => 'planned']);
        foreach ([1, 2] as $n) {
            Semester::create(['academic_year_id' => $y->id, 'number' => $n]);
        }
        AuditService::log('year.create', $y, "Année {$y->code} créée");

        return redirect()->route('years.show', $y)->with('success', 'Année créée (planifiée). Ajoutez ses sessions d’examens puis ouvrez-la.');
    }

    public function open(AcademicYear $year)
    {
        abort_unless($year->status === 'planned', 422);
        $year->update(['status' => 'open']);
        AuditService::log('year.open', $year, "Ouverture de l’année {$year->code}");

        return back()->with('success', 'Année ouverte aux écritures.');
    }

    public function makeCurrent(AcademicYear $year)
    {
        AcademicYear::query()->update(['is_current' => false]);
        $year->update(['is_current' => true]);
        AuditService::log('year.current', $year, "{$year->code} définie comme année courante");

        return back()->with('success', 'Année courante modifiée.');
    }

    public function storeSession(Request $request, AcademicYear $year)
    {
        $d = $request->validate(['semester' => 'required|in:1,2', 'type' => 'required|in:ordinary,resit', 'name' => 'required|string|max:120', 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on']);
        $exists = ExamSession::query()->where('academic_year_id', $year->id)->where('semester', $d['semester'])->where('type', $d['type'])->exists();
        if ($exists) {
            throw new BusinessException('Cette session existe déjà pour l’année.');
        }
        $s = ExamSession::create($d + ['academic_year_id' => $year->id, 'threshold_percent' => 100]);
        AuditService::log('session.create', $s, "Session « {$s->name} » créée");

        return back()->with('success', 'Session créée. Paramétrez ses conditions financières dans « Contrôle des examens ».');
    }

    public function close(Request $request, AcademicYear $year)
    {
        $d = $request->validate(['note' => 'required|string|max:1000', 'acknowledge' => 'nullable|boolean']);
        $stats = $this->closing->close($year, $request->user(), $d['note'], $request->boolean('acknowledge'));

        return back()->with('success', "Année clôturée : {$stats['arrears']} arriéré(s) et {$stats['credits']} crédit(s) reporté(s), liés à leurs opérations d’origine.");
    }

    public function reopen(Request $request, AcademicYear $year)
    {
        $this->closing->reopen($year, $request->user(), (string) $request->validate(['reason' => 'required|string|max:1000'])['reason']);

        return back()->with('warning', 'Année rouverte à titre exceptionnel : cette réouverture est inscrite au journal d’audit.');
    }
}
