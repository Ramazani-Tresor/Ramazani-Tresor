<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\Payment;
use App\Services\ClosingService;
use App\Services\CreditService;
use App\Services\PaymentService;
use App\Services\SyncService;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditAndClosingTest extends TestCase
{
    private function overpaid(): array
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p = $this->pay($s, '200', [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '100']]); // 100 non affectés

        return [$w, $s, $p];
    }

    public function test_compensation_d_un_excedent(): void
    {
        [$w, $s, $p] = $this->overpaid();
        $svc = app(CreditService::class);
        $min = $this->charge($s, 'MINERVAL');
        $op = $svc->request($p, 'compensation', '80', 'Compensation demandée par l’étudiant', $w['clerk'], (string) Str::uuid(), $min->id);
        $this->assertSame('20.00', $p->fresh()->availableAmount());           // réservé dès la demande
        $this->assertSame('500.00', $min->fresh()->balance());                // sans effet avant approbation
        $svc->approve($op, $w['budget']);
        $this->assertSame('420.00', $min->fresh()->balance());
        $this->assertSame('20.00', $p->fresh()->availableAmount());
        $this->assertSame(1, Payment::query()->count());                      // compensation ≠ nouveau paiement
    }

    public function test_remboursement_et_prevention_de_la_double_utilisation(): void
    {
        [$w, $s, $p] = $this->overpaid();
        $svc = app(CreditService::class);
        $r1 = $svc->request($p, 'refund', '100', 'Remboursement du trop-perçu', $w['clerk'], (string) Str::uuid(), null, null, 'Virement');
        try {
            $svc->request($p, 'compensation', '50', 'Seconde utilisation', $w['clerk'], (string) Str::uuid(), $this->charge($s, 'MINERVAL')->id);
            $this->fail('Le même crédit ne peut pas être utilisé deux fois');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('Crédit insuffisant', $e->getMessage());
        }
        $svc->approve($r1, $w['budget'], null, 'VIR-001');
        $this->assertSame('executed', $r1->fresh()->status);
        $this->assertSame('0.00', $p->fresh()->availableAmount());
        $this->expectException(BusinessException::class);
        $svc->approve($r1->fresh(), $w['budget']);                            // ré-approbation refusée
    }

    public function test_report_sur_l_annee_suivante(): void
    {
        [$w, $s, $p] = $this->overpaid();
        $w['next']->update(['status' => 'open']);
        $svc = app(CreditService::class);
        $op = $svc->request($p, 'carry_forward', '100', 'Report de l’excédent', $w['clerk'], (string) Str::uuid(), null, $w['next']->id);
        $svc->approve($op, $w['budget']);
        $carried = Payment::query()->where('type', 'carry_forward')->sole();
        $this->assertSame($w['next']->id, $carried->academic_year_id);
        $this->assertSame($p->id, $carried->origin_payment_id);
        $this->assertSame('100.00', $carried->availableAmount());
        $this->assertSame('0.00', $p->fresh()->availableAmount());
    }

    public function test_pas_de_compensation_entre_devises_sans_taux_approuve(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p = $this->pay($s, '50000', [], 'CDF-1', true, $w['acc_cdf']);
        $this->expectExceptionMessage('Aucun taux de conversion approuvé');
        app(CreditService::class)->request($p, 'compensation', '10000', 'Test', $w['clerk'], (string) Str::uuid(), $this->charge($s, 'CONNEXES')->id);
    }

    public function test_cloture_bloque_les_ecritures_et_reporte_sans_duplication(): void
    {
        [$w, $s, $p] = $this->overpaid();
        $closing = app(ClosingService::class);
        $pending = $this->pay($s, '10', [], 'PEND', false);
        try {
            $closing->close($w['year'], $w['budget'], 'Fin d’année', true);
            $this->fail('Un paiement en attente bloque la clôture');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('bloquants', $e->getMessage());
        }
        app(PaymentService::class)->reject($pending, $w['validator'], 'Doublon');
        $stats = $closing->close($w['year'], $w['budget'], 'Clôture de l’exercice (test)', true);
        $this->assertSame(['arrears' => 2, 'credits' => 1], $stats);          // MINERVAL + INSCR-NOUV reportés ; excédent reporté
        $min = $this->charge($s, 'MINERVAL');
        $this->assertSame('0.00', $min->fresh()->balance());
        $carried = Charge::query()->where('carried_from_charge_id', $min->id)->sole();
        $this->assertSame('500.00', $carried->amount);
        $this->assertSame($w['next']->id, $carried->academic_year_id);

        // Écritures bloquées : saisie, synchronisation
        try {
            $this->pay($s, '5', [], 'APRES', false);
            $this->fail('Écriture sur année clôturée');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('clôturée', $e->getMessage());
        }
        $res = app(SyncService::class)->pushPayment(['client_uuid' => (string) Str::uuid(), 'matricule' => $s->matricule, 'academic_year_id' => $w['year']->id,
            'bank_account_id' => $w['acc']->id, 'bank_reference' => 'OFF-1', 'deposit_date' => '2025-11-10', 'amount' => '5', 'currency' => 'USD', 'proof_base64' => base64_encode('%PDF-1.4 x')], $w['clerk'], 'poste-1');
        $this->assertSame('rejected', $res['status']);

        // Réouverture motivée puis nouvelle clôture : aucun double report
        $closing->reopen($w['year']->fresh(), $w['budget'], 'Correction d’une erreur de saisie autorisée par le recteur');
        $w['next']->update(['status' => 'open']);
        $closing->close($w['year']->fresh(), $w['budget'], 'Seconde clôture', true);
        $this->assertSame(1, Charge::query()->where('carried_from_charge_id', $min->id)->count());
        $this->assertSame(1, Payment::query()->where('type', 'carry_forward')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'year.reopen')->count());
    }
}
