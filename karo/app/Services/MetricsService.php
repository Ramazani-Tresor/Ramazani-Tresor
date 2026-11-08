<?php

namespace App\Services;

use App\Models\PaymentContract;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs financiers, TOUJOURS par devise (aucune addition entre devises).
 * Filtres : année, faculté, filière, promotion, frais, banque/compte, période (date bancaire), facultés autorisées.
 * Une affectation, un rapprochement ou une synchronisation n'est jamais compté comme un nouveau paiement :
 * les montants « payés » proviennent uniquement de la table des paiements (type versement bancaire).
 */
class MetricsService
{
    public const DEFINITIONS = [
        'expected' => ['Montant attendu', 'Σ montants des créances actives − ajustements approuvés (bourses, réductions, exonérations, révisions).', 'Créances, ajustements'],
        'exigible' => ['Montant exigible', 'Σ par créance de min(net, tranches échues à ce jour).', 'Tranches des créances'],
        'declared' => ['Paiements déclarés', 'Σ versements saisis non rejetés ni contrepassés (soumis, contrôlés, validés), selon la date bancaire.', 'Paiements'],
        'validated' => ['Paiements validés', 'Σ versements au statut « validé » (validation interne).', 'Paiements'],
        'confirmed' => ['Confirmés par la banque', 'Σ montants rapprochés avec une ligne de relevé, pour les paiements validés.', 'Rapprochements'],
        'allocated' => ['Montants affectés', 'Σ affectations effectives (paiements validés) aux créances, dans la devise des créances.', 'Affectations'],
        'unallocated' => ['Non affectés (crédits)', 'Σ disponible des paiements validés : montant − affecté − remboursé/reporté/réservé.', 'Paiements, opérations sur excédent'],
        'balance_due' => ['Soldes dus', 'Σ par créance de max(0, net − affecté).', 'Créances, affectations'],
        'recovery_rate' => ['Taux de recouvrement', 'Montants affectés ÷ montant attendu × 100.', 'Calcul'],
        'overdue' => ['Échéances en retard', 'Σ par créance de max(0, tranches échues − affecté) ; nombre de créances concernées.', 'Tranches, affectations'],
        'pending' => ['En attente de validation', 'Σ et nombre de versements soumis ou contrôlés.', 'Paiements'],
        'unmatched' => ['Lignes de relevé non rapprochées', 'Σ reste non rapproché des lignes de relevé (hors lignes écartées).', 'Relevés'],
    ];

    public function compute(array $f): array
    {
        $today = now()->toDateString();
        $charges = DB::table('charges as c')->where('c.status', 'active')
            ->join('fee_categories as fc', 'fc.id', '=', 'c.fee_category_id')
            ->leftJoin('enrollments as e', 'e.id', '=', 'c.enrollment_id')
            ->leftJoin('promotions as pr', 'pr.id', '=', 'e.promotion_id')
            ->leftJoin('programs as pg', 'pg.id', '=', 'pr.program_id')
            ->leftJoin('departments as d', 'd.id', '=', 'pg.department_id');
        $this->scope($charges, $f, 'c');
        $adj = DB::table('charge_adjustments')->where('status', 'approved')->groupBy('charge_id')->selectRaw('charge_id, SUM(amount) as a');
        $alloc = DB::table('payment_allocations as pa')->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->where('pa.status', 'active')->where('p.status', 'validated')->groupBy('pa.charge_id')->selectRaw('pa.charge_id, SUM(pa.charge_amount) as s');
        $due = DB::table('charge_installments')->where('due_date', '<=', $today)->groupBy('charge_id')->selectRaw('charge_id, SUM(amount) as d');
        $perCharge = (clone $charges)
            ->leftJoinSub($adj, 'adj', 'adj.charge_id', '=', 'c.id')
            ->leftJoinSub($alloc, 'al', 'al.charge_id', '=', 'c.id')
            ->leftJoinSub($due, 'du', 'du.charge_id', '=', 'c.id')
            ->selectRaw('c.currency, c.amount - COALESCE(adj.a, 0) as net, COALESCE(al.s, 0) as settled, COALESCE(du.d, 0) as due_sum');
        $rows = DB::query()->fromSub($perCharge, 'x')->groupBy('currency')->selectRaw('currency,
            SUM(net) as expected, SUM(settled) as allocated,
            SUM(CASE WHEN net - settled > 0 THEN net - settled ELSE 0 END) as balance_due,
            SUM(CASE WHEN due_sum < net THEN due_sum ELSE net END) as exigible,
            SUM(CASE WHEN (CASE WHEN due_sum < net THEN due_sum ELSE net END) - settled > 0 THEN (CASE WHEN due_sum < net THEN due_sum ELSE net END) - settled ELSE 0 END) as overdue_amount,
            SUM(CASE WHEN (CASE WHEN due_sum < net THEN due_sum ELSE net END) - settled > 0 THEN 1 ELSE 0 END) as overdue_count,
            COUNT(*) as charges_count')->get()->keyBy('currency');

        $payments = DB::table('payments as p')->where('p.type', 'bank_deposit')
            ->join('students as s', 's.id', '=', 'p.student_id')
            ->leftJoin('enrollments as e', fn ($j) => $j->on('e.student_id', '=', 'p.student_id')->on('e.academic_year_id', '=', 'p.academic_year_id'))
            ->leftJoin('promotions as pr', 'pr.id', '=', 'e.promotion_id')
            ->leftJoin('programs as pg', 'pg.id', '=', 'pr.program_id')
            ->leftJoin('departments as d', 'd.id', '=', 'pg.department_id');
        $this->scope($payments, $f, 'p');
        if (! empty($f['bank_account_id'])) {
            $payments->where('p.bank_account_id', $f['bank_account_id']);
        }
        if (! empty($f['from'])) {
            $payments->whereBetween('p.deposit_date', [$f['from'], $f['to']]);
        }
        $pay = (clone $payments)->groupBy('p.currency')->selectRaw("p.currency as currency,
            SUM(CASE WHEN p.status IN ('submitted','checked','validated') THEN p.amount ELSE 0 END) as declared,
            SUM(CASE WHEN p.status = 'validated' THEN p.amount ELSE 0 END) as validated,
            SUM(CASE WHEN p.status = 'validated' THEN p.bank_confirmed_amount ELSE 0 END) as confirmed,
            SUM(CASE WHEN p.status IN ('submitted','checked') THEN p.amount ELSE 0 END) as pending,
            SUM(CASE WHEN p.status IN ('submitted','checked') THEN 1 ELSE 0 END) as pending_count")->get()->keyBy('currency');

        // Crédits non affectés (paiements validés de tout type, y compris reports reçus)
        $used = DB::table('payment_allocations')->whereIn('status', ['proposed', 'active'])->groupBy('payment_id')->selectRaw('payment_id, SUM(amount) as u');
        $ops = DB::table('credit_operations')->where(fn ($q) => $q->whereIn('type', ['refund', 'carry_forward'])->whereIn('status', ['pending', 'executed'])
            ->orWhere(fn ($w) => $w->where('type', 'compensation')->where('status', 'pending')))->groupBy('source_payment_id')->selectRaw('source_payment_id, SUM(amount) as o');
        $creditQ = DB::table('payments as p')->where('p.status', 'validated')
            ->leftJoin('enrollments as e', fn ($j) => $j->on('e.student_id', '=', 'p.student_id')->on('e.academic_year_id', '=', 'p.academic_year_id'))
            ->leftJoin('promotions as pr', 'pr.id', '=', 'e.promotion_id')->leftJoin('programs as pg', 'pg.id', '=', 'pr.program_id')
            ->leftJoin('departments as d', 'd.id', '=', 'pg.department_id')
            ->leftJoinSub($used, 'u', 'u.payment_id', '=', 'p.id')->leftJoinSub($ops, 'o', 'o.source_payment_id', '=', 'p.id');
        $this->scope($creditQ, $f, 'p');
        $credits = $creditQ->groupBy('p.currency')->selectRaw('p.currency as currency, SUM(p.amount - COALESCE(u.u, 0) - COALESCE(o.o, 0)) as unallocated')->get()->keyBy('currency');

        $lines = DB::table('statement_lines')->where('match_status', '!=', 'ignored')->whereIn('match_status', ['unmatched', 'partial'])
            ->when($f['bank_account_id'] ?? null, fn ($q, $v) => $q->where('bank_account_id', $v))
            ->groupBy('currency')->selectRaw('currency, SUM(amount - matched_amount) as unmatched, COUNT(*) as unmatched_count')->get()->keyBy('currency');

        $currencies = collect([$rows->keys(), $pay->keys(), $credits->keys(), $lines->keys()])->flatten()->unique()->filter(fn ($c) => empty($f['currency']) || $c === $f['currency'])->sort()->values();
        $out = [];
        foreach ($currencies as $cur) {
            $r = $rows->get($cur);
            $p = $pay->get($cur);
            $m = [
                'expected' => $r->expected ?? 0, 'exigible' => $r->exigible ?? 0, 'allocated' => $r->allocated ?? 0, 'balance_due' => $r->balance_due ?? 0,
                'overdue' => $r->overdue_amount ?? 0, 'declared' => $p->declared ?? 0, 'validated' => $p->validated ?? 0, 'confirmed' => $p->confirmed ?? 0,
                'pending' => $p->pending ?? 0, 'unallocated' => $credits->get($cur)->unallocated ?? 0, 'unmatched' => $lines->get($cur)->unmatched ?? 0,
            ];
            $m = array_map(fn ($v) => Money::money($v), $m);
            $m['recovery_rate'] = Money::ratio($m['allocated'], $m['expected']);
            $m['overdue_count'] = (int) ($r->overdue_count ?? 0);
            $m['pending_count'] = (int) ($p->pending_count ?? 0);
            $m['unmatched_count'] = (int) ($lines->get($cur)->unmatched_count ?? 0);
            $out[$cur] = $m;
        }

        return $out;
    }

    public function breachedContracts(array $f): int
    {
        $svc = app(ContractService::class);

        return PaymentContract::query()->with('installments')->whereIn('status', ['active', 'signed'])
            ->when($f['year_id'] ?? null, fn ($q, $v) => $q->where('academic_year_id', $v))->get()
            ->filter(fn ($c) => $svc->compliance($c)['breached'])->count();
    }

    private function scope($q, array $f, string $alias): void
    {
        if (! empty($f['year_id'])) {
            $q->where("{$alias}.academic_year_id", $f['year_id']);
        }
        if (array_key_exists('faculty_ids', $f) && $f['faculty_ids'] !== null) {
            $q->whereIn('d.faculty_id', $f['faculty_ids'] ?: [0]);
        }
        if (! empty($f['faculty_id'])) {
            $q->where('d.faculty_id', $f['faculty_id']);
        }
        if (! empty($f['program_id'])) {
            $q->where('pg.id', $f['program_id']);
        }
        if (! empty($f['promotion_id'])) {
            $q->where('pr.id', $f['promotion_id']);
        }
        if (! empty($f['fee_category_id']) && $alias === 'c') {
            $q->where('c.fee_category_id', $f['fee_category_id']);
        }
        if (! empty($f['currency'])) {
            $q->where("{$alias}.currency", $f['currency']);
        }
    }
}
