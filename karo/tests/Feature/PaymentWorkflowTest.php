<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Payment;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Services\BalanceService;
use App\Services\PaymentService;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    public function test_paiement_partiel_laisse_un_solde_exact(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $min = $this->charge($s, 'MINERVAL');
        $this->pay($s, '200', [['charge_id' => $min->id, 'amount' => '200']]);
        $this->assertSame('300.00', $min->fresh()->balance());
        $this->pay($s, '100', [['charge_id' => $min->id, 'amount' => '100']]); // plusieurs versements sur une même créance
        $this->assertSame('200.00', $min->fresh()->balance());
    }

    public function test_versement_reparti_entre_plusieurs_frais_reste_un_paiement_unique(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p = $this->pay($s, '700', [
            ['charge_id' => $this->charge($s, 'MINERVAL')->id, 'amount' => '500'],
            ['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '100'],
            ['charge_id' => $this->charge($s, 'INSCR-NOUV')->id, 'amount' => '50'],
        ]);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(3, $p->allocations()->where('status', 'active')->count());
        $this->assertSame('50.00', $p->availableAmount());           // montant non affecté conservé
        $sit = app(BalanceService::class)->situation($s, $w['year']->id);
        $this->assertSame('0.00', $sit['totals']['USD']['balance']);
        $this->assertSame('50.00', $sit['totals']['USD']['credit']);
        $this->assertSame('700.00', $sit['totals']['USD']['validated']);
    }

    public function test_affectations_superieures_au_montant_refusees(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $this->expectException(BusinessException::class);
        $this->pay($s, '100', [['charge_id' => $this->charge($s, 'MINERVAL')->id, 'amount' => '80'], ['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '30']], null, false);
    }

    public function test_separation_saisie_controle_validation(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $svc = app(PaymentService::class);
        $p = $this->pay($s, '100', [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '100']], null, false);
        // L'auteur détient aussi les droits de contrôle et de validation : seule la séparation des tâches l'arrête.
        foreach (['validator', 'budget_admin'] as $role) {
            UserRoleAssignment::create(['user_id' => $w['clerk']->id, 'role_id' => Role::query()->where('slug', $role)->value('id')]);
        }
        $w['clerk']->flushGrants();
        foreach (['check', 'validate'] as $action) {
            try {
                $action === 'check' ? $svc->check($p, $w['clerk']) : $svc->validate($p, $w['clerk'], true);
                $this->fail("L'auteur de la saisie ne doit pas pouvoir {$action}");
            } catch (BusinessException $e) {
                $this->assertStringContainsString('Séparation des tâches', $e->getMessage());
            }
        }
        try {
            $svc->validate($p, $w['budget'], true);
            $this->fail('Validation sans contrôle du justificatif');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('contrôlé', $e->getMessage());
        }
        $this->assertSame('100.00', $this->charge($s, 'CONNEXES')->balance()); // saisie sans effet sur le solde
        $svc->check($p, $w['validator']);
        $svc->validate($p, $w['budget'], true);
        $this->assertSame('0.00', $this->charge($s, 'CONNEXES')->balance());
        $this->assertSame('unconfirmed', $p->fresh()->bank_status);          // validé ≠ confirmé par la banque
    }

    public function test_detection_des_doublons_et_idempotence(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $svc = app(PaymentService::class);
        $data = ['client_uuid' => (string) Str::uuid(), 'student_id' => $s->id, 'academic_year_id' => $w['year']->id, 'bank_account_id' => $w['acc']->id,
            'bank_reference' => 'BRD-2025/0001', 'deposit_date' => '2025-11-10', 'amount' => '100', 'currency' => 'USD'];
        [$p1] = $svc->submit($data, [], $this->proof(), $w['clerk']);
        [$again, $dup] = $svc->submit($data, [], $this->proof(), $w['clerk']);     // double clic / re-tentative
        $this->assertTrue($dup);
        $this->assertSame($p1->id, $again->id);
        [$p2] = $svc->submit(array_merge($data, ['client_uuid' => (string) Str::uuid(), 'bank_reference' => 'brd 2025 0001']), [], $this->proof(), $w['clerk']);
        $codes = array_column($p2->anomalies, 'code');
        $this->assertContains('duplicate_reference', $codes);
        $this->assertContains('possible_duplicate', $codes);
        $svc->check($p2, $w['validator']);
        try {
            $svc->validate($p2, $w['budget']);
            $this->fail('Une anomalie doit être examinée explicitement');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('Référence bancaire déjà utilisée', $e->getMessage());
        }
    }

    public function test_coherence_devise_et_compte_autorise(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $svc = app(PaymentService::class);
        try {
            $svc->submit(['client_uuid' => (string) Str::uuid(), 'student_id' => $s->id, 'academic_year_id' => $w['year']->id, 'bank_account_id' => $w['acc']->id,
                'bank_reference' => 'X', 'deposit_date' => '2025-11-10', 'amount' => '10', 'currency' => 'CDF'], [], $this->proof(), $w['clerk']);
            $this->fail('Devise incohérente');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('Devise incohérente', $e->getMessage());
        }
        $w['cat']['CONNEXES']->bankAccounts()->sync([$w['acc_cdf']->id]);
        $this->expectExceptionMessage('n\'est pas autorisé');
        $this->pay($s, '100', [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '100']], null, false);
    }

    public function test_double_affectation_concurrente_refusee_a_la_validation(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $c = $this->charge($s, 'CONNEXES');
        $a = $this->pay($s, '100', [['charge_id' => $c->id, 'amount' => '100']], null, false);
        $b = $this->pay($s, '100', [['charge_id' => $c->id, 'amount' => '100']], null, false); // proposée, non encore effective
        $svc = app(PaymentService::class);
        foreach ([$a, $b] as $p) {
            $svc->check($p, $w['validator']);
        }
        $svc->validate($a, $w['budget'], true);
        $this->expectExceptionMessage('Affectation supérieure au solde');
        $svc->validate($b, $w['budget'], true);
    }

    public function test_contrepassation_sans_suppression(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $c = $this->charge($s, 'CONNEXES');
        $p = $this->pay($s, '100', [['charge_id' => $c->id, 'amount' => '100']]);
        app(PaymentService::class)->reverse($p, $w['budget'], 'Bordereau falsifié (fictif)');
        $this->assertSame('reversed', $p->fresh()->status);
        $this->assertSame('100.00', $c->fresh()->balance());
        $this->assertDatabaseHas('payments', ['id' => $p->id]);
        $this->assertSame(['submitted', 'checked', 'validated', 'reversed'], $p->events()->pluck('to_status')->all());
    }
}
