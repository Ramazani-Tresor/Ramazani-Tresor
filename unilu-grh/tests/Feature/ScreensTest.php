<?php

namespace Tests\Feature;

use App\Http\Controllers\ReferenceController;
use App\Models\ApiToken;
use App\Models\Asset;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OrgUnit;
use App\Models\PrimeEntitlement;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['grh.demo_mode' => true]);
        $this->seed(DemoSeeder::class);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }

    public function test_tous_les_ecrans_par_role(): void
    {
        $emp = Employee::query()->where('matricule', 'DEMO-0001')->first();
        $params = [
            'employee' => $emp->id, 'unit' => OrgUnit::query()->where('code', 'FSCI')->value('id'), 'entitlement' => PrimeEntitlement::query()->value('id'),
            'candidate' => Candidate::query()->value('id'), 'asset' => Asset::query()->value('id'), 'inventory' => Inventory::query()->value('id'),
            'ref' => 'grades', 'user' => $this->user('rh')->id,
        ];
        $skip = ['documents.download', 'employees.photo.show', 'backups.download', 'recruitment.document', 'reports.export', 'employees.sheet', 'me.sheet', 'login'];
        $admin = $this->user('admin');
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! in_array('GET', $route->methods(), true) || in_array($name, $skip, true) || str_starts_with($route->uri(), 'api/') || str_starts_with($route->uri(), 'livewire') || ! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }
            $needed = array_intersect_key($params, array_flip($route->parameterNames()));
            if (count($needed) !== count($route->parameterNames())) {
                continue;
            }
            $actor = str_starts_with($name, 'me.') ? $this->user('agent') : $admin;
            $res = $this->actingAs($actor)->get(route($name, $needed));
            $this->assertContains($res->status(), [200, 302], "Écran {$name} : HTTP {$res->status()}");
            $checked++;
        }
        $this->assertGreaterThanOrEqual(43, $checked); // toutes les pages GET authentifiées (hors téléchargements testés à part)
        foreach (array_keys(ReferenceController::REFS) as $ref) {
            $this->actingAs($admin)->get(route('refs.index', $ref))->assertOk();
        }
        foreach (['rh', 'gestionnaire', 'finance', 'patrimoine', 'doyen.sciences', 'acad', 'chef.personnel', 'agent'] as $u) {
            $this->actingAs($this->user($u))->get('/')->assertRedirect();
        }
        $this->actingAs($this->user('finance'))->get(route('dashboard'))->assertOk()->assertSee('Primes payées');
        // Livewire réellement actif (composants rendus, directives compilées)
        $list = $this->actingAs($this->user('rh'))->get(route('employees.index'))->assertOk();
        $list->assertDontSee('<livewire:', false)->assertDontSee('@livewire', false)->assertSee('wire:id', false)->assertSee('DEMO-0001');
        $this->actingAs($this->user('rh'))->get(route('employees.show', $emp))->assertOk()->assertSee('DEMO-0001')->assertDontSee('Numéro de compte');
    }

    public function test_editions_pdf_excel_et_fiche(): void
    {
        $fin = $this->user('finance');
        foreach (['personnel', 'personnel_grade', 'personnel_faculty', 'promotions', 'assignments', 'mutations', 'departures', 'scientific', 'annual'] as $t) {
            $this->actingAs($this->user('rh'))->get(route('reports.export', [$t, 'html']))->assertOk();
        }
        foreach (['primes', 'advances', 'arrears', 'social'] as $t) {
            $this->actingAs($fin)->get(route('reports.export', [$t, 'html']))->assertOk();
            $this->actingAs($this->user('rh'))->get(route('reports.export', [$t, 'html']))->assertForbidden();
        }
        $pdf = $this->actingAs($this->user('rh'))->get(route('reports.export', ['personnel_grade', 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $x = $this->actingAs($fin)->get(route('reports.export', ['primes', 'xlsx']));
        $x->assertOk();
        $this->assertStringContainsString('spreadsheetml', $x->headers->get('content-type'));
        $this->actingAs($this->user('patrimoine'))->get(route('reports.export', ['assets', 'pdf']))->assertOk();
        $sheet = $this->actingAs($this->user('rh'))->get(route('employees.sheet', Employee::query()->where('matricule', 'DEMO-0001')->first()));
        $sheet->assertOk();
        $this->assertStringStartsWith('%PDF', $sheet->getContent());
        $this->actingAs($this->user('agent'))->get(route('me.sheet'))->assertOk();
    }

    public function test_cloisonnement_des_acces(): void
    {
        $emp = Employee::query()->where('matricule', 'DEMO-0006')->first(); // Faculté de Droit
        $sci = Employee::query()->where('matricule', 'DEMO-0001')->first(); // Chimie (Faculté des Sciences)
        $dean = $this->user('doyen.sciences');
        $this->actingAs($dean)->get(route('employees.show', $sci))->assertOk();
        $this->actingAs($dean)->get(route('employees.show', $emp))->assertForbidden(); // identifiant manipulé
        $this->actingAs($this->user('patrimoine'))->get(route('primes.index'))->assertForbidden();
        $this->actingAs($this->user('patrimoine'))->get(route('employees.show', $sci))->assertForbidden();
        $this->actingAs($this->user('agent'))->get(route('employees.show', $sci))->assertForbidden();
        $this->actingAs($this->user('agent'))->get(route('dashboard'))->assertForbidden();
        $this->actingAs($this->user('finance'))->post(route('career.store', $sci), [])->assertForbidden();
        $this->actingAs($this->user('rh'))->get(route('primes.index'))->assertForbidden();
        // L'agent ne voit que son propre dossier
        $this->actingAs($this->user('agent'))->get(route('me.show'))->assertOk()->assertSee($this->user('agent')->employee->matricule);
    }

    public function test_api_par_jeton_et_perimetre(): void
    {
        $plain = 'ugrh_test_token_value_1234567890';
        ApiToken::create(['user_id' => $this->user('doyen.sciences')->id, 'name' => 'Test', 'token_hash' => hash('sha256', $plain)]);
        $this->getJson('/api/v1/agents')->assertStatus(401);
        $r = $this->withToken($plain)->getJson('/api/v1/agents?par_page=100')->assertOk();
        $units = OrgUnit::subtreeIds(OrgUnit::query()->where('code', 'FSCI')->value('id'));
        $codes = OrgUnit::query()->whereIn('id', $units)->pluck('code')->all();
        foreach ($r->json('data') as $row) {
            $this->assertContains($row['affectation'], $codes);
            $this->assertArrayNotHasKey('banque', $row);
        }
        $this->withToken($plain)->getJson('/api/v1/agents/DEMO-0006')->assertForbidden();
        $this->withToken($plain)->getJson('/api/v1/agents/DEMO-0001')->assertOk()->assertJsonPath('data.matricule', 'DEMO-0001');
        $this->withToken($plain)->getJson('/api/v1/patrimoine')->assertOk(); // le responsable de faculté consulte le patrimoine
        $agentToken = 'ugrh_agent_token_value_0987654321';
        ApiToken::create(['user_id' => $this->user('agent')->id, 'name' => 'Agent', 'token_hash' => hash('sha256', $agentToken)]);
        $this->withToken($agentToken)->getJson('/api/v1/agents')->assertForbidden();
        ApiToken::query()->update(['revoked_at' => now()]);
        $this->withToken($plain)->getJson('/api/v1/agents')->assertStatus(401);
    }

    public function test_securite_http(): void
    {
        $res = $this->get(route('login'));
        $res->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString('http', $csp);
        preg_match_all('#<(?:script|link|img)[^>]+(?:src|href)="([^"]+)"#', $res->getContent(), $m);
        foreach ($m[1] as $url) {
            $this->assertTrue(str_starts_with($url, url('/')) || str_starts_with($url, '/'), "Ressource externe : {$url}");
        }
        $this->get(route('employees.index'))->assertRedirect(route('login'));
        // Mot de passe temporaire : changement obligatoire
        $u = $this->user('rh');
        $u->update(['must_change_password' => true]);
        $this->actingAs($u)->get(route('dashboard'))->assertRedirect(route('account.password'));
        // Document privé : jamais servi sans autorisation
        $this->assertFalse(is_dir(public_path('storage/dossiers')));
    }
}
