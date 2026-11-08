<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\CareerEvent;
use App\Models\Employee;
use App\Models\Mutation;
use App\Models\OrgUnit;
use App\Models\PrimeEntitlement;
use App\Models\PrimePayment;
use App\Models\SocialSupport;
use App\Models\User;
use App\Support\Labels;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Éditions officielles : un même jeu de données (titre, colonnes, lignes) sert au PDF, à l'Excel et à l'aperçu.
 * Le périmètre de l'utilisateur est appliqué ; les états financiers exigent « reports.finance ».
 */
class ReportService
{
    /** type => [libellé, permission, groupe] */
    public const TYPES = [
        'personnel' => ['Liste générale du personnel', 'reports.view', 'Personnel'],
        'personnel_grade' => ['Liste du personnel par grade', 'reports.view', 'Personnel'],
        'personnel_faculty' => ['Liste du personnel par faculté / service', 'reports.view', 'Personnel'],
        'promotions' => ['État des promotions et nominations', 'reports.view', 'Carrière'],
        'assignments' => ['État des affectations', 'reports.view', 'Carrière'],
        'mutations' => ['État des mutations', 'reports.view', 'Carrière'],
        'departures' => ['État des départs et décès', 'reports.view', 'Carrière'],
        'scientific' => ['Statistiques scientifiques (diplômes et thèses)', 'reports.view', 'Carrière'],
        'primes' => ['État des primes', 'reports.finance', 'Finances'],
        'advances' => ['État des acomptes', 'reports.finance', 'Finances'],
        'arrears' => ['État des arriérés', 'reports.finance', 'Finances'],
        'social' => ['État des soutiens sociaux', 'reports.finance', 'Finances'],
        'assets' => ['Inventaire du patrimoine', 'assets.view', 'Patrimoine'],
        'annual' => ['Rapport annuel (indicateurs)', 'dashboard.view', 'Pilotage'],
    ];

    public function __construct(private StatsService $stats) {}

    public function build(string $type, User $user, array $f): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$title, $perm] = self::TYPES[$type];
        abort_unless($user->hasPermission($perm), 403);
        $year = ! empty($f['year']) ? AcademicYear::query()->find($f['year']) : AcademicYear::current();
        [$from, $to] = $year ? [$year->starts_on, $year->ends_on] : [now()->startOfYear(), now()->endOfYear()];
        $scope = function ($q) use ($user, $f) {
            $user->scopeEmployees($q, 'employees.view');
            if (! empty($f['unit'])) {
                $q->inUnit((int) $f['unit']);
            }
            if (! empty($f['family'])) {
                $q->where('staff_family', $f['family']);
            }

            return $q;
        };
        $empIds = fn () => $scope(Employee::query())->pluck('id');
        $period = $year ? 'Année académique '.$year->code : 'Année '.now()->year;
        $name = fn ($e) => $e?->fullName() ?? '—';
        $d = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : '';

        $r = match ($type) {
            'personnel', 'personnel_grade', 'personnel_faculty' => (function () use ($scope, $f, $type, $name, $d) {
                $q = $scope(Employee::query()->with(['grade', 'category', 'activeAssignment.orgUnit.parent', 'jobFunction']));
                if (empty($f['status'])) {
                    $q->whereNotIn('status', Employee::ENDED_STATUSES);
                } else {
                    $q->where('status', $f['status']);
                }
                $list = $q->orderBy('last_name')->get();
                $group = match ($type) {
                    'personnel_grade' => fn ($e) => $e->grade?->name ?? '(sans grade)',
                    'personnel_faculty' => fn ($e) => $e->activeAssignment?->orgUnit?->faculty()?->name ?? $e->activeAssignment?->orgUnit?->name ?? '(sans affectation)',
                    default => null,
                };
                if ($group) {
                    $list = $list->sortBy(fn ($e) => $group($e).'|'.$e->last_name)->values();
                }

                return [
                    'columns' => ['N°', 'Matricule', 'Nom, postnom, prénom', 'Sexe', 'Grade', 'Catégorie', 'Fonction', 'Affectation', 'Engagement', 'Situation'],
                    'rows' => $list->values()->map(fn ($e, $i) => [$i + 1, $e->matricule, $name($e), $e->sex, $e->grade?->name, $e->category?->name, $e->jobFunction?->name, $e->activeAssignment?->orgUnit?->name, $d($e->engagement_date), $e->statusLabel()])->all(),
                    'groups' => $group ? $list->values()->map($group)->all() : null,
                ];
            })(),
            'promotions' => ['columns' => ['Date d’effet', 'Matricule', 'Agent', 'Type', 'Ancien grade', 'Nouveau grade / mandat', 'Décision', 'Date décision', 'Autorité', 'Statut'],
                'rows' => CareerEvent::query()->with(['employee', 'oldGrade', 'newGrade', 'newMandate', 'newCategory'])->whereIn('employee_id', $empIds())->where('type', '!=', 'engagement')->whereBetween('effective_date', [$from, $to])->orderBy('effective_date')->get()
                    ->map(fn ($ev) => [$d($ev->effective_date), $ev->employee->matricule, $name($ev->employee), Labels::status('career_type', $ev->type), $ev->oldGrade?->name, $ev->newGrade?->name ?? $ev->newMandate?->name ?? $ev->newCategory?->name, $ev->decision_number, $d($ev->decision_date), $ev->signing_authority, Labels::status('decision', $ev->status)])->all()],
            'assignments' => ['columns' => ['Matricule', 'Agent', 'Structure d’origine', 'Structure d’accueil', 'Poste', 'Début', 'Fin', 'Motif', 'Décision', 'Statut'],
                'rows' => Assignment::query()->with(['employee', 'fromUnit', 'orgUnit', 'position'])->whereIn('employee_id', $empIds())->where(fn ($q) => $q->where('status', 'active')->orWhereBetween('start_date', [$from, $to]))->orderBy('start_date')->get()
                    ->map(fn ($a) => [$a->employee->matricule, $name($a->employee), $a->fromUnit?->name, $a->orgUnit?->name, $a->position?->title, $d($a->start_date), $d($a->end_date), $a->reason, $a->decision_number, Labels::status('assignment', $a->status)])->all()],
            'mutations' => ['columns' => ['Date d’effet', 'Matricule', 'Agent', 'Nature', 'Origine', 'Accueil', 'Motif', 'Décision', 'Statut'],
                'rows' => Mutation::query()->with(['employee', 'fromUnit', 'toUnit'])->whereIn('employee_id', $empIds())->whereBetween('effective_date', [$from, $to])->orderBy('effective_date')->get()
                    ->map(fn ($m) => [$d($m->effective_date), $m->employee->matricule, $name($m->employee), Labels::status('mutation_kind', $m->kind), $m->fromUnit?->name ?? $m->origin_institution, $m->kind === 'internal' ? $m->toUnit?->name : $m->destination_institution, $m->reason, $m->decision_reference, Labels::status('decision', $m->status)])->all()],
            'departures' => ['columns' => ['Date d’effet', 'Matricule', 'Agent', 'Grade', 'Motif du départ', 'Statut actuel'],
                'rows' => Employee::query()->with('grade')->whereIn('id', $empIds())->whereIn('status', Employee::ENDED_STATUSES)->whereBetween('status_since', [$from, $to])->orderBy('status_since')->get()
                    ->map(fn ($e) => [$d($e->status_since), $e->matricule, $name($e), $e->grade?->name, $e->statusLabel(), $e->statusLabel()])->all()],
            'scientific' => (function () use ($user, $f, $d, $name) {
                $s = $this->stats->scientific($user, $f);

                return ['columns' => ['Nature', 'Agent', 'Intitulé', 'Domaine', 'Université de soutenance', 'Date', 'Année académique', 'Mention', 'Directeur'],
                    'rows' => $s['items']->sortBy('nature')->map(fn ($q) => [Labels::status('qualification', $q->nature), $name($q->employee), $q->title, $q->field, $q->defense_university, $d($q->defense_date), $q->academicYear?->code, $q->mention, $q->supervisor])->values()->all(),
                    'summary' => ['Total DEA' => $s['total_dea'], 'Mémoires de recherche' => $s['total_research_theses'], 'Thèses soutenues' => $s['total_theses'], 'Docteurs' => $s['total_doctors'], 'Docteurs parmi le personnel scientifique' => $s['doctors_scientific'], 'Docteurs parmi les candidats à la promotion' => $s['doctors_promotion_candidates']]];
            })(),
            'primes', 'arrears' => (function () use ($empIds, $from, $to, $type, $name, $f) {
                $q = PrimeEntitlement::query()->with(['employee', 'primeType', 'adjustments', 'payments'])->whereIn('employee_id', $empIds())->whereBetween('period_start', [$from, $to]);
                if (! empty($f['period'])) {
                    $q->where('period_label', $f['period']);
                }
                $items = $q->orderBy('period_start')->get();
                if ($type === 'arrears') {
                    $items = $items->filter(fn ($e) => Money::isPositive($e->balance()) && $e->period_end->lt(now()));
                }
                $totals = [];
                foreach ($items as $e) {
                    $t = &$totals[$e->currency];
                    $t = [Money::sum([$t[0] ?? '0', $e->netAmount()]), Money::sum([$t[1] ?? '0', $e->paidAmount()]), Money::sum([$t[2] ?? '0', $e->balance()])];
                    unset($t);
                }

                return ['columns' => ['Période', 'Matricule', 'Agent', 'Prime', 'Montant prévu', 'Payé', 'Acomptes', 'Solde', 'Devise', 'Statut'],
                    'rows' => $items->map(fn ($e) => [$e->period_label, $e->employee->matricule, $name($e->employee), $e->primeType?->name, Money::format($e->netAmount()), Money::format($e->paidAmount()), Money::format($e->advancesAmount()), Money::format($e->balance()), $e->currency, Labels::status('entitlement', $e->status)])->values()->all(),
                    'summary' => collect($totals)->mapWithKeys(fn ($t, $c) => ["Total {$c}" => 'prévu '.Money::format($t[0], $c).' · payé '.Money::format($t[1], $c).' · solde '.Money::format($t[2], $c)])->all()];
            })(),
            'advances' => ['columns' => ['N°', 'Date', 'Matricule', 'Agent', 'Prime / période', 'Montant', 'Devise', 'Statut', 'Solde restant du droit', 'Régularisé'],
                'rows' => PrimePayment::query()->with(['employee', 'entitlement.primeType', 'entitlement.adjustments', 'entitlement.payments'])->whereIn('employee_id', $empIds())->where('kind', 'advance')->whereBetween('payment_date', [$from, $to])->orderBy('payment_date')->get()
                    ->map(fn ($p) => [$p->number, $p->payment_date->format('d/m/Y'), $p->employee->matricule, $name($p->employee), $p->entitlement->primeType?->name.' — '.$p->entitlement->period_label, Money::format($p->amount), $p->currency, Labels::status('payment', $p->status), Money::format($p->entitlement->balance()), Money::isPositive($p->entitlement->balance()) ? 'Non' : 'Oui'])->all()],
            'social' => ['columns' => ['N°', 'Date', 'Matricule', 'Agent', 'Type', 'Personne décédée / objet', 'Parenté', 'Montant accordé', 'Devise', 'Décision', 'Statut'],
                'rows' => SocialSupport::query()->with(['employee', 'type', 'kinship'])->whereIn('employee_id', $empIds())->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])->orderBy('created_at')->get()
                    ->map(fn ($s) => [$s->number, $s->created_at->format('d/m/Y'), $s->employee->matricule, $name($s->employee), $s->type->name, $s->deceased_name ?? $s->description, $s->kinship?->name, $s->amount_granted !== null ? Money::format($s->amount_granted) : '', $s->currency, $s->decision_reference, Labels::status('support', $s->status)])->all()],
            'assets' => (function () use ($f, $d) {
                $q = Asset::query()->with(['category', 'orgUnit', 'responsible', 'parent']);
                if (! empty($f['unit'])) {
                    $q->whereIn('org_unit_id', OrgUnit::subtreeIds((int) $f['unit']));
                }
                if (empty($f['status'])) {
                    $q->where('status', 'in_service');
                }

                return ['columns' => ['Code', 'Catégorie', 'Désignation', 'Qté', 'Localisation', 'Affectation', 'Responsable', 'Acquisition', 'Valeur', 'Devise', 'État', 'Statut'],
                    'rows' => $q->orderBy('code')->get()->map(fn ($a) => [$a->code, $a->category->name, $a->designation, $a->quantity, $a->location ?: $a->parent?->designation, $a->orgUnit?->name, $a->responsible?->fullName(), $d($a->acquisition_date), $a->value !== null ? Money::format($a->value) : '', $a->currency, Labels::status('asset_condition', $a->condition), Labels::status('asset_status', $a->status)])->all()];
            })(),
            'annual' => (function () use ($user, $f) {
                $s = $this->stats->dashboard($user, $f);
                $rows = [['Effectif total', $s['total']]];
                foreach ($s['by_family'] as $k => $v) {
                    $rows[] = ['Effectif — '.Labels::status('family', $k), $v];
                }
                foreach ([['Docteurs', 'doctors'], ['DEA', 'dea'], ['Promotions', 'promotions'], ['Mutations', 'mutations'], ['Départs', 'departures'], ['Décès', 'deaths'], ['Suspensions', 'suspensions'], ['Agents en congé', 'on_leave']] as [$l, $k]) {
                    $rows[] = [$l, $s[$k]];
                }
                foreach ($s['by_grade'] as $k => $v) {
                    $rows[] = ['Effectif par grade — '.$k, $v];
                }
                foreach ($s['by_faculty'] + $s['by_service'] as $k => $v) {
                    $rows[] = ['Effectif — '.$k, $v];
                }
                foreach ($s['theses_by_year'] as $k => $v) {
                    $rows[] = ['Thèses soutenues — '.$k, $v];
                }
                foreach ($s['finance'] ?? [] as $c => $t) {
                    foreach (['planned' => 'Primes prévues', 'paid' => 'Primes payées', 'advances' => 'Acomptes', 'arrears' => 'Arriérés', 'support' => 'Soutiens sociaux'] as $k => $l) {
                        $rows[] = ["{$l} ({$c})", Money::format($t[$k], $c)];
                    }
                }

                return ['columns' => ['Indicateur', 'Valeur'], 'rows' => $rows];
            })(),
        };

        AuditService::log('reports.build', null, "Édition « {$title} » ({$period})");

        return $r + ['title' => $title, 'period' => $period, 'type' => $type, 'reference' => 'EDT-'.strtoupper($type).'-'.now()->format('YmdHis'), 'generated_at' => now(), 'groups' => $r['groups'] ?? null, 'summary' => $r['summary'] ?? []];
    }
}
