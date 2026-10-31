<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Student;
use App\Models\SyncOperation;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\SyncService;
use App\Services\TotpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncSecurityBackupTest extends TestCase
{
    private function payload(Student $s, array $extra = []): array
    {
        return array_merge(['client_uuid' => (string) Str::uuid(), 'matricule' => $s->matricule, 'academic_year_id' => $this->w['year']->id, 'bank_account_id' => $this->w['acc']->id,
            'bank_reference' => 'OFF-'.Str::random(5), 'deposit_date' => '2025-11-10', 'amount' => '100', 'currency' => 'USD',
            'allocations' => [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '100']], 'proof_base64' => base64_encode('%PDF-1.4 bordereau'), 'prepared_at' => now()->subHour()->toIso8601String()], $extra);
    }

    public function test_reprise_de_synchronisation_sans_double_paiement(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $payload = $this->payload($s);
        $svc = app(SyncService::class);
        for ($i = 0; $i < 3; $i++) {                                           // reconnexions successives
            $r = $svc->pushPayment($payload, $w['clerk'], 'poste-compta-2');
            $this->assertSame('accepted', $r['status']);
        }
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('submitted', Payment::query()->value('status'));      // jamais validé hors serveur
        $this->assertSame('offline_sync', Payment::query()->value('channel'));
    }

    public function test_conflit_explicite_et_droits_reverifies(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $svc = app(SyncService::class);
        $svc->pushPayment($this->payload($s, ['bank_reference' => 'DUP-1']), $w['clerk'], 'p1');
        $r = $svc->pushPayment($this->payload($s, ['bank_reference' => 'DUP-1', 'allocations' => []]), $w['clerk'], 'p2');
        $this->assertSame('conflict', $r['status']);
        $this->assertSame(1, Payment::query()->count());
        $op = SyncOperation::query()->where('status', 'conflict')->sole();
        $svc->resolve($op, 'reject', $w['validator'], 'Doublon confirmé');
        $this->assertSame('rejected', $op->fresh()->status);

        $w['clerk']->assignments()->delete();                                  // droits révoqués pendant la déconnexion
        $w['clerk']->flushGrants();
        $r = $svc->pushPayment($this->payload($s), $w['clerk'], 'p1');
        $this->assertSame('rejected', $r['status']);
        $this->assertStringContainsString('Droits insuffisants', $r['messages'][0]);
    }

    public function test_journal_d_audit_chaine_detecte_toute_alteration(): void
    {
        $w = $this->world();
        $this->student($w['sci_l1']);
        $this->assertTrue(AuditService::verifyChain()['ok']);
        $victim = AuditLog::query()->orderBy('id')->skip(2)->first();
        DB::table('audit_logs')->where('id', $victim->id)->update(['description' => 'falsifié']); // accès direct à la base
        $res = AuditService::verifyChain();
        $this->assertFalse($res['ok']);
        $this->assertSame($victim->id, $res['broken_at']);
        $this->expectException(\LogicException::class);
        AuditLog::query()->first()->delete();                                  // l'application refuse toute suppression
    }

    public function test_second_facteur_totp(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $this->assertTrue($totp->verify($secret, $totp->code($secret)));
        $this->assertFalse($totp->verify($secret, '000000') && $totp->code($secret) !== '000000');
    }

    public function test_sauvegarde_chiffree_et_restauration(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'karo').'.sqlite';
        touch($db);
        config(['database.connections.restore_test' => ['driver' => 'sqlite', 'database' => $db, 'prefix' => '', 'foreign_key_constraints' => true]]);
        config(['database.default' => 'restore_test', 'karo.backup.key' => 'base64:'.base64_encode(random_bytes(32)),
            'karo.backup.path' => sys_get_temp_dir().'/karo_bk_'.uniqid(), 'karo.backup.secondary_path' => sys_get_temp_dir().'/karo_bk2_'.uniqid()]);
        DB::purge('restore_test');
        $this->artisan('migrate', ['--database' => 'restore_test', '--force' => true]);
        DB::connection('restore_test')->table('settings')->insert(['key' => 'university_name', 'value' => 'Université Témoin (fictive)']);

        $backup = app(BackupService::class)->create();
        $this->assertCount(2, $backup['copies']);                              // copie séparée
        $raw = file_get_contents($backup['copies'][0]);
        $this->assertStringNotContainsString('Université Témoin', $raw);        // contenu chiffré

        DB::connection('restore_test')->table('settings')->delete();           // incident
        app(BackupService::class)->restore($backup['copies'][1]);
        $this->assertSame('Université Témoin (fictive)', DB::connection('restore_test')->table('settings')->where('key', 'university_name')->value('value'));

        config(['karo.backup.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->expectExceptionMessage('Déchiffrement impossible');
        app(BackupService::class)->restore($backup['copies'][0]);
    }
}
