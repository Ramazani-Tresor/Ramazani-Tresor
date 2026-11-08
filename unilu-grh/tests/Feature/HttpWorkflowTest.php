<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\CareerEvent;
use App\Models\CorrectionRequest;
use App\Models\Death;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Grade;
use App\Models\Inventory;
use App\Models\KinshipDegree;
use App\Models\Mutation;
use App\Models\PrimeEntitlement;
use App\Models\PrimePayment;
use App\Models\PrimeType;
use App\Models\Role;
use App\Models\SituationType;
use App\Models\SocialSupport;
use App\Models\SupportType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HttpWorkflowTest extends TestCase
{
    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('decision.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_parcours_complet_par_formulaires(): void
    {
        $w = $this->world();
        AcademicYear::create(['code' => '2026-2027', 'starts_on' => '2026-10-01', 'ends_on' => '2027-09-30', 'is_current' => true]);
        $hr = $this->u['hr'];
        $hr2 = $this->u['hr2'];
        $clerk = $this->u['clerk'];
        $ass = Grade::where('code', 'ASS')->first();

        // Dossier, photo, pièce, banque
        $this->actingAs($clerk)->post(route('employees.store'), ['last_name' => 'FORMULAIRE', 'first_name' => 'Test', 'sex' => 'F', 'staff_family' => 'scientific', 'grade_id' => $ass->id, 'engagement_date' => '2025-10-01', 'org_unit_id' => $w['dep']->id, 'email' => 'form@test.invalid'])->assertRedirect();
        $e = Employee::where('last_name', 'FORMULAIRE')->firstOrFail();
        $this->assertSame($w['dep']->id, $e->currentUnitId());
        $this->actingAs($clerk)->put(route('employees.update', $e), ['last_name' => 'FORMULAIRE', 'first_name' => 'Test', 'sex' => 'F', 'staff_family' => 'scientific', 'phone' => '+243 1'])->assertRedirect();
        $this->assertSame('+243 1', $e->fresh()->phone);
        $photo = UploadedFile::fake()->image('photo.jpg', 120, 160);
        $this->actingAs($clerk)->post(route('employees.photo', $e), ['photo' => $photo])->assertRedirect();
        $this->actingAs($clerk)->get(route('employees.photo.show', $e))->assertOk();
        $this->actingAs($clerk)->post(route('documents.store', $e), ['document' => $this->pdf(), 'title' => 'Acte d’engagement'])->assertRedirect();
        $doc = EmployeeDocument::where('employee_id', $e->id)->firstOrFail();
        $this->actingAs($clerk)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($this->u['asset'])->get(route('documents.download', $doc))->assertForbidden();
        $bad = UploadedFile::fake()->createWithContent('script.pdf', '<?php echo 1;');
        $this->actingAs($clerk)->post(route('documents.store', $e), ['document' => $bad, 'title' => 'Faux PDF'])->assertSessionHas('error');
        $this->actingAs($this->u['fin'])->put(route('employees.bank', $e), ['bank_name' => 'Banque fictive', 'bank_account' => '1234'])->assertRedirect();
        $this->actingAs($clerk)->put(route('employees.bank', $e), ['bank_account' => '9999'])->assertForbidden();

        // Carrière avec pièce, validation par un tiers
        $this->actingAs($clerk)->post(route('career.store', $e), ['type' => 'promotion', 'new_grade_id' => Grade::where('code', 'CT')->value('id'), 'effective_date' => '2026-11-01', 'decision_date' => '2026-10-20', 'decision_number' => 'REC/2026/77', 'signing_authority' => 'Le Recteur', 'document' => $this->pdf()])->assertRedirect();
        $ev = CareerEvent::where('employee_id', $e->id)->where('decision_number', 'REC/2026/77')->firstOrFail();
        $this->assertNotNull($ev->document_id);
        $this->actingAs($hr)->post(route('career.validate', $ev))->assertRedirect();
        $this->assertSame('validated', $ev->fresh()->status);

        // Diplôme
        $this->actingAs($clerk)->post(route('qualifications.store', $e), ['nature' => 'dea', 'title' => 'DEA', 'defense_date' => '2026-10-15'])->assertRedirect();
        $this->assertSame('2026-2027', $e->qualifications()->first()->academicYear?->code);

        // Affectation
        $this->actingAs($clerk)->post(route('assignments.store', $e), ['org_unit_id' => $w['fs']->id, 'start_date' => '2026-11-02', 'reason' => 'Réorganisation', 'is_primary' => '1'])->assertRedirect();
        $this->assertSame($w['fs']->id, $e->fresh()->currentUnitId());

        // Situation → approbation → clôture
        $this->actingAs($clerk)->post(route('situations.store', $e), ['situation_type_id' => SituationType::where('code', 'CONGE_MALADIE')->value('id'), 'start_date' => now()->subDay()->toDateString(), 'planned_end_date' => now()->addDays(5)->toDateString(), 'reason' => 'Maladie'])->assertRedirect();
        $s = $e->situations()->firstOrFail();
        $this->actingAs($hr)->post(route('situations.approve', $s))->assertRedirect();
        $this->assertSame('on_leave', $e->fresh()->status);
        $this->actingAs($hr)->post(route('situations.close', $s), ['end_date' => now()->toDateString()])->assertRedirect();
        $this->assertSame('active', $e->fresh()->status);

        // Primes : taux, génération, paiement, validation
        $this->actingAs($this->u['fin'])->post(route('rates.store'), ['prime_type_id' => PrimeType::where('code', 'PRIME_MENS')->value('id'), 'amount' => '400', 'currency' => 'USD', 'staff_family' => 'scientific'])->assertRedirect();
        $this->actingAs($this->u['fin'])->post(route('primes.generate.run'), ['prime_type_id' => PrimeType::where('code', 'PRIME_MENS')->value('id'), 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'period_label' => '2026-10'])->assertRedirect();
        $ent = PrimeEntitlement::where('employee_id', $e->id)->firstOrFail();
        $this->actingAs($this->u['fin'])->post(route('primes.record', $ent), ['kind' => 'advance', 'amount' => '150', 'payment_date' => now()->toDateString(), 'mode' => 'cash'])->assertRedirect();
        $p = PrimePayment::where('prime_entitlement_id', $ent->id)->firstOrFail();
        $this->actingAs($this->u['fin2'])->post(route('payments.validate', $p))->assertRedirect();
        $this->assertSame('validated', $p->fresh()->status);
        $this->actingAs($this->u['fin'])->get(route('primes.show', $ent))->assertOk()->assertSee('150,00');

        // Soutien social
        KinshipDegree::where('code', 'MERE')->update(['support_amount' => '300']);
        $this->actingAs($clerk)->post(route('social.store'), ['matricule' => $e->matricule, 'support_type_id' => SupportType::where('code', 'DEUIL')->value('id'), 'kinship_degree_id' => KinshipDegree::where('code', 'MERE')->value('id'), 'deceased_name' => 'Mère (fictive)', 'event_date' => now()->subDays(3)->toDateString(), 'document' => $this->pdf()])->assertRedirect();
        $sup = SocialSupport::where('employee_id', $e->id)->firstOrFail();
        $this->assertSame(1, $sup->documents()->count());
        $this->actingAs($hr)->post(route('social.approve', $sup), ['decision_reference' => 'SOC-1'])->assertRedirect();
        $this->actingAs($this->u['fin'])->post(route('social.pay', $sup), ['paid_on' => now()->toDateString(), 'payment_reference' => 'VIR-SOC'])->assertRedirect();
        $this->assertSame('paid', $sup->fresh()->status);

        // Correction demandée par l'agent
        $agent = $this->makeUser('agent.http', 'agent');
        $agent->update(['employee_id' => $e->id]);
        $this->actingAs($agent->fresh())->post(route('me.corrections.store'), ['field' => 'address', 'requested_value' => 'Avenue corrigée', 'justification' => 'Déménagement'])->assertRedirect();
        $cr = CorrectionRequest::firstOrFail();
        $this->actingAs($clerk)->post(route('corrections.handle', $cr), ['decision' => 'accept', 'response' => 'OK'])->assertRedirect();
        $this->assertSame('Avenue corrigée', $e->fresh()->address);
        $this->actingAs($agent->fresh())->get(route('me.finances'))->assertOk()->assertSee('150,00');
        $this->assertGreaterThan(0, $agent->notifications()->count());

        // Mutation externe et décès sur un autre agent
        $m2 = $this->employee($w['fd']);
        $this->actingAs($clerk)->post(route('mutations.store', $m2), ['kind' => 'external', 'destination_institution' => 'Université X (fictive)', 'reason' => 'Demande', 'decision_date' => '2026-10-01', 'effective_date' => '2026-10-20', 'decision_reference' => 'MUT-HTTP'])->assertRedirect();
        $mut = Mutation::where('employee_id', $m2->id)->firstOrFail();
        $this->actingAs($hr)->post(route('mutations.validate', $mut))->assertRedirect();
        $this->assertSame('transferred_out', $m2->fresh()->status);
        $d2 = $this->employee($w['fd']);
        $this->actingAs($clerk)->post(route('deaths.store', $d2), ['death_date' => now()->subDays(1)->toDateString(), 'death_place' => 'Likasi', 'document' => $this->pdf()])->assertRedirect();
        $death = Death::where('employee_id', $d2->id)->firstOrFail();
        $this->actingAs($hr2)->post(route('deaths.validate', $death))->assertRedirect();
        $this->assertSame('deceased', $d2->fresh()->status);

        // Recrutement jusqu'à l'engagement
        $this->actingAs($clerk)->post(route('recruitment.store'), ['last_name' => 'CANDIDATE', 'sex' => 'F', 'staff_family' => 'administrative', 'org_unit_id' => $w['srv']->id])->assertRedirect();
        $c = Candidate::firstOrFail();
        $this->actingAs($clerk)->post(route('recruitment.upload', $c), ['title' => 'CV', 'document' => $this->pdf()])->assertRedirect();
        $this->actingAs($clerk)->post(route('recruitment.status', $c), ['status' => 'under_review'])->assertRedirect();
        $this->actingAs($hr)->post(route('recruitment.status', $c), ['status' => 'validated'])->assertRedirect();
        $this->actingAs($hr)->post(route('recruitment.engage', $c), ['decision_number' => 'ENG-HTTP', 'decision_date' => '2026-10-01', 'signing_authority' => 'Le Recteur', 'engagement_date' => '2026-10-15', 'org_unit_id' => $w['srv']->id])->assertRedirect();
        $this->assertNotNull($c->fresh()->employee_id);

        // Patrimoine et inventaire
        $this->actingAs($this->u['asset'])->post(route('assets.store'), ['asset_category_id' => AssetCategory::where('code', 'TABLE')->value('id'), 'designation' => 'Table de réunion', 'condition' => 'good', 'org_unit_id' => $w['srv']->id])->assertRedirect();
        $a = Asset::firstOrFail();
        $this->actingAs($this->u['asset'])->post(route('assets.transfer', $a), ['to_unit_id' => $w['fs']->id, 'movement_date' => now()->toDateString()])->assertRedirect();
        $this->actingAs($this->u['asset'])->post(route('assets.condition', $a), ['condition' => 'fair'])->assertRedirect();
        $this->actingAs($this->u['asset'])->post(route('inventories.store'), ['label' => 'Inventaire test', 'started_on' => now()->toDateString()])->assertRedirect();
        $inv = Inventory::firstOrFail();
        $this->actingAs($this->u['asset'])->post(route('inventories.check', $inv), ['asset_id' => $a->id, 'found' => '1', 'observed_condition' => 'poor'])->assertRedirect();
        $this->actingAs($this->u['asset'])->post(route('inventories.close', $inv))->assertRedirect();
        $this->assertSame('poor', $a->fresh()->condition);
        $this->actingAs($this->u['asset'])->post(route('assets.retire', $a), ['type' => 'scrap', 'reference' => 'PV-1'])->assertRedirect();
        $this->assertSame('scrapped', $a->fresh()->status);

        // Paramétrage, utilisateurs, jetons, structure
        $admin = $this->u['admin'];
        $admin->update(['must_change_password' => false]); // compte initial : mot de passe temporaire déjà remplacé
        $this->actingAs($admin)->post(route('refs.store', 'grades'), ['family' => 'administrative', 'code' => 'NEW', 'name' => 'Nouveau grade', 'rank' => 1, 'is_active' => '1'])->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');
        $g = Grade::where('code', 'NEW')->firstOrFail();
        $this->actingAs($admin)->put(route('refs.update', ['grades', $g->id]), ['family' => 'administrative', 'code' => 'NEW', 'name' => 'Grade renommé', 'rank' => 2, 'is_active' => '0'])->assertRedirect();
        $this->assertFalse($g->fresh()->is_active);
        $this->actingAs($admin)->post(route('refs.store', 'annees'), ['code' => '2027-2028', 'starts_on' => '2027-10-01', 'ends_on' => '2028-09-30', 'status' => 'planned', 'is_current' => '1'])->assertRedirect();
        $this->assertSame(1, AcademicYear::where('is_current', true)->count());
        $this->assertSame(2, AcademicYear::count()); // l'année précédente est conservée (aucun historique supprimé)
        $this->actingAs($admin)->put(route('settings.update'), ['university_name' => 'Université de test', 'segregation_of_duties' => '1', 'max_upload_kb' => '4096'])->assertRedirect();
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Nouvel utilisateur', 'username' => 'nouveau', 'role_id' => Role::where('slug', 'service_head')->value('id')])->assertRedirect();
        $nu = User::where('username', 'nouveau')->firstOrFail();
        $this->assertTrue($nu->must_change_password);
        $this->actingAs($admin)->post(route('users.assign', $nu), ['role_id' => Role::where('slug', 'faculty_head')->value('id'), 'org_unit_id' => $w['fs']->id])->assertRedirect();
        $this->actingAs($admin)->post(route('users.assign', $admin), ['role_id' => Role::where('slug', 'agent')->value('id')])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('tokens.store'), ['user_id' => $nu->id, 'name' => 'Intégration'])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->post(route('structure.store'), ['name' => 'Nouveau service', 'code' => 'NSRV', 'type' => 'admin_service', 'parent_id' => $w['rect']->id])->assertRedirect();
        $this->actingAs($admin)->put(route('roles.update', Role::where('slug', 'service_head')->first()), ['permissions' => ['self.view', 'employees.directory']])->assertRedirect();
        $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee('Intégrité vérifiée');
    }

    public function test_connexion_et_deconnexion(): void
    {
        $this->world();
        $this->post(route('login.attempt'), ['username' => 'rh', 'password' => 'mauvais'])->assertSessionHas('error');
        $this->post(route('login.attempt'), ['username' => 'rh', 'password' => 'Secret!2026grh'])->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.login')->exists());
        $this->assertTrue(AuditLog::where('action', 'auth.failed')->exists());
    }
}
