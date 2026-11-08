<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\KinshipDegree;
use App\Models\PrimeEntitlement;
use App\Models\SocialSupport;
use App\Notifications\AgentNotification;
use App\Services\DeathService;
use App\Services\MutationService;
use App\Services\PrimeService;
use App\Services\SituationService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MovementsTest extends TestCase
{
    public function test_mutation_externe_cloture_les_droits_et_conserve_le_dossier(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep']);
        $primes = app(PrimeService::class);
        $primes->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $svc = app(MutationService::class);
        $m = $svc->propose($e, ['kind' => 'external', 'destination_institution' => 'Université partenaire (fictive)', 'reason' => 'Demande de l’agent', 'decision_date' => '2026-04-01', 'effective_date' => '2026-04-16', 'decision_reference' => 'MUT-001'], $this->u['clerk']);
        $svc->validate($m, $this->u['hr']);
        $e->refresh();
        $this->assertSame('transferred_out', $e->status);
        $this->assertSame('2026-04-16', $e->rights_end_date->toDateString());
        $this->assertSame(0, Assignment::where('employee_id', $e->id)->where('status', 'active')->count());
        $this->assertSame('2026-04-15', Assignment::where('employee_id', $e->id)->latest('id')->first()->end_date->toDateString());
        $ent = PrimeEntitlement::with(['adjustments', 'payments'])->where('employee_id', $e->id)->first();
        $this->assertSame('150.00', $ent->netAmount()); // 15 jours sur 30 restent dus
        $this->assertSame('150.00', $m->fresh()->financial_snapshot['USD']['balance']);
        $r = $primes->generate($type, $this->d('2026-05-01'), $this->d('2026-05-31'), '2026-05', $this->u['fin']);
        $this->assertSame(0, $r['created']);
        $this->expectException(BusinessException::class);
        $e->delete(); // le dossier n'est jamais supprimé
    }

    public function test_mutation_interne_maintient_les_droits(): void
    {
        $w = $this->world();
        $type = $this->monthlyPrime('300.00');
        $e = $this->employee($w['dep']);
        $svc = app(MutationService::class);
        $m = $svc->propose($e, ['kind' => 'internal', 'to_unit_id' => $w['fd']->id, 'reason' => 'Nécessité de service', 'decision_date' => '2026-04-01', 'effective_date' => '2026-04-10', 'decision_reference' => 'MUT-002'], $this->u['clerk']);
        $svc->validate($m, $this->u['hr']);
        $e->refresh();
        $this->assertSame('active', $e->status);
        $this->assertSame($w['fd']->id, $e->currentUnitId());
        $this->assertSame(1, Assignment::where('employee_id', $e->id)->where('status', 'active')->count());
        $this->assertSame(2, Assignment::where('employee_id', $e->id)->count()); // historique conservé
        app(PrimeService::class)->generate($type, $this->d('2026-04-01'), $this->d('2026-04-30'), '2026-04', $this->u['fin']);
        $this->assertSame('300.00', (string) PrimeEntitlement::where('employee_id', $e->id)->first()->amount);
    }

    public function test_mutation_sans_date_d_effet_refusee_et_validation_par_un_tiers(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $svc = app(MutationService::class);
        try {
            $svc->propose($e, ['kind' => 'internal', 'to_unit_id' => $w['fd']->id, 'reason' => 'x', 'decision_date' => '2026-04-01', 'decision_reference' => 'M'], $this->u['clerk']);
            $this->fail('Mutation sans date d’effet acceptée');
        } catch (BusinessException $ex) {
            $this->assertStringContainsString('date d’effet', $ex->getMessage());
        }
        $m = $svc->propose($e, ['kind' => 'internal', 'to_unit_id' => $w['fd']->id, 'reason' => 'x', 'decision_date' => '2026-04-01', 'effective_date' => '2026-04-02', 'decision_reference' => 'M'], $this->u['hr']);
        $this->expectExceptionMessage('Séparation des tâches');
        $svc->validate($m, $this->u['hr']);
    }

    public function test_suspension_avec_duree_et_justification_puis_reintegration(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $svc = app(SituationService::class);
        try {
            $svc->request($e, ['situation_type_id' => $this->situationType('SUSP_DISC')->id, 'start_date' => now()->subDays(5)->toDateString(), 'reason' => 'Faute'], $this->u['clerk']);
            $this->fail('Suspension sans durée acceptée');
        } catch (BusinessException $ex) {
            $this->assertStringContainsString('durée', $ex->getMessage());
        }
        $s = $svc->request($e, ['situation_type_id' => $this->situationType('SUSP_DISC')->id, 'start_date' => now()->subDays(5)->toDateString(), 'planned_end_date' => now()->addDays(25)->toDateString(), 'reason' => 'Faute professionnelle', 'decision_reference' => 'DISC-01'], $this->u['clerk']);
        $this->assertSame(31, $s->durationDays());
        $svc->approve($s, $this->u['hr']);
        $this->assertSame('suspended', $e->fresh()->status);
        $svc->close($s->fresh(), now(), $this->u['hr']);
        $this->assertSame('active', $e->fresh()->status);
        $this->assertSame('closed', $s->fresh()->status);
    }

    public function test_disponibilite_cloture_l_affectation_et_prolongation(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $svc = app(SituationService::class);
        $s = $svc->request($e, ['situation_type_id' => $this->situationType('DISPONIBILITE')->id, 'start_date' => now()->toDateString(), 'planned_end_date' => now()->addMonths(6)->toDateString(), 'reason' => 'Convenances personnelles'], $this->u['clerk']);
        $svc->approve($s, $this->u['hr']);
        $this->assertSame('available', $e->fresh()->status);
        $this->assertNull($e->fresh()->currentUnitId());
        $ext = $svc->extend($s->fresh(), now()->addMonths(9), ['reason' => 'Prolongation demandée'], $this->u['clerk']);
        $this->assertSame($s->id, $ext->extends_id);
        $this->assertSame('requested', $ext->status);
    }

    public function test_deces_statut_affectation_soutien_et_dossier_conserve(): void
    {
        $w = $this->world();
        KinshipDegree::where('code', 'AGENT')->update(['support_amount' => '500.00']);
        $e = $this->employee($w['dep']);
        $svc = app(DeathService::class);
        $d = $svc->record($e, ['death_date' => now()->subDays(2)->toDateString(), 'death_place' => 'Likasi', 'cause' => 'Information confidentielle'], $this->u['clerk']);
        $this->assertNull($d->fresh()->cause); // le gestionnaire sans permission « cause » ne peut pas la saisir
        $svc->validate($d, $this->u['hr']);
        $e->refresh();
        $this->assertSame('deceased', $e->status);
        $this->assertNull($e->currentUnitId());
        $support = SocialSupport::where('employee_id', $e->id)->first();
        $this->assertNotNull($support);
        $this->assertSame('requested', $support->status);
        $this->assertSame('500.00', (string) $support->amount_requested);
        $this->assertTrue(Employee::whereKey($e->id)->exists());
        $this->expectException(BusinessException::class);
        $svc->record($e, ['death_date' => now()->toDateString()], $this->u['clerk']);
    }

    public function test_notifications_aux_agents(): void
    {
        Notification::fake();
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $user = $this->makeUser('agent.test', 'agent');
        $user->update(['employee_id' => $e->id]);
        $svc = app(SituationService::class);
        $s = $svc->request($e->fresh(), ['situation_type_id' => $this->situationType('CONGE_ANNUEL')->id, 'start_date' => now()->addDays(10)->toDateString(), 'planned_end_date' => now()->addDays(20)->toDateString(), 'reason' => 'Congé'], $this->u['clerk']);
        $svc->approve($s, $this->u['hr']);
        Notification::assertSentTo($user, AgentNotification::class, function ($n, $channels) {
            return $n->event === 'leave_approved' && in_array('database', $channels, true) && in_array('mail', $channels, true);
        });
    }
}
