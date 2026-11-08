<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\AcademicYear;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\CareerEvent;
use App\Models\KinshipDegree;
use App\Models\Qualification;
use App\Models\SupportType;
use App\Services\AssetService;
use App\Services\CareerService;
use App\Services\CorrectionService;
use App\Services\RecruitmentService;
use App\Services\SocialSupportService;
use App\Services\StatsService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    public function test_recrutement_jusqu_a_l_engagement(): void
    {
        $w = $this->world();
        $svc = app(RecruitmentService::class);
        $c = $svc->create(['last_name' => 'CANDIDAT', 'first_name' => 'Fictif', 'sex' => 'M', 'staff_family' => 'scientific', 'grade_id' => $this->grade('ASS')->id, 'org_unit_id' => $w['dep']->id], $this->u['clerk']);
        try {
            $svc->engage($c, ['decision_number' => 'ENG-1', 'decision_date' => '2026-09-01', 'engagement_date' => '2026-10-01', 'org_unit_id' => $w['dep']->id], $this->u['hr']);
            $this->fail('Engagement d’une candidature non validée');
        } catch (BusinessException) {
        }
        $svc->transition($c, 'under_review', $this->u['clerk']);
        $svc->transition($c->fresh(), 'validated', $this->u['hr']);
        $e = $svc->engage($c->fresh(), ['decision_number' => 'ENG-1', 'decision_date' => '2026-09-01', 'engagement_date' => '2026-10-01', 'org_unit_id' => $w['dep']->id], $this->u['hr']);
        $this->assertStringStartsWith('UNILU-', $e->matricule);
        $this->assertSame($w['dep']->id, $e->currentUnitId());
        $this->assertSame('engaged', $c->fresh()->status);
        $this->assertSame(1, CareerEvent::where('employee_id', $e->id)->where('type', 'engagement')->where('status', 'validated')->count());
    }

    public function test_patrimoine_mouvements_et_inventaire(): void
    {
        $w = $this->world();
        $svc = app(AssetService::class);
        $pc = $svc->create(['asset_category_id' => AssetCategory::where('code', 'ORDI')->value('id'), 'designation' => 'Ordinateur portable', 'value' => '850', 'condition' => 'new', 'org_unit_id' => $w['srv']->id, 'acquisition_date' => '2026-01-15'], $this->u['asset']);
        $svc->transfer($pc, ['to_unit_id' => $w['fs']->id, 'reference' => 'TR-1'], $this->u['asset']);
        $svc->changeCondition($pc->fresh(), 'fair', $this->u['asset'], 'Usure');
        $bench = $svc->create(['asset_category_id' => AssetCategory::where('code', 'BANC')->value('id'), 'designation' => 'Banc d’amphithéâtre', 'quantity' => 40, 'org_unit_id' => $w['fs']->id], $this->u['asset']);
        $inv = $svc->openInventory(['label' => 'Inventaire annuel', 'org_unit_id' => $w['fs']->id], $this->u['asset']);
        $svc->check($inv, $pc->fresh(), true, 'poor', 'Salle 12', null, $this->u['asset']);
        $svc->check($inv, $bench, false, null, null, null, $this->u['asset']);
        $stats = $svc->closeInventory($inv->fresh(), $this->u['asset']);
        $this->assertSame(['checked' => 2, 'missing' => 1, 'condition_changed' => 1], $stats);
        $this->assertSame('poor', $pc->fresh()->condition);
        $this->assertSame(['entry', 'transfer', 'condition', 'inventory'], AssetMovement::where('asset_id', $pc->id)->orderBy('id')->pluck('type')->all());
        $svc->retire($pc->fresh(), 'reform', 'PV-REF-01', $this->u['asset']);
        $this->expectException(BusinessException::class);
        $svc->transfer($pc->fresh(), ['to_unit_id' => $w['fd']->id], $this->u['asset']);
    }

    public function test_statistiques_scientifiques(): void
    {
        $w = $this->world();
        $y1 = AcademicYear::create(['code' => '2024-2025', 'starts_on' => '2024-10-01', 'ends_on' => '2025-09-30']);
        $y2 = AcademicYear::create(['code' => '2025-2026', 'starts_on' => '2025-10-01', 'ends_on' => '2026-09-30', 'is_current' => true]);
        $a = $this->employee($w['dep']);
        $b = $this->employee($w['dep'], ['grade' => 'CT']);
        $c = $this->employee($w['fd'], ['grade' => 'PA']);
        $q = fn ($e, $nature, $year, $unit = null) => Qualification::create(['employee_id' => $e->id, 'nature' => $nature, 'academic_year_id' => $year->id, 'org_unit_id' => $unit?->id, 'title' => 'Travail fictif']);
        $q($a, 'dea', $y1);
        $q($a, 'research_thesis', $y1);
        $q($b, 'dea', $y2);
        $q($b, 'doctoral_thesis', $y2, $w['dep']);
        $q($c, 'doctoral_thesis', $y1, $w['fd']);
        $q($c, 'doctorate', $y1);
        app(CareerService::class)->propose($b, ['type' => 'promotion', 'new_grade_id' => $this->grade('PA')->id, 'allow_family_change' => true, 'effective_date' => '2026-10-01', 'decision_date' => '2026-09-01', 'decision_number' => 'P-1', 'signing_authority' => 'Recteur'], $this->u['clerk']);
        $s = app(StatsService::class)->scientific($this->u['hr'], []);
        $this->assertSame(2, $s['total_dea']);
        $this->assertSame(1, $s['total_research_theses']);
        $this->assertSame(2, $s['total_theses']);
        $this->assertSame(2, $s['total_doctors']);
        $this->assertSame(['2024-2025' => 1, '2025-2026' => 1], $s['theses_by_year']);
        $this->assertSame(['2024-2025' => 1, '2025-2026' => 1], $s['dea_by_year']);
        $this->assertSame(1, $s['theses_by_faculty']['Faculté des Sciences (test)']);
        $this->assertSame(1, $s['theses_by_department']['Département de Chimie (test)']);
        $this->assertSame(1, $s['doctors_scientific']);
        $this->assertSame(1, $s['doctors_promotion_candidates']);
        $dash = app(StatsService::class)->dashboard($this->u['hr'], ['year' => $y2->id]);
        $this->assertSame(3, $dash['total']);
        $this->assertSame(2, $dash['doctors']);
        $this->assertNull($dash['finance']); // l'administrateur RH n'a pas accès aux états financiers
        $this->assertNotNull(app(StatsService::class)->dashboard($this->u['fin'], ['year' => $y2->id])['finance']);
    }

    public function test_soutien_social_deuil_selon_parente_et_circuit(): void
    {
        $w = $this->world();
        KinshipDegree::where('code', 'PERE')->update(['support_amount' => '250.00']);
        $e = $this->employee($w['dep']);
        $svc = app(SocialSupportService::class);
        $type = SupportType::where('code', 'DEUIL')->first();
        $s = $svc->request($e, ['support_type_id' => $type->id, 'kinship_degree_id' => KinshipDegree::where('code', 'PERE')->value('id'), 'deceased_name' => 'Père (fictif)', 'event_date' => '2026-05-02'], $this->u['clerk']);
        $this->assertSame('250.00', (string) $s->amount_requested);
        $svc->approve($s, null, 'DEC-SOC-01', $this->u['hr']);
        try {
            $svc->pay($s->fresh(), '2026-05-10', 'VIR-9', $this->u['clerk']);
            $this->fail('Paiement par un utilisateur non habilité');
        } catch (AuthorizationException) {
        }
        $svc->pay($s->fresh(), '2026-05-10', 'VIR-9', $this->u['fin']);
        $this->assertSame('paid', $s->fresh()->status);
    }

    public function test_demande_de_correction_par_l_agent(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $agent = $this->makeUser('agent.c', 'agent');
        $agent->update(['employee_id' => $e->id]);
        $svc = app(CorrectionService::class);
        $r = $svc->submit($e, ['field' => 'phone', 'requested_value' => '+243 970 000 000', 'justification' => 'Nouveau numéro'], $agent->fresh());
        $svc->handle($r, true, 'Mise à jour effectuée', $this->u['clerk']);
        $this->assertSame('+243 970 000 000', $e->fresh()->phone);
        $other = $this->employee($w['dep']);
        $this->expectException(BusinessException::class);
        $svc->submit($other, ['field' => 'phone', 'requested_value' => 'x', 'justification' => 'y'], $agent->fresh());
    }
}
