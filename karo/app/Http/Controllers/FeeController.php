<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\BankAccount;
use App\Models\Charge;
use App\Models\Currency;
use App\Models\Cycle;
use App\Models\Faculty;
use App\Models\FeeCategory;
use App\Models\FeeSchedule;
use App\Models\Program;
use App\Models\Promotion;
use App\Services\AuditService;
use App\Services\FeeScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeController extends Controller
{
    public function __construct(private FeeScheduleService $svc) {}

    public function index(Request $request)
    {
        $year = $this->year($request);

        return view('fees.index', [
            'year' => $year, 'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'categories' => FeeCategory::query()->with('bankAccounts.bank')->orderBy('priority')->get(),
            'schedules' => FeeSchedule::query()->with(['category', 'faculty', 'program', 'promotion', 'cycle', 'creator', 'approver', 'installments'])
                ->where('academic_year_id', $year?->id)->orderBy('fee_category_id')->orderByDesc('version')->get(),
            'faculties' => Faculty::query()->orderBy('name')->get(), 'promotions' => Promotion::query()->with('program.department')->orderBy('code')->get(),
        ]);
    }

    public function createCategory()
    {
        return view('fees.category', ['category' => new FeeCategory(['is_active' => true, 'nature' => 'mandatory', 'trigger' => 'enrollment', 'priority' => 100]), 'accounts' => BankAccount::query()->with('bank')->get()]);
    }

    public function storeCategory(Request $request)
    {
        $cat = FeeCategory::create($this->validatedCategory($request));
        $cat->bankAccounts()->sync($request->input('accounts', []));
        AuditService::log('fee_category.create', $cat, "Frais « {$cat->label} » créé");

        return redirect()->route('fees.index')->with('success', 'Frais créé. Définissez ensuite ses barèmes.');
    }

    public function editCategory(FeeCategory $category)
    {
        return view('fees.category', ['category' => $category->load('bankAccounts'), 'accounts' => BankAccount::query()->with('bank')->get()]);
    }

    public function updateCategory(Request $request, FeeCategory $category)
    {
        $old = $category->toArray() + ['accounts' => $category->bankAccounts()->pluck('bank_accounts.id')->all()];
        $category->update($this->validatedCategory($request, $category));
        $category->bankAccounts()->sync($request->input('accounts', []));
        AuditService::log('fee_category.update', $category, "Frais « {$category->label} » modifié", $old, $category->fresh()->toArray() + ['accounts' => $request->input('accounts', [])]);

        return redirect()->route('fees.index')->with('success', 'Frais mis à jour (sans effet sur les créances déjà établies).');
    }

    public function createSchedule(Request $request)
    {
        $replaces = $request->integer('replaces') ? FeeSchedule::with('installments')->findOrFail($request->integer('replaces')) : null;

        return view('fees.schedule-form', [
            'replaces' => $replaces, 'years' => AcademicYear::query()->whereIn('status', ['planned', 'open'])->orderByDesc('starts_on')->get(),
            'categories' => FeeCategory::query()->where('is_active', true)->orderBy('priority')->get(), 'faculties' => Faculty::query()->orderBy('name')->get(),
            'programs' => Program::query()->with('department')->orderBy('name')->get(), 'promotions' => Promotion::query()->with('program.department')->orderBy('code')->get(),
            'cycles' => Cycle::all(), 'currencies' => Currency::query()->where('is_active', true)->pluck('code'),
        ]);
    }

    public function storeSchedule(Request $request)
    {
        $replaces = $request->integer('replaces_id') ? FeeSchedule::findOrFail($request->integer('replaces_id')) : null;
        $d = $request->validate([
            'fee_category_id' => [$replaces ? 'nullable' : 'required', 'exists:fee_categories,id'], 'academic_year_id' => [$replaces ? 'nullable' : 'required', 'exists:academic_years,id'],
            'faculty_id' => 'nullable|exists:faculties,id', 'program_id' => 'nullable|exists:programs,id', 'promotion_id' => 'nullable|exists:promotions,id', 'cycle_id' => 'nullable|exists:cycles,id',
            'student_status' => 'nullable|in:new,returning,finalist', 'currency' => 'required|exists:currencies,code', 'amount' => 'required|numeric|gt:0',
            'effective_from' => 'required|date', 'change_reason' => [$replaces ? 'required' : 'nullable', 'string', 'max:255'], 'apply_to_existing' => 'nullable|boolean',
            'inst' => 'required|array|min:1|max:12', 'inst.*.label' => 'nullable|string|max:80', 'inst.*.mode' => 'required|in:amount,percent',
            'inst.*.value' => 'required|numeric|gt:0', 'inst.*.due_date' => 'required|date',
        ]);
        $s = $this->svc->createDraft($d, array_values($d['inst']), $request->user(), $replaces);

        return redirect()->route('fees.schedules.show', $s)->with('success', 'Barème enregistré en brouillon : il doit être approuvé par une autre personne habilitée.');
    }

    public function showSchedule(FeeSchedule $schedule)
    {
        return view('fees.schedule-show', ['s' => $schedule->load(['category', 'academicYear', 'installments', 'creator', 'approver', 'faculty', 'program', 'promotion', 'cycle']),
            'versions' => FeeSchedule::query()->where('fee_category_id', $schedule->fee_category_id)->where('academic_year_id', $schedule->academic_year_id)
                ->where(fn ($q) => collect(['faculty_id', 'program_id', 'promotion_id', 'cycle_id', 'student_status'])->each(fn ($k) => $schedule->{$k} === null ? $q->whereNull($k) : $q->where($k, $schedule->{$k})))
                ->orderByDesc('version')->get(),
            'chargesCount' => Charge::query()->where('fee_schedule_id', $schedule->id)->count()]);
    }

    public function approve(Request $request, FeeSchedule $schedule)
    {
        $this->svc->approve($schedule, $request->user(), $request->input('note'));

        return back()->with('success', 'Barème approuvé et actif.');
    }

    public function reject(Request $request, FeeSchedule $schedule)
    {
        $this->svc->reject($schedule, $request->user(), (string) $request->validate(['note' => 'required|string|max:255'])['note']);

        return back()->with('success', 'Barème rejeté.');
    }

    private function validatedCategory(Request $request, ?FeeCategory $cat = null): array
    {
        $d = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('fee_categories')->ignore($cat)], 'label' => 'required|string|max:150',
            'nature' => 'required|in:mandatory,conditional', 'trigger' => 'required|in:enrollment,exam_session,manual',
            'applicable_statuses' => 'nullable|array', 'applicable_statuses.*' => 'in:new,returning,finalist',
            'session_semester' => 'nullable|required_if:trigger,exam_session|in:1,2', 'session_type' => 'nullable|required_if:trigger,exam_session|in:ordinary,resit',
            'valid_from' => 'nullable|date', 'valid_until' => 'nullable|date|after_or_equal:valid_from', 'priority' => 'required|integer|min:1|max:999',
            'accounts' => 'nullable|array', 'accounts.*' => 'exists:bank_accounts,id',
        ]);
        $d['code'] = strtoupper($d['code']);
        $d['applicable_statuses'] = $d['applicable_statuses'] ?? null ?: null;
        $d['final_promotions_only'] = $request->boolean('final_promotions_only');
        $d['is_active'] = $request->boolean('is_active');
        unset($d['accounts']);
        if ($d['trigger'] !== 'exam_session') {
            $d['session_semester'] = $d['session_type'] = null;
        }

        return $d;
    }
}
