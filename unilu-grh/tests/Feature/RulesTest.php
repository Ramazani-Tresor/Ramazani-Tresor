<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Mandate;
use App\Models\RecordChange;
use App\Services\AssignmentService;
use App\Services\AuditService;
use App\Services\CareerService;
use App\Services\EmployeeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RulesTest extends TestCase
{
    public function test_matricule_unique(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('matricule unique');
        app(EmployeeService::class)->create(['matricule' => $e->matricule, 'last_name' => 'DOUBLON', 'sex' => 'M', 'staff_family' => 'scientific'], $this->u['hr']);
    }

    public function test_matricule_attribue_automatiquement(): void
    {
        $this->world();
        $e = app(EmployeeService::class)->create(['last_name' => 'NOUVEAU', 'sex' => 'M', 'staff_family' => 'administrative'], $this->u['hr']);
        $this->assertMatchesRegularExpression('/^UNILU-'.date('Y').'-\d{5}$/', $e->matricule);
    }

    public function test_une_seule_affectation_principale_active_et_postes(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $svc = app(AssignmentService::class);
        $svc->assign($e, ['org_unit_id' => $w['fd']->id, 'start_date' => '2026-02-01', 'decision_number' => 'AFF-1'], $this->u['hr']);
        $this->assertSame(1, Assignment::where('employee_id', $e->id)->where('status', 'active')->where('is_primary', true)->count());
        $this->assertSame('2026-01-31', Assignment::where('employee_id', $e->id)->where('status', 'closed')->first()->end_date->toDateString());
        $pos = $this->position($w['fd'], 'Secrétaire de faculté', 1);
        $other = $this->employee($w['dep']);
        $svc->assign($other, ['org_unit_id' => $w['fd']->id, 'position_id' => $pos->id, 'start_date' => '2026-03-01'], $this->u['hr']);
        $this->expectExceptionMessage('entièrement pourvu');
        $svc->assign($e->fresh(), ['org_unit_id' => $w['fd']->id, 'position_id' => $pos->id, 'start_date' => '2026-03-02'], $this->u['hr']);
    }

    public function test_promotion_justifiee_par_decision_et_validee_par_un_tiers(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $svc = app(CareerService::class);
        try {
            $svc->propose($e, ['type' => 'promotion', 'new_grade_id' => $this->grade('CT')->id, 'effective_date' => '2026-01-01', 'decision_date' => '2025-12-15', 'signing_authority' => 'Recteur'], $this->u['clerk']);
            $this->fail('Promotion sans numéro de décision acceptée');
        } catch (BusinessException $ex) {
            $this->assertStringContainsString('décision officielle', $ex->getMessage());
        }
        $ev = $svc->propose($e, ['type' => 'promotion', 'new_grade_id' => $this->grade('CT')->id, 'effective_date' => '2026-01-01', 'decision_date' => '2025-12-15', 'decision_number' => 'REC/2025/0042', 'signing_authority' => 'Recteur'], $this->u['clerk']);
        $this->assertSame($this->grade('ASS')->id, $e->fresh()->grade_id); // pas d'effet avant validation : aucune promotion automatique
        try {
            $svc->validate($ev, $this->u['clerk']);
            $this->fail('Le gestionnaire ne peut pas valider');
        } catch (AuthorizationException) {
        }
        $svc->validate($ev, $this->u['hr']);
        $e->refresh();
        $this->assertSame($this->grade('CT')->id, $e->grade_id);
        $change = RecordChange::where('employee_id', $e->id)->where('field', 'grade_id')->first();
        $this->assertSame('Décision REC/2025/0042', $change->context);
        // Après une décision validée, le grade ne se modifie plus par simple édition du dossier.
        app(EmployeeService::class)->update($e, ['grade_id' => $this->grade('PO')->id, 'phone' => '+243 000 000'], $this->u['hr']);
        $this->assertSame($this->grade('CT')->id, $e->fresh()->grade_id);
        $this->assertSame('+243 000 000', $e->fresh()->phone);
    }

    public function test_renouvellement_de_mandat_et_alerte_d_echeance(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $svc = app(CareerService::class);
        $m1 = Mandate::where('code', 'ASS_M1')->first();
        $ev = $svc->propose($e, ['type' => 'mandate_renewal', 'new_mandate_id' => $m1->id, 'new_mandate_end' => now()->addDays(30)->toDateString(), 'effective_date' => now()->subYear()->toDateString(), 'decision_date' => now()->subYear()->toDateString(), 'decision_number' => 'MAND-1', 'signing_authority' => 'Recteur'], $this->u['clerk']);
        $svc->validate($ev, $this->u['hr']);
        $this->assertTrue($svc->expiringMandates(90)->pluck('id')->contains($e->id));
        $this->assertFalse($svc->expiringMandates(10)->pluck('id')->contains($e->id));
    }

    public function test_gestionnaire_financier_ne_modifie_pas_les_grades(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $this->expectException(AuthorizationException::class);
        app(CareerService::class)->propose($e, ['type' => 'promotion', 'new_grade_id' => $this->grade('CT')->id, 'effective_date' => '2026-01-01', 'decision_date' => '2025-12-15', 'decision_number' => 'X', 'signing_authority' => 'Recteur'], $this->u['fin']);
    }

    public function test_perimetres_faculte_et_famille(): void
    {
        $w = $this->world();
        $inDep = $this->employee($w['dep']);
        $inLaw = $this->employee($w['fd']);
        $prof = $this->employee($w['fd'], ['grade' => 'PO']);
        $dean = $this->u['dean'];
        $this->assertTrue($dean->canOnEmployee('employees.view', $inDep)); // sous-unité de sa faculté
        $this->assertFalse($dean->canOnEmployee('employees.view', $inLaw));
        $q = Employee::query();
        $dean->scopeEmployees($q, 'employees.view');
        $this->assertSame([$inDep->id], $q->pluck('id')->all());
        $acad = $this->u['acad'];
        $this->assertTrue($acad->canOnEmployee('employees.manage', $prof));
        $this->assertFalse($acad->canOnEmployee('employees.manage', $inLaw));
        $this->assertFalse($this->u['asset']->hasPermission('primes.view'));
        $this->assertFalse($this->u['asset']->hasPermission('employees.view'));
    }

    public function test_historique_et_journal_d_audit_infalsifiable(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        app(EmployeeService::class)->updateBank($e, ['bank_name' => 'Banque fictive', 'bank_account' => '0001-0002-0003'], $this->u['fin']);
        $raw = DB::table('employees')->where('id', $e->id)->value('bank_account');
        $this->assertStringNotContainsString('0001-0002-0003', $raw); // chiffré en base
        $this->assertSame('0001-0002-0003', $e->fresh()->bank_account);
        $hist = RecordChange::where('employee_id', $e->id)->where('field', 'bank_account')->first();
        $this->assertSame('[modifié]', $hist->new_value); // jamais en clair dans l'historique
        $this->assertTrue(AuditService::verifyChain()['ok']);
        $log = AuditLog::query()->orderByDesc('id')->first();
        DB::table('audit_logs')->where('id', $log->id)->update(['description' => 'falsifié']);
        $this->assertFalse(AuditService::verifyChain()['ok']);
        $this->expectException(BusinessException::class);
        $log->update(['description' => 'x']);
    }
}
