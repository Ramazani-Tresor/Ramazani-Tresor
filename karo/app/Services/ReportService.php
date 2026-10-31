<?php

namespace App\Services;

use App\Models\ChargeAdjustment;
use App\Models\CreditOperation;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Support\Filters;
use App\Support\Labels;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rapports tabulaires (écran, Excel, PDF) — toujours par devise, jamais d'addition entre devises.
 * Les totaux sont calculés par devise ; une affectation ou un rapprochement n'est jamais compté comme paiement.
 */
class ReportService
{
    public const REPORTS = [
        'recovery' => 'Recouvrement par faculté et promotion (synthèse)',
        'balances' => 'Soldes par étudiant (détail)',
        'payments' => 'Journal des paiements',
        'allocations' => 'Affectations par frais',
        'overdue' => 'Échéances en retard',
        'credits' => 'Excédents : compensations, remboursements, reports',
        'adjustments' => 'Bourses, réductions, exonérations',
    ];

    public function build(string $type, Filters $f): array
    {
        return $this->{'r'.ucfirst($type)}($f) + ['title' => self::REPORTS[$type]];
    }

    private function chargeBase(Filters $f)
    {
        $adj = DB::table('charge_adjustments')->where('status', 'approved')->groupBy('charge_id')->selectRaw('charge_id, SUM(amount) a');
        $al = DB::table('payment_allocations as pa')->join('payments as p', 'p.id', '=', 'pa.payment_id')->where('pa.status', 'active')->where('p.status', 'validated')->groupBy('pa.charge_id')->selectRaw('pa.charge_id, SUM(pa.charge_amount) s');
        $q = DB::table('charges as c')->where('c.status', 'active')
            ->join('students as st', 'st.id', '=', 'c.student_id')
            ->leftJoin('enrollments as e', fn ($j) => $j->on('e.student_id', '=', 'c.student_id')->on('e.academic_year_id', '=', 'c.academic_year_id'))
            ->leftJoin('promotions as pr', 'pr.id', '=', 'e.promotion_id')->leftJoin('programs as pg', 'pg.id', '=', 'pr.program_id')
            ->leftJoin('departments as d', 'd.id', '=', 'pg.department_id')->leftJoin('faculties as fa', 'fa.id', '=', 'd.faculty_id')
            ->leftJoinSub($adj, 'adj', 'adj.charge_id', '=', 'c.id')->leftJoinSub($al, 'al', 'al.charge_id', '=', 'c.id')
            ->where('c.academic_year_id', $f->get('year'));
        $this->scope($q, $f);
        if ($f->get('fee')) {
            $q->where('c.fee_category_id', $f->get('fee'));
        }

        return $q;
    }

    private function rRecovery(Filters $f): array
    {
        $rows = $this->chargeBase($f)->groupBy('fa.code', 'pr.code', 'c.currency')
            ->selectRaw('fa.code as faculty, pr.code as promotion, c.currency, COUNT(DISTINCT c.student_id) as students, SUM(c.amount - COALESCE(adj.a,0)) as expected, SUM(COALESCE(al.s,0)) as settled')
            ->orderBy('fa.code')->orderBy('pr.code')->get()
            ->map(fn ($r) => ['faculty' => $r->faculty, 'promotion' => $r->promotion, 'currency' => $r->currency, 'students' => $r->students,
                'expected' => Money::money($r->expected), 'settled' => Money::money($r->settled), 'balance' => Money::money(Money::sub($r->expected, $r->settled)), 'rate' => Money::ratio($r->settled, $r->expected).' %'])->all();

        return $this->table(['faculty' => 'Faculté', 'promotion' => 'Promotion', 'currency' => 'Devise', 'students' => 'Étudiants', 'expected' => 'Attendu (net)', 'settled' => 'Affecté', 'balance' => 'Reste dû', 'rate' => 'Recouvrement'],
            ['expected', 'settled', 'balance'], $rows, 'Attendu = créances actives − ajustements approuvés ; affecté = affectations effectives des paiements validés.');
    }

    private function rBalances(Filters $f): array
    {
        $rows = $this->chargeBase($f)->groupBy('st.matricule', 'st.last_name', 'st.first_name', 'pr.code', 'c.currency')
            ->selectRaw('st.matricule, st.last_name, st.first_name, pr.code as promotion, c.currency, SUM(c.amount - COALESCE(adj.a,0)) as net, SUM(COALESCE(al.s,0)) as settled')
            ->orderBy('st.last_name')->get()
            ->map(fn ($r) => ['matricule' => $r->matricule, 'name' => trim($r->last_name.' '.$r->first_name), 'promotion' => $r->promotion, 'currency' => $r->currency,
                'net' => Money::money($r->net), 'settled' => Money::money($r->settled), 'balance' => Money::money(Money::max(0, Money::sub($r->net, $r->settled)))])
            ->when($f->get('status') === 'due', fn ($c) => $c->filter(fn ($r) => Money::isPositive($r['balance'])))->values()->all();

        return $this->table(['matricule' => 'Matricule', 'name' => 'Nom', 'promotion' => 'Promotion', 'currency' => 'Devise', 'net' => 'Net dû', 'settled' => 'Réglé', 'balance' => 'Solde dû'],
            ['net', 'settled', 'balance'], $rows, 'Les crédits disponibles (montants non affectés) sont présentés dans le rapport des excédents, jamais déduits d’office des soldes.');
    }

    private function rPayments(Filters $f): array
    {
        $q = Payment::query()->with(['student', 'bankAccount.bank'])->where('type', 'bank_deposit')->where('academic_year_id', $f->get('year'))
            ->whereHas('student', fn ($s) => $s->inFaculties($f->facultyScope))
            ->when($f->get('account'), fn ($w, $v) => $w->where('bank_account_id', $v))->when($f->get('currency'), fn ($w, $v) => $w->where('currency', $v))
            ->when($f->get('from'), fn ($w) => $w->whereBetween('deposit_date', [$f->get('from'), $f->get('to') ?? now()->toDateString()]))
            ->when($f->get('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($f->get('promotion'), fn ($w, $v) => $w->whereHas('student.enrollments', fn ($e) => $e->where('academic_year_id', $f->get('year'))->where('promotion_id', $v)))
            ->when($f->get('faculty'), fn ($w, $v) => $w->whereHas('student.enrollments', fn ($e) => $e->where('academic_year_id', $f->get('year'))->whereHas('promotion.program.department', fn ($d) => $d->where('faculty_id', $v))))
            ->orderBy('deposit_date');
        $rows = $q->get()->map(fn ($p) => ['number' => $p->number, 'date' => $p->deposit_date?->format('d/m/Y'), 'matricule' => $p->student->matricule, 'name' => $p->student->fullName(),
            'bank' => $p->bankAccount?->bank->code.' '.$p->bankAccount?->account_number, 'reference' => $p->bank_reference, 'currency' => $p->currency, 'amount' => (string) $p->amount,
            'status' => Labels::status('payment', $p->status), 'bank_status' => Labels::status('bank', $p->bank_status), 'confirmed' => (string) $p->bank_confirmed_amount])->all();

        return $this->table(['number' => 'N°', 'date' => 'Date bancaire', 'matricule' => 'Matricule', 'name' => 'Nom', 'bank' => 'Compte', 'reference' => 'Référence', 'currency' => 'Devise', 'amount' => 'Montant', 'status' => 'Statut', 'bank_status' => 'Banque', 'confirmed' => 'Confirmé'],
            ['amount', 'confirmed'], $rows, 'Versements bancaires uniquement (les reports d’excédent ne sont pas des encaissements).');
    }

    private function rAllocations(Filters $f): array
    {
        $rows = PaymentAllocation::query()->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')->join('charges as c', 'c.id', '=', 'payment_allocations.charge_id')
            ->join('fee_categories as fc', 'fc.id', '=', 'c.fee_category_id')
            ->where('payment_allocations.status', 'active')->where('p.status', 'validated')->where('c.academic_year_id', $f->get('year'))
            ->when($f->get('fee'), fn ($w, $v) => $w->where('c.fee_category_id', $v))->when($f->facultyScope !== null, fn ($w) => $w->whereIn('c.student_id', Student::query()->inFaculties($f->facultyScope)->select('id')))
            ->groupBy('fc.label', 'c.currency', 'payment_allocations.kind')->selectRaw('fc.label, c.currency, payment_allocations.kind, COUNT(*) as n, SUM(payment_allocations.charge_amount) as total')
            ->orderBy('fc.label')->get()->map(fn ($r) => ['fee' => $r->label, 'currency' => $r->currency, 'kind' => $r->kind === 'compensation' ? 'Compensation' : 'Directe', 'n' => $r->n, 'total' => Money::money($r->total)])->all();

        return $this->table(['fee' => 'Frais', 'currency' => 'Devise', 'kind' => 'Nature', 'n' => 'Affectations', 'total' => 'Montant affecté'], ['total'], $rows, 'Une affectation répartit un paiement existant : elle n’ajoute aucun encaissement.');
    }

    private function rOverdue(Filters $f): array
    {
        $today = now()->toDateString();
        $due = DB::table('charge_installments')->where('due_date', '<=', $today)->groupBy('charge_id')->selectRaw('charge_id, SUM(amount) d, MIN(due_date) first_due');
        $rows = $this->chargeBase($f)->joinSub($due, 'du', 'du.charge_id', '=', 'c.id')->join('fee_categories as fc', 'fc.id', '=', 'c.fee_category_id')
            ->selectRaw('st.matricule, st.last_name, st.first_name, pr.code as promotion, fc.label, c.currency, c.amount - COALESCE(adj.a,0) as net, COALESCE(al.s,0) as settled, du.d as due_sum, du.first_due')
            ->get()->map(function ($r) {
                $exig = Money::sub(Money::min($r->due_sum, $r->net), $r->settled);

                return ['matricule' => $r->matricule, 'name' => trim($r->last_name.' '.$r->first_name), 'promotion' => $r->promotion, 'fee' => $r->label, 'currency' => $r->currency,
                    'first_due' => Carbon::parse($r->first_due)->format('d/m/Y'), 'overdue' => Money::money($exig)];
            })->filter(fn ($r) => Money::isPositive($r['overdue']))->values()->all();

        return $this->table(['matricule' => 'Matricule', 'name' => 'Nom', 'promotion' => 'Promotion', 'fee' => 'Frais', 'currency' => 'Devise', 'first_due' => '1re échéance échue', 'overdue' => 'Montant en retard'], ['overdue'], $rows, 'Montant en retard = tranches échues − réglé, par créance.');
    }

    private function rCredits(Filters $f): array
    {
        $rows = CreditOperation::query()->with(['student', 'sourcePayment', 'approver'])->whereHas('sourcePayment', fn ($p) => $p->where('academic_year_id', $f->get('year')))
            ->whereHas('student', fn ($s) => $s->inFaculties($f->facultyScope))->latest('id')->get()
            ->map(fn ($o) => ['number' => $o->number, 'matricule' => $o->student->matricule, 'type' => Labels::status('credit_type', $o->type), 'source' => $o->sourcePayment->number, 'currency' => $o->currency, 'amount' => (string) $o->amount,
                'status' => Labels::status('credit', $o->status), 'approver' => $o->approver?->name, 'date' => $o->decided_at?->format('d/m/Y')])->all();

        return $this->table(['number' => 'N°', 'matricule' => 'Matricule', 'type' => 'Opération', 'source' => 'Paiement source', 'currency' => 'Devise', 'amount' => 'Montant', 'status' => 'Statut', 'approver' => 'Approbateur', 'date' => 'Date'], ['amount'], $rows, null);
    }

    private function rAdjustments(Filters $f): array
    {
        $rows = ChargeAdjustment::query()->with(['charge.student', 'charge.category', 'approver'])->whereNotIn('type', ['transfer_out'])
            ->whereHas('charge', fn ($c) => $c->where('academic_year_id', $f->get('year'))->whereHas('student', fn ($s) => $s->inFaculties($f->facultyScope)))->latest('id')->get()
            ->map(fn ($a) => ['number' => $a->number, 'matricule' => $a->charge->student->matricule, 'fee' => $a->charge->category->label, 'type' => Labels::status('adjustment_type', $a->type), 'currency' => $a->charge->currency,
                'amount' => (string) $a->amount, 'status' => Labels::status('adjustment', $a->status), 'approver' => $a->approver?->name, 'reason' => $a->reason])->all();

        return $this->table(['number' => 'N°', 'matricule' => 'Matricule', 'fee' => 'Frais', 'type' => 'Type', 'currency' => 'Devise', 'amount' => 'Montant (+ diminue)', 'status' => 'Statut', 'approver' => 'Approbateur', 'reason' => 'Motif'], ['amount'], $rows, null);
    }

    private function scope($q, Filters $f): void
    {
        if ($f->facultyScope !== null) {
            $q->whereIn('d.faculty_id', $f->facultyScope ?: [0]);
        }
        foreach (['faculty' => 'd.faculty_id', 'program' => 'pg.id', 'promotion' => 'pr.id', 'currency' => 'c.currency'] as $k => $col) {
            if ($f->get($k)) {
                $q->where($col, $f->get($k));
            }
        }
    }

    /** Totaux par devise uniquement. */
    private function table(array $columns, array $money, array $rows, ?string $note): array
    {
        $totals = [];
        foreach ($rows as $r) {
            $cur = $r['currency'] ?? '—';
            foreach ($money as $k) {
                $totals[$cur][$k] = Money::money(Money::add($totals[$cur][$k] ?? 0, $r[$k] ?? 0));
            }
        }
        ksort($totals);

        return compact('columns', 'money', 'rows', 'totals', 'note');
    }
}
