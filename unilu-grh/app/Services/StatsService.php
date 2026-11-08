<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AdministrativeSituation;
use App\Models\CareerEvent;
use App\Models\Death;
use App\Models\Employee;
use App\Models\Mutation;
use App\Models\OrgUnit;
use App\Models\PrimeEntitlement;
use App\Models\Qualification;
use App\Models\SocialSupport;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Indicateurs. Définitions :
 *  - Effectif : agents dont le statut n'est pas une fin de fonctions (décès, mutation externe, retraite, démission, départ).
 *  - Faculté / service : unité de l'affectation principale active (et ses sous-unités).
 *  - Docteur : agent ayant une qualification « grade de docteur » ou « thèse de doctorat ».
 *  - Mouvements (promotions, mutations, départs, décès, suspensions) : décisions validées dont la date d'effet tombe dans l'année académique.
 *  - Montants : par devise, jamais additionnés entre devises ; primes prévues = Σ droits nets des périodes commençant dans l'année.
 */
class StatsService
{
    public function __construct(private PrimeService $primes) {}

    public function employeeBase(User $user, array $f): Builder
    {
        $q = Employee::query();
        $user->scopeEmployees($q, 'employees.view');
        if (! empty($f['unit'])) {
            $q->inUnit((int) $f['unit']);
        }
        if (! empty($f['family'])) {
            $q->where('staff_family', $f['family']);
        }
        if (! empty($f['category'])) {
            $q->where('staff_category_id', (int) $f['category']);
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        } else {
            $q->whereNotIn('status', Employee::ENDED_STATUSES);
        }

        return $q;
    }

    public function dashboard(User $user, array $f): array
    {
        $year = ! empty($f['year']) ? AcademicYear::query()->find($f['year']) : AcademicYear::current();
        [$from, $to] = $year ? [$year->starts_on, $year->ends_on] : [now()->startOfYear(), now()->endOfYear()];
        $employees = $this->employeeBase($user, $f)->with(['grade', 'category', 'activeAssignment.orgUnit.parent.parent'])->get();
        $ids = $employees->pluck('id');
        // Population de référence pour les mouvements : agents du périmètre, y compris ceux qui ont quitté.
        $all = Employee::query();
        $user->scopeEmployees($all, 'employees.view');
        if (! empty($f['unit'])) {
            $all->where(fn ($q) => $q->inUnit((int) $f['unit'])->orWhereHas('assignments', fn ($a) => $a->whereIn('org_unit_id', OrgUnit::subtreeIds((int) $f['unit']))));
        }
        foreach (['family' => 'staff_family', 'category' => 'staff_category_id'] as $k => $col) {
            if (! empty($f[$k])) {
                $all->where($col, $f[$k]);
            }
        }
        $allIds = $all->pluck('id');

        $byFaculty = [];
        $byService = [];
        foreach ($employees as $e) {
            $unit = $e->activeAssignment?->orgUnit;
            $fac = $unit?->faculty();
            if ($fac) {
                $byFaculty[$fac->name] = ($byFaculty[$fac->name] ?? 0) + 1;
            } elseif ($unit) {
                $byService[$unit->name] = ($byService[$unit->name] ?? 0) + 1;
            } else {
                $byService['(sans affectation)'] = ($byService['(sans affectation)'] ?? 0) + 1;
            }
        }
        arsort($byFaculty);
        arsort($byService);
        $quals = Qualification::query()->whereIn('employee_id', $allIds);
        $doctors = Qualification::query()->whereIn('employee_id', $ids)->whereIn('nature', ['doctorate', 'doctoral_thesis'])->distinct()->count('employee_id');

        $out = [
            'year' => $year,
            'total' => $employees->count(),
            'by_family' => $employees->groupBy('staff_family')->map->count()->all(),
            'by_grade' => $employees->groupBy(fn ($e) => $e->grade?->name ?? '(sans grade)')->map->count()->sortDesc()->all(),
            'by_category' => $employees->groupBy(fn ($e) => $e->category?->name ?? '(sans catégorie)')->map->count()->sortDesc()->all(),
            'by_status' => $employees->groupBy('status')->map->count()->all(),
            'by_faculty' => $byFaculty,
            'by_service' => $byService,
            'doctors' => $doctors,
            'dea' => (clone $quals)->where('nature', 'dea')->count(),
            'theses_by_year' => $this->thesesByYear($allIds),
            'promotions' => CareerEvent::query()->whereIn('employee_id', $allIds)->where('type', 'promotion')->where('status', 'validated')->whereBetween('effective_date', [$from, $to])->count(),
            'mutations' => Mutation::query()->whereIn('employee_id', $allIds)->where('status', 'validated')->whereBetween('effective_date', [$from, $to])->count(),
            'departures' => Employee::query()->whereIn('id', $allIds)->whereIn('status', ['transferred_out', 'retired', 'resigned', 'departed'])->whereBetween('status_since', [$from, $to])->count(),
            'deaths' => Death::query()->whereIn('employee_id', $allIds)->where('status', 'validated')->whereBetween('death_date', [$from, $to])->count(),
            'suspensions' => AdministrativeSituation::query()->whereIn('employee_id', $allIds)->whereIn('status', ['approved', 'closed'])
                ->whereHas('type', fn ($t) => $t->where('kind', 'suspension'))->whereDate('start_date', '<=', $to)
                ->where(fn ($q) => $q->whereNull('actual_end_date')->orWhereDate('actual_end_date', '>=', $from))->count(),
            'on_leave' => Employee::query()->whereIn('id', $allIds)->where('status', 'on_leave')->count(),
            'finance' => null,
        ];
        if ($user->hasPermission('reports.finance') || $user->hasGlobalPermission('primes.view')) {
            $out['finance'] = $this->financeTotals($allIds->all(), $from, $to);
        }

        return $out;
    }

    public function thesesByYear($employeeIds): array
    {
        return Qualification::query()->with('academicYear')->whereIn('employee_id', $employeeIds)->where('nature', 'doctoral_thesis')->get()
            ->groupBy(fn ($q) => $q->academicYear?->code ?? ($q->defense_date?->format('Y') ?? 'Non précisée'))->map->count()->sortKeys()->all();
    }

    /** @return array<string, array{planned:string, paid:string, advances:string, arrears:string, overpaid:string, support:string}> */
    public function financeTotals(array $employeeIds, $from, $to): array
    {
        $tot = [];
        $asOf = now();
        foreach (PrimeEntitlement::query()->with(['adjustments', 'payments'])->whereIn('employee_id', $employeeIds)->whereBetween('period_start', [$from, $to])->get() as $ent) {
            $c = $ent->currency;
            $tot[$c] ??= ['planned' => '0.00', 'paid' => '0.00', 'advances' => '0.00', 'arrears' => '0.00', 'overpaid' => '0.00', 'support' => '0.00'];
            $bal = $ent->balance();
            $tot[$c]['planned'] = Money::sum([$tot[$c]['planned'], $ent->netAmount()]);
            $tot[$c]['paid'] = Money::sum([$tot[$c]['paid'], $ent->paidAmount()]);
            $tot[$c]['advances'] = Money::sum([$tot[$c]['advances'], $ent->advancesAmount()]);
            if (Money::isPositive($bal) && $ent->period_end->lt($asOf)) {
                $tot[$c]['arrears'] = Money::sum([$tot[$c]['arrears'], $bal]);
            }
            if (Money::isNegative($bal)) {
                $tot[$c]['overpaid'] = Money::sum([$tot[$c]['overpaid'], Money::of($bal)->negated()]);
            }
        }
        foreach (SocialSupport::query()->whereIn('employee_id', $employeeIds)->whereIn('status', ['approved', 'paid'])->whereBetween('approved_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])->get() as $s) {
            $tot[$s->currency] ??= ['planned' => '0.00', 'paid' => '0.00', 'advances' => '0.00', 'arrears' => '0.00', 'overpaid' => '0.00', 'support' => '0.00'];
            $tot[$s->currency]['support'] = Money::sum([$tot[$s->currency]['support'], $s->amount_granted]);
        }
        ksort($tot);

        return $tot;
    }

    /** Statistiques scientifiques obligatoires (section 6). */
    public function scientific(User $user, array $f): array
    {
        $all = Employee::query();
        $user->scopeEmployees($all, 'employees.view');
        if (! empty($f['family'])) {
            $all->where('staff_family', $f['family']);
        }
        $ids = $all->pluck('id');
        $q = Qualification::query()->with(['academicYear', 'orgUnit.parent', 'employee.activeAssignment.orgUnit.parent'])->whereIn('employee_id', $ids);
        if (! empty($f['year'])) {
            $q->where('academic_year_id', $f['year']);
        }
        if (! empty($f['unit'])) {
            $q->whereIn('org_unit_id', OrgUnit::subtreeIds((int) $f['unit']));
        }
        $items = $q->get();
        $unitOf = fn ($qual) => $qual->orgUnit ?? $qual->employee?->activeAssignment?->orgUnit;
        $theses = $items->where('nature', 'doctoral_thesis');
        $doctorIds = $items->whereIn('nature', ['doctorate', 'doctoral_thesis'])->pluck('employee_id')->unique();
        $promotionCandidates = CareerEvent::query()->whereIn('employee_id', $ids)->where('type', 'promotion')->where('status', 'draft')->pluck('employee_id')->unique();

        return [
            'total_dea' => $items->where('nature', 'dea')->count(),
            'total_research_theses' => $items->where('nature', 'research_thesis')->count(),
            'total_dea_theses' => $items->where('nature', 'dea_thesis')->count(),
            'total_doctors' => $doctorIds->count(),
            'total_theses' => $theses->count(),
            'theses_by_year' => $theses->groupBy(fn ($t) => $t->academicYear?->code ?? 'Non précisée')->map->count()->sortKeys()->all(),
            'dea_by_year' => $items->where('nature', 'dea')->groupBy(fn ($t) => $t->academicYear?->code ?? 'Non précisée')->map->count()->sortKeys()->all(),
            'theses_by_faculty' => $theses->groupBy(fn ($t) => $unitOf($t)?->faculty()?->name ?? 'Hors faculté / non précisée')->map->count()->sortDesc()->all(),
            'theses_by_department' => $theses->groupBy(fn ($t) => ($u = $unitOf($t)) && $u->type === 'department' ? $u->name : 'Département non précisé')->map->count()->sortDesc()->all(),
            'doctors_scientific' => Employee::query()->whereIn('id', $doctorIds)->where('staff_family', 'scientific')->count(),
            'doctors_promotion_candidates' => $promotionCandidates->intersect($doctorIds)->count(),
            'promotion_candidates' => $promotionCandidates->count(),
            'items' => $items,
        ];
    }
}
