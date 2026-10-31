<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\ChargeAdjustment;
use App\Services\AdjustmentService;
use App\Services\ChargeService;
use App\Services\FeeScheduleService;
use Tests\TestCase;

class FeesAndChargesTest extends TestCase
{
    public function test_tarifs_distincts_selon_la_promotion(): void
    {
        $w = $this->world();
        $drt = $this->student($w['drt_l1']);
        $sci = $this->student($w['sci_l1']);
        $this->assertSame('600.00', $this->charge($drt, 'MINERVAL')->amount);   // barème propre à DRT-L1
        $this->assertSame('500.00', $this->charge($sci, 'MINERVAL')->amount);   // barème général
    }

    public function test_creances_selon_le_statut_sans_doublon(): void
    {
        $w = $this->world();
        $new = $this->student($w['drt_l1'], 'new');
        $finalist = $this->student($w['drt_l3'], 'finalist');
        $codes = fn ($s) => $s->charges()->with('category')->get()->pluck('category.code')->sort()->values()->all();
        $this->assertSame(['CONNEXES', 'INSCR-NOUV', 'MINERVAL'], $codes($new));
        $this->assertSame(['CONNEXES', 'ENTERINEMENT', 'MINERVAL', 'REINSCR'], $codes($finalist));

        $r = app(ChargeService::class)->generateForEnrollment($new->enrollments()->first(), $w['clerk']);
        $this->assertSame(0, $r['created']);
        $this->assertSame(3, $r['existing']);
        $this->assertSame(3, $new->charges()->count());
    }

    public function test_tranches_doivent_correspondre_au_montant(): void
    {
        $this->world();
        $svc = app(FeeScheduleService::class);
        $this->assertSame(['33.33', '33.33', '33.34'], array_column($svc->computeInstallments('100', [
            ['label' => 'A', 'mode' => 'percent', 'value' => '33.33', 'due_date' => '2025-11-01'],
            ['label' => 'B', 'mode' => 'percent', 'value' => '33.33', 'due_date' => '2025-12-01'],
            ['label' => 'C', 'mode' => 'percent', 'value' => '33.34', 'due_date' => '2026-01-01'],
        ]), 'amount'));
        $this->expectException(BusinessException::class);
        $svc->computeInstallments('100', [['label' => 'A', 'mode' => 'amount', 'value' => '60', 'due_date' => '2025-11-01'], ['label' => 'B', 'mode' => 'amount', 'value' => '30', 'due_date' => '2025-12-01']]);
    }

    public function test_bareme_approuve_par_une_autre_personne_et_versionne(): void
    {
        $w = $this->world();
        $svc = app(FeeScheduleService::class);
        $s = $this->student($w['sci_l1']);
        $current = $this->charge($s, 'CONNEXES')->schedule;

        $draft = $svc->createDraft(['currency' => 'USD', 'amount' => '80', 'effective_from' => '2025-10-01', 'change_reason' => 'Décision du conseil (fictive)', 'apply_to_existing' => true],
            [['label' => 'Intégral', 'mode' => 'amount', 'value' => '80', 'due_date' => '2025-12-31']], $w['budget'], $current);
        try {
            $svc->approve($draft, $w['budget']);
            $this->fail('L’auteur ne peut pas approuver son propre barème');
        } catch (BusinessException) {
        }
        $svc->approve($draft, $w['budget2']);
        $this->assertSame(2, $draft->fresh()->version);
        $this->assertSame('superseded', $current->fresh()->status);
        $charge = $this->charge($s, 'CONNEXES');
        $this->assertSame('100.00', $charge->amount);              // montant initial jamais modifié
        $this->assertSame('80.00', $charge->netAmount());          // révision tracée
        $this->assertSame('schedule_revision', ChargeAdjustment::query()->where('charge_id', $charge->id)->value('type'));
    }

    public function test_exoneration_approuvee_par_un_tiers_avec_justificatif(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $charge = $this->charge($s, 'MINERVAL');
        $svc = app(AdjustmentService::class);
        try {
            $svc->request($charge, 'scholarship', '200', 'Bourse', $w['clerk']);
            $this->fail('Justificatif obligatoire');
        } catch (BusinessException) {
        }
        $adj = $svc->request($charge, 'scholarship', '200', 'Bourse d’excellence (fictive)', $w['clerk'], 'Fondation X', 'justifs/b.pdf');
        $this->assertSame('500.00', $charge->balance());             // sans effet avant approbation
        $svc->approve($adj, $w['budget']);
        $this->assertSame('300.00', $charge->fresh()->balance());
        $this->expectException(BusinessException::class);
        $svc->request($charge, 'exemption', '400', 'Trop', $w['clerk'], null, 'j.pdf'); // dépasse le solde
    }
}
