<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\StudentImportService;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        foreach ($rows as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->close();

        return new UploadedFile($path, 'etudiants.xlsx', null, null, true);
    }

    private const HEAD = ['Matricule', 'Nom', 'Postnom', 'Prénom', 'Sexe', 'Faculté', 'Promotion', 'Statut'];

    public function test_import_excel_valide_puis_repete_sans_doublon(): void
    {
        $w = $this->world();
        $svc = app(StudentImportService::class);
        $rows = [self::HEAD, ['DEMO/001', 'KABONGO', 'ILUNGA', 'Marie', 'F', 'DRT', 'DRT-L1', 'nouveau'], ['DEMO/002', 'MBUYI', '', 'Paul', 'M', 'SCI', 'SCI-L1', 'Ancien']];
        $batch = $svc->upload($this->xlsx($rows), $w['year']->id, $w['clerk']);
        $this->assertSame(['matricule' => 0, 'last_name' => 1, 'post_name' => 2, 'first_name' => 3, 'gender' => 4, 'faculty_code' => 5, 'promotion_code' => 6, 'status' => 7], $batch->column_map);
        $a = $svc->preview($batch, $batch->column_map, false);
        $this->assertSame(2, $a['summary']['valid']);
        $this->assertSame(0, Student::query()->count());                      // la prévisualisation n'écrit rien
        $svc->confirm($batch->fresh(), $w['clerk'], false, true);
        $this->assertSame(2, Student::query()->count());
        $this->assertSame(2, Enrollment::query()->count());
        $this->assertSame('600.00', Charge::query()->whereHas('student', fn ($q) => $q->where('matricule', 'DEMO/001'))->whereHas('category', fn ($q) => $q->where('code', 'MINERVAL'))->value('amount'));

        // Même fichier à nouveau
        $again = $svc->upload($this->xlsx($rows), $w['year']->id, $w['clerk']);
        $this->assertSame($batch->number, $again->options['already_imported_as']);
        $svc->preview($again, $again->column_map, false);
        $svc->confirm($again->fresh(), $w['clerk'], false, true);
        $this->assertSame(2, Student::query()->count());
        $this->assertSame(2, Enrollment::query()->count());
        $this->assertSame(6, Charge::query()->count());                        // 3 + 3, aucune créance en double
    }

    public function test_import_avec_erreurs_par_ligne_et_confirmation_explicite(): void
    {
        $w = $this->world();
        $svc = app(StudentImportService::class);
        $batch = $svc->upload($this->xlsx([self::HEAD,
            ['DEMO/010', 'A', '', 'B', 'F', 'DRT', 'DRT-L1', 'nouveau'],
            ['', 'SANS', '', 'Matricule', 'M', 'DRT', 'DRT-L1', 'nouveau'],
            ['DEMO/010', 'DOUBLON', '', 'X', 'M', 'DRT', 'DRT-L1', 'nouveau'],
            ['DEMO/011', 'C', '', 'D', 'M', 'DRT', 'SCI-L1', 'nouveau'],
            ['DEMO/012', 'E', '', 'F', 'M', 'DRT', 'DRT-L1', 'finaliste'],
        ]), $w['year']->id, $w['clerk']);
        $a = $svc->preview($batch, $batch->column_map, false);
        $errors = collect($a['rows'])->keyBy('line')->map(fn ($r) => implode(' | ', $r['errors']));
        $this->assertSame('', $errors[2]);
        $this->assertStringContainsString('Matricule absent', $errors[3]);
        $this->assertStringContainsString('double dans le fichier (ligne 2)', $errors[4]);
        $this->assertStringContainsString("n'appartient pas à la faculté", $errors[5]);
        $this->assertStringContainsString('finaliste incohérent', $errors[6]);
        try {
            $svc->confirm($batch->fresh(), $w['clerk'], false, false);
            $this->fail('Import bloqué tant que les erreurs ne sont pas explicitement écartées');
        } catch (BusinessException) {
        }
        $this->assertSame(0, Student::query()->count());
        $done = $svc->confirm($batch->fresh(), $w['clerk'], true, false);
        $this->assertSame(1, Student::query()->count());
        $this->assertSame(4, $done->rows_skipped);
        $this->assertCount(5, $done->report);                                  // rapport conservé par ligne
    }

    public function test_mise_a_jour_d_un_dossier_existant_uniquement_sur_option(): void
    {
        $w = $this->world();
        Student::create(['matricule' => 'DEMO/020', 'last_name' => 'ANCIEN', 'first_name' => 'Nom']);
        $svc = app(StudentImportService::class);
        $file = fn () => $this->xlsx([self::HEAD, ['DEMO/020', 'NOUVEAU', '', 'Nom', 'M', 'SCI', 'SCI-L1', 'ancien']]);
        $b1 = $svc->upload($file(), $w['year']->id, $w['clerk']);
        $a = $svc->preview($b1, $b1->column_map, false);
        $this->assertSame('skip_existing', $a['rows'][0]['action']);
        $svc->confirm($b1->fresh(), $w['clerk'], false, false);
        $this->assertSame('ANCIEN', Student::query()->value('last_name'));
        $b2 = $svc->upload($file(), $w['year']->id, $w['clerk']);
        $svc->preview($b2, $b2->column_map, true);
        $svc->confirm($b2->fresh(), $w['clerk'], false, false);
        $this->assertSame('NOUVEAU', Student::query()->value('last_name'));
    }
}
