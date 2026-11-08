<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Employee;
use App\Models\Mandate;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\AlertService;
use App\Services\BackupService;
use App\Services\CareerService;
use App\Services\SituationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    /** Exécuté dans un processus séparé : le test bascule la connexion vers un fichier SQLite réel. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_sauvegarde_chiffree_copie_separee_et_restauration(): void
    {
        $dir = storage_path('framework/testing/backup_'.uniqid());
        File::ensureDirectoryExists($dir);
        $db = $dir.'/grh.sqlite';
        touch($db);
        config([
            'database.connections.sqlite.database' => $db,
            'grh.backup.key' => base64_encode(random_bytes(32)), 'grh.backup.path' => $dir.'/main', 'grh.backup.secondary_path' => $dir.'/copy',
        ]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--seed' => true, '--force' => true]);
        $this->world();
        $e = $this->employee(OrgUnit::where('code', 'FSCI')->first());
        $svc = app(BackupService::class);
        $r = $svc->create();
        $this->assertCount(2, $r['copies']);
        $this->assertFileExists($dir.'/copy/'.$r['name']);
        $raw = file_get_contents($r['copies'][0]);
        $this->assertStringStartsWith('UGRH1', $raw);
        $this->assertStringNotContainsString($e->matricule, $raw); // contenu chiffré
        $e->update(['phone' => 'MODIFIÉ APRÈS SAUVEGARDE']);
        $svc->restore($r['copies'][0]);
        $this->assertNotSame('MODIFIÉ APRÈS SAUVEGARDE', Employee::find($e->id)->phone);
        config(['grh.backup.key' => base64_encode(random_bytes(32))]);
        try {
            $svc->restore($r['copies'][0]);
            $this->fail('Restauration avec une mauvaise clé acceptée');
        } catch (BusinessException $ex) {
            $this->assertStringContainsString('clé incorrecte', $ex->getMessage());
        }
        File::deleteDirectory($dir);
    }

    public function test_alertes_d_echeance_de_mandat_et_de_situation(): void
    {
        $w = $this->world();
        $e = $this->employee($w['dep']);
        $agent = $this->makeUser('agent.alert', 'agent');
        $agent->update(['employee_id' => $e->id]);
        $cs = app(CareerService::class);
        $ev = $cs->propose($e, ['type' => 'mandate_renewal', 'new_mandate_id' => Mandate::where('code', 'ASS_M1')->value('id'), 'new_mandate_end' => now()->addDays(30)->toDateString(), 'effective_date' => now()->subYear()->toDateString(), 'decision_date' => now()->subYear()->toDateString(), 'decision_number' => 'M-ALERT', 'signing_authority' => 'Recteur'], $this->u['clerk']);
        $cs->validate($ev, $this->u['hr']);
        $other = $this->employee($w['dep']);
        $ss = app(SituationService::class);
        $s = $ss->request($other, ['situation_type_id' => $this->situationType('CONGE_ANNUEL')->id, 'start_date' => now()->subDays(10)->toDateString(), 'planned_end_date' => now()->addDays(3)->toDateString(), 'reason' => 'Congé'], $this->u['clerk']);
        $ss->approve($s, $this->u['hr']);
        $r = app(AlertService::class)->run();
        $this->assertSame(1, $r['mandates']);
        $this->assertSame(1, $r['situations']);
        $this->assertTrue($agent->fresh()->notifications()->where('data', 'like', '%mandate_due%')->exists());
        $this->assertTrue(User::where('username', 'rh')->first()->notifications()->where('data', 'like', '%situation_due%')->exists());
        $again = app(AlertService::class)->run(); // pas de doublon d'alerte
        $this->assertSame(0, $again['mandates'] + $again['situations']);
        $this->artisan('grh:alerts')->assertSuccessful();
        $this->artisan('grh:audit-verify')->assertSuccessful();
    }
}
