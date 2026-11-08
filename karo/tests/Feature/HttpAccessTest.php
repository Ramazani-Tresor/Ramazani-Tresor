<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PaymentService;
use App\Services\TotpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class HttpAccessTest extends TestCase
{
    public function test_refus_d_acces_hors_habilitation(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p = $this->pay($s, '100', [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '100']], null, false);
        // Comptable de saisie : pas de validation, pas de paramètres, pas d'utilisateurs
        $this->as($w['clerk'])->post(route('payments.validate', $p))->assertForbidden();
        $this->as($w['clerk'])->get(route('settings.index'))->assertForbidden();
        $this->as($w['clerk'])->get(route('users.index'))->assertForbidden();
        $this->as($w['clerk'])->get(route('audit.index'))->assertForbidden();
        // Administrateur technique : aucune approbation financière
        $this->as($w['tech'])->post(route('payments.validate', $p))->assertForbidden();
        $this->as($w['tech'])->get(route('payments.entry'))->assertForbidden();
        $this->as($w['tech'])->get(route('users.index'))->assertOk();
        $this->assertSame('submitted', $p->fresh()->status);
    }

    public function test_cumul_administration_technique_et_approbation_interdit(): void
    {
        $w = $this->world();
        $admin2 = $this->makeUser('tech_admin');
        $budgetRole = Role::query()->where('slug', 'budget_admin')->first();
        $this->as($admin2)->post(route('users.assignments.store', $w['tech']), ['role_id' => $budgetRole->id])->assertSessionHas('error');
        $this->assertFalse($w['tech']->fresh()->hasPermission('payments.validate'));
        $this->as($w['tech'])->post(route('users.assignments.store', $w['tech']), ['role_id' => $budgetRole->id])->assertSessionHas('error'); // ses propres droits
        $techRole = Role::query()->where('slug', 'tech_admin')->first();
        $this->as($admin2)->put(route('roles.update', $techRole), ['permissions' => Permission::query()->whereIn('slug', ['users.manage', 'payments.validate'])->pluck('id')->all()])->assertSessionHas('error');
    }

    public function test_perimetre_par_faculte_contre_la_manipulation_d_identifiants(): void
    {
        $w = $this->world();
        $clerkDrt = $this->makeUser('entry_clerk', $w['fac_drt']->id);
        $drt = $this->student($w['drt_l1']);
        $sci = $this->student($w['sci_l1']);
        $this->as($clerkDrt)->get(route('students.show', $drt))->assertOk();
        $this->as($clerkDrt)->get(route('students.show', $sci))->assertForbidden();       // URL forgée
        $this->as($clerkDrt)->get(route('students.index', ['year' => $w['year']->id]))->assertSee($drt->matricule)->assertDontSee($sci->matricule);
        $this->as($clerkDrt)->get(route('payments.entry', ['year' => $w['year']->id, 'faculty' => $w['fac_sci']->id]))->assertForbidden();
    }

    public function test_affichage_des_etudiants_apres_filtrage_faculte_et_promotion(): void
    {
        $w = $this->world();
        $a = $this->student($w['drt_l1']);
        $b = $this->student($w['drt_l3'], 'finalist');
        $c = $this->student($w['sci_l1']);
        $r = $this->as($w['clerk'])->get(route('payments.entry', ['year' => $w['year']->id, 'faculty' => $w['fac_drt']->id, 'promotion' => $w['drt_l1']->id]));
        $r->assertOk()->assertSee($a->matricule)->assertDontSee($b->matricule)->assertDontSee($c->matricule);
        $this->as($w['clerk'])->get(route('payments.entry', ['year' => $w['year']->id, 'faculty' => $w['fac_drt']->id, 'student' => $a->id]))
            ->assertOk()->assertSee('Situation de')->assertSee('Minerval')->assertSee('Soumettre au contrôle');
    }

    public function test_saisie_http_d_un_bordereau_reparti(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $file = UploadedFile::fake()->createWithContent('bordereau.pdf', "%PDF-1.4\nbordereau");
        $this->as($w['clerk'])->post(route('payments.store'), [
            'client_uuid' => (string) Str::uuid(), 'academic_year_id' => $w['year']->id, 'student_id' => $s->id, 'bank_account_id' => $w['acc']->id,
            'bank_reference' => 'HTTP-1', 'deposit_date' => '2025-11-10', 'amount' => '150', 'proof' => $file,
            'allocations' => [$this->charge($s, 'CONNEXES')->id => '100', $this->charge($s, 'INSCR-NOUV')->id => '50'],
        ])->assertRedirect();
        $p = Payment::query()->sole();
        $this->assertSame(2, $p->allocations()->count());
        $this->assertNotNull($p->proof_path);
        $this->as($w['validator'])->get(route('payments.proof', $p))->assertOk();
    }

    public function test_connexion_avec_second_facteur(): void
    {
        $w = $this->world();
        $u = $w['validator'];
        $this->post(route('login.attempt'), ['username' => $u->username, 'password' => 'Secret!2026'])->assertRedirect(route('home'));
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.verify'), ['code' => app(TotpService::class)->code($u->two_factor_secret)])->assertRedirect();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_mot_de_passe_temporaire_doit_etre_change(): void
    {
        $w = $this->world();
        $w['clerk']->forceFill(['must_change_password' => true])->save();
        $this->as($w['clerk'])->get(route('students.index'))->assertRedirect(route('account.show'));
    }

    public function test_aucune_ressource_externe_fonctionnement_local(): void
    {
        $w = $this->world();
        $html = $this->as($w['budget'])->get(route('dashboard'))->assertOk()->getContent();
        preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/', $html, $m);
        $external = array_filter($m[1], fn ($u) => ! str_starts_with($u, config('app.url')) && ! str_starts_with($u, 'http://localhost'));
        $this->assertSame([], array_values($external), 'Aucun script, style ou police ne doit venir d’Internet');
        $this->assertStringContainsString("default-src 'self'", $this->as($w['budget'])->get(route('dashboard'))->headers->get('Content-Security-Policy'));
    }

    public function test_verification_publique_d_un_document_avec_donnees_minimales(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p = $this->pay($s, '100', [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '100']]);
        $this->as($w['clerk'])->post(route('documents.issue', $s), ['type' => 'receipt', 'payment_id' => $p->id])->assertRedirect();
        $doc = IssuedDocument::query()->sole();
        $this->assertSame('final', $doc->status);
        auth()->logout();
        $this->get(route('documents.verify', $doc->verification_token))->assertOk()->assertSee('authentique')->assertDontSee($s->last_name)->assertSee($s->maskedName());
        app(PaymentService::class)->reverse($p, $w['budget'], 'Erreur');
        $this->get(route('documents.verify', $doc->verification_token))->assertSee('ANNULÉ');
    }
}
