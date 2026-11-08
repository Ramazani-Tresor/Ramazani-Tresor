<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\PrimeEntitlement;
use App\Models\PrimePayment;
use App\Services\DeathService;
use App\Services\PrimeService;
use App\Services\SituationService;
use App\Support\Money;
use Tests\TestCase;

class PrimesTest extends TestCase
{
    private function ent($employee): PrimeEntitlement
    {
        return PrimeEntitlement::query()->with(['adjustments', 'payments'])->where('employee_id', $employee->id)->latest('id')->firstOrFail();
    }

    public function test_generation_au_prorata_et_sans_doublon(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep'], [], '2026-03-11');
        $svc = app(PrimeService::class);
        $r = $svc->generate($type, $this->d('2026-03-01'), $this->d('2026-03-31'), '2026-03', $this->u['fin']);
        $this->assertSame(1, $r['created']);
        $this->assertSame('203.23', (string) $this->ent($e)->amount); // 300 × 21/31
        $r2 = $svc->generate($type, $this->d('2026-03-01'), $this->d('2026-03-31'), '2026-03', $this->u['fin']);
        $this->assertSame(0, $r2['created']);
        $this->assertSame(1, $r2['existing']);
        $this->assertSame(1, PrimeEntitlement::count());
    }

    public function test_taux_le_plus_specifique_et_sans_taux(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00', ['staff_family' => 'scientific']);
        $this->monthlyPrime('900.00', ['grade_id' => $this->grade('PO')->id]);
        $ass = $this->employee($w['dep']);
        $po = $this->employee($w['dep'], ['grade' => 'PO']);
        $adm = $this->employee($w['srv'], ['grade' => 'CB']);
        $r = app(PrimeService::class)->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $this->assertSame(2, $r['created']);
        $this->assertSame(1, $r['no_rate']); // aucun taux pour le personnel administratif : rien n'est inventé
        $this->assertSame('300.00', (string) $this->ent($ass)->amount);
        $this->assertSame('900.00', (string) $this->ent($po)->amount);
        $this->assertSame(0, PrimeEntitlement::where('employee_id', $adm->id)->count());
    }

    public function test_acompte_puis_paiement_du_solde_avec_deduction_automatique(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep']);
        $svc = app(PrimeService::class);
        $svc->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $ent = $this->ent($e);
        $adv = $svc->record($ent, 'advance', '100', ['mode' => 'cash'], $this->u['fin']);
        $svc->validate($adv, $this->u['fin2']);
        try {
            $svc->record($ent, 'advance', '250', [], $this->u['fin']);
            $this->fail('Acompte supérieur au solde accepté');
        } catch (BusinessException $ex) {
            $this->assertStringContainsString('solde restant', $ex->getMessage());
        }
        $pay = $svc->record($ent, 'payment', null, ['mode' => 'bank_transfer', 'reference' => 'VIR-001'], $this->u['fin']);
        $this->assertSame('200.00', (string) $pay->amount); // 300 − acompte de 100
        $svc->validate($pay, $this->u['fin2']);
        $ent = $this->ent($e);
        $this->assertSame('0.00', $ent->balance());
        $this->assertSame('100.00', $ent->advancesAmount());
        $sum = $svc->summary($e)['USD'];
        $this->assertSame('300.00', $sum['paid']);
        $this->assertSame('0.00', $sum['unregularized_advances']);
    }

    public function test_separation_des_taches_sur_les_paiements(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep']);
        $svc = app(PrimeService::class);
        $svc->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $p = $svc->record($this->ent($e), 'payment', null, [], $this->u['fin']);
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Séparation des tâches');
        $svc->validate($p, $this->u['fin']);
    }

    public function test_contrepassation_sans_suppression(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep']);
        $svc = app(PrimeService::class);
        $svc->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $p = $svc->record($this->ent($e), 'payment', null, [], $this->u['fin']);
        $svc->validate($p, $this->u['fin2']);
        $rev = $svc->reverse($p, 'Erreur de bénéficiaire', $this->u['fin2']);
        $this->assertSame('-300.00', (string) $rev->amount);
        $this->assertSame('reversed', $p->fresh()->status);
        $this->assertSame('300.00', $this->ent($e)->balance());
        $this->assertSame(2, PrimePayment::count());
        $this->expectException(BusinessException::class);
        $p->fresh()->delete();
    }

    public function test_regles_de_situation_suspension_et_conge(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $susp = $this->employee($w['dep']);
        $leave = $this->employee($w['dep']);
        $sit = app(SituationService::class);
        $s = $sit->request($susp, ['situation_type_id' => $this->situationType('SUSP_ADMIN')->id, 'start_date' => '2026-04-01', 'planned_end_date' => '2026-06-30', 'reason' => 'Enquête administrative', 'decision_reference' => 'D-01'], $this->u['clerk']);
        $sit->approve($s, $this->u['hr']);
        $l = $sit->request($leave, ['situation_type_id' => $this->situationType('CONGE_ANNUEL')->id, 'start_date' => '2026-04-10', 'planned_end_date' => '2026-04-25', 'reason' => 'Congé annuel'], $this->u['clerk']);
        $sit->approve($l, $this->u['hr']);
        app(PrimeService::class)->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $this->assertSame('suspended', $this->ent($susp)->status);
        $this->assertSame('0.00', (string) $this->ent($susp)->amount);
        $this->assertSame('300.00', (string) $this->ent($leave)->amount); // congé annuel : 100 % (paramétrable)
    }

    public function test_deces_retroactif_trop_percu_regularisation_et_arret_des_primes(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep']);
        $svc = app(PrimeService::class);
        $svc->generate($type, $this->d('2026-03-01'), $this->d('2026-03-31'), '2026-03', $this->u['fin']);
        $p = $svc->record($this->ent($e), 'payment', null, [], $this->u['fin']);
        $svc->validate($p, $this->u['fin2']);
        $deaths = app(DeathService::class);
        $d = $deaths->record($e, ['death_date' => '2026-03-16', 'death_place' => 'Likasi'], $this->u['clerk']);
        $deaths->validate($d, $this->u['hr']);
        $ent = $this->ent($e);
        $this->assertSame('145.16', $ent->netAmount()); // 300 × 15/31 : primes acquises avant le décès
        $this->assertSame('-154.84', $ent->balance());   // trop-perçu, le paiement validé n'est pas touché
        $this->assertSame('validated', $p->fresh()->status);
        $this->assertSame('154.84', $svc->summary($e->fresh())['USD']['overpaid']);
        $reg = $svc->record($ent, 'regularization', '154.84', ['reference' => 'RET-01'], $this->u['fin']);
        $svc->validate($reg, $this->u['fin2']);
        $this->assertSame('0.00', $this->ent($e)->balance());
        $r = $svc->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $this->assertSame(0, $r['created']); // plus aucune nouvelle prime après la date d'effet
        $this->assertSame(0, Money::cmp($d->fresh()->financial_snapshot['USD']['overpaid'], '154.84'));
    }

    public function test_paiement_apres_deces_exige_un_ayant_droit(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep']);
        $svc = app(PrimeService::class);
        $svc->generate($type, $this->d('2026-03-01'), $this->d('2026-03-31'), '2026-03', $this->u['fin']);
        $deaths = app(DeathService::class);
        $deaths->validate($deaths->record($e, ['death_date' => '2026-04-05'], $this->u['clerk']), $this->u['hr']);
        try {
            $svc->record($this->ent($e), 'payment', null, [], $this->u['fin']);
            $this->fail('Paiement sans ayant droit accepté');
        } catch (BusinessException $ex) {
            $this->assertStringContainsString('ayant droit', $ex->getMessage());
        }
        $p = $svc->record($this->ent($e), 'payment', null, ['beneficiary' => 'Conjoint (fictif)'], $this->u['fin']);
        $this->assertSame('300.00', (string) $p->amount); // arriérés de mars dus aux ayants droit
    }
}
