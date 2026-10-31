<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Payment;
use App\Models\StatementLine;
use App\Services\ReconciliationService;
use App\Services\StatementImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    private function import(string $csv, string $name = 'releve.csv')
    {
        $svc = app(StatementImportService::class);
        $file = UploadedFile::fake()->createWithContent($name, $csv);
        $batch = $svc->upload($file, $this->w['acc'], $this->w['clerk']);
        $svc->preview($batch, $batch->column_map, null, null);

        return $svc->confirm($batch->fresh(), $this->w['clerk']);
    }

    public function test_rapprochement_exact_automatique_et_confirmation_bancaire(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p = $this->pay($s, '150', [['charge_id' => $this->charge($s, 'MINERVAL')->id, 'amount' => '150']], 'BRD-7781');
        $batch = $this->import("date;date_valeur;reference;libelle;credit;debit\n11/11/2025;12/11/2025;BRD-7781;Versement étudiant;150,00;\n12/11/2025;12/11/2025;FRAIS;Frais bancaires;;5,00\n");
        $this->assertSame(1, $batch->rows_created);                          // le débit est ignoré
        $line = StatementLine::query()->sole();
        $this->assertSame('2025-11-12', $line->value_date->toDateString());   // dates d'opération et de valeur conservées
        $stats = app(ReconciliationService::class)->autoMatch($batch, $w['validator']);
        $this->assertSame(1, $stats['matched']);
        $this->assertSame('confirmed', $p->fresh()->bank_status);
        $this->assertSame('matched', $line->fresh()->match_status);
        $this->assertSame(1, Payment::query()->count());          // aucun nouveau paiement créé
    }

    public function test_rapprochement_ambigu_soumis_a_decision_humaine(): void
    {
        $w = $this->world();
        $a = $this->student($w['sci_l1']);
        $b = $this->student($w['sci_l1']);
        // Deux paiements avec la même référence et le même montant (cas ambigu)
        $p1 = $this->pay($a, '100', [['charge_id' => $this->charge($a, 'CONNEXES')->id, 'amount' => '100']], 'GRP-01');
        $this->pay($b, '100', [['charge_id' => $this->charge($b, 'CONNEXES')->id, 'amount' => '100']], 'GRP-01');
        $batch = $this->import("date;reference;libelle;credit\n10/11/2025;GRP-01;Versement groupé;100\n");
        $stats = app(ReconciliationService::class)->autoMatch($batch, $w['validator']);
        $this->assertSame(0, $stats['matched']);
        $this->assertSame(1, $stats['ambiguous']);
        $line = StatementLine::query()->sole();
        $this->assertCount(2, app(ReconciliationService::class)->candidates($line)->where('exact', true));
        app(ReconciliationService::class)->match($line, $p1, '100', $w['validator'], 'Bordereau original présenté');
        $this->assertSame('confirmed', $p1->fresh()->bank_status);
    }

    public function test_rapprochement_partiel_et_multiple(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p1 = $this->pay($s, '60', [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '60']], 'A1');
        $p2 = $this->pay($s, '40', [['charge_id' => $this->charge($s, 'CONNEXES')->id, 'amount' => '40']], 'A2');
        $this->import("date;reference;libelle;credit\n10/11/2025;LOT-9;Dépôt groupé;100\n");
        $line = StatementLine::query()->sole();
        $svc = app(ReconciliationService::class);
        try {
            $svc->match($line, $p1, '60', $w['validator']);
            $this->fail('Un rapprochement partiel exige un commentaire');
        } catch (BusinessException) {
        }
        $svc->match($line, $p1, '60', $w['validator'], 'Dépôt groupé : part 1');
        $this->assertSame('partial', $line->fresh()->match_status);
        $svc->match($line->fresh(), $p2, '40', $w['validator'], 'Dépôt groupé : part 2');
        $this->assertSame('matched', $line->fresh()->match_status);
        $this->expectException(BusinessException::class);
        $svc->match($line->fresh(), $p2, '1', $w['validator'], 'trop');
    }

    public function test_releves_qui_se_chevauchent_non_comptes_deux_fois(): void
    {
        $this->world();
        $this->import("date;reference;libelle;credit\n01/11/2025;R1;V1;100\n02/11/2025;R2;V2;200\n02/11/2025;;Espèces;50\n02/11/2025;;Espèces;50\n", 'octobre.csv');
        $second = $this->import("date;reference;libelle;credit\n02/11/2025;R2;V2;200\n02/11/2025;;Espèces;50\n02/11/2025;;Espèces;50\n03/11/2025;R3;V3;300\n", 'novembre.csv');
        $this->assertSame(1, $second->rows_created);      // seule R3 est nouvelle
        $this->assertSame(3, $second->rows_skipped);
        $this->assertSame(5, StatementLine::query()->count());
        $this->assertSame(2, StatementLine::query()->where('review_status', 'pending')->count()); // sans référence → à vérifier
        $this->expectExceptionMessage('déjà été importé');
        $this->import("date;reference;libelle;credit\n02/11/2025;R2;V2;200\n02/11/2025;;Espèces;50\n02/11/2025;;Espèces;50\n03/11/2025;R3;V3;300\n", 'copie.csv');
    }

    public function test_ligne_incertaine_verifiee_avant_rapprochement(): void
    {
        $w = $this->world();
        $s = $this->student($w['sci_l1']);
        $p = $this->pay($s, '100', [], 'Z9');
        $this->import("date;reference;libelle;credit\n10/11/2025;;Sans référence;100\n");
        $line = StatementLine::query()->sole();
        $svc = app(ReconciliationService::class);
        try {
            $svc->match($line, $p, '100', $w['validator'], 'test');
            $this->fail('Ligne incertaine');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('vérifiée', $e->getMessage());
        }
        $svc->review($line, true, $w['validator'], ['reference' => 'Z9'], 'Référence lue sur la copie du relevé');
        $svc->match($line->fresh(), $p, '100', $w['validator'], 'ok');
        $this->assertSame('confirmed', $p->fresh()->bank_status);
    }

    public function test_rapport_de_couverture(): void
    {
        $w = $this->world();
        $this->import("date;reference;libelle;credit\n01/11/2025;R1;V1;100\n15/11/2025;R2;V2;100\n");
        $r = app(ReconciliationService::class)->report($w['acc']->id, Carbon::parse('2025-10-01'), Carbon::parse('2025-11-30'));
        $gaps = $r['coverage'][0]['gaps'];
        $this->assertSame(['2025-10-01', '2025-10-31'], $gaps[0]);       // période non couverte signalée
        $this->assertSame(['2025-11-16', '2025-11-30'], $gaps[1]);
        $this->assertCount(2, $r['unmatched']);
    }
}
