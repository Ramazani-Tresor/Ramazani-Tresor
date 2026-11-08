<?php

namespace App\Http\Controllers;

use App\Jobs\ConfirmImportJob;
use App\Models\AcademicYear;
use App\Models\ImportBatch;
use App\Models\Promotion;
use App\Services\StudentImportService;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class StudentImportController extends Controller
{
    public const ASYNC_THRESHOLD = 1000;

    public function __construct(private StudentImportService $svc) {}

    public function create()
    {
        return view('imports.students-create', [
            'years' => AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->get(),
            'batches' => ImportBatch::query()->where('type', 'students')->with('creator')->latest('id')->limit(15)->get(),
        ]);
    }

    public function template()
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        @unlink($path);
        $path .= '.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(['Matricule', 'Nom', 'Postnom', 'Prénom', 'Sexe', 'Date de naissance', 'Téléphone', 'E-mail', 'Adresse', 'Faculté', 'Promotion', 'Statut']));
        $promo = Promotion::query()->with('program.department.faculty')->first();
        $w->addRow(Row::fromValues(['EXEMPLE/0001', 'NOM FICTIF', 'POSTNOM', 'Prénom', 'F', '2005-03-14', '+243 000 000 000', '', '', $promo?->program->department->faculty->code ?? 'CODE-FAC', $promo?->code ?? 'CODE-PROMO', 'nouveau']));
        $w->addRow(Row::fromValues(['— Ligne d’exemple à supprimer. Statut : nouveau, ancien ou finaliste. Codes des facultés et promotions : voir Paramètres > Structure.']));
        $w->close();

        return response()->download($path, 'modele-import-etudiants.xlsx')->deleteFileAfterSend();
    }

    public function upload(Request $request)
    {
        $d = $request->validate(['file' => 'required|file|max:10240', 'academic_year_id' => 'required|exists:academic_years,id']);
        $batch = $this->svc->upload($request->file('file'), (int) $d['academic_year_id'], $request->user());

        return redirect()->route('imports.students.show', $batch)->with('info', 'Fichier déposé. Vérifiez l’association des colonnes, puis prévisualisez.');
    }

    public function show(ImportBatch $batch)
    {
        abort_unless($batch->type === 'students', 404);
        $analysis = null;
        $headers = $this->svc->headers($batch);
        if ($batch->status === 'previewed') {
            $analysis = $this->svc->analyze($batch, $batch->column_map, (bool) ($batch->options['update_existing'] ?? false));
        }

        return view('imports.students-show', ['batch' => $batch, 'headers' => $headers, 'fields' => StudentImportService::FIELDS, 'analysis' => $analysis,
            'year' => AcademicYear::find($batch->options['academic_year_id'] ?? 0), 'async' => $batch->rows_total > self::ASYNC_THRESHOLD]);
    }

    public function preview(Request $request, ImportBatch $batch)
    {
        abort_unless($batch->type === 'students' && in_array($batch->status, ['uploaded', 'previewed'], true), 404);
        $map = array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, (array) $request->input('map', []));
        $this->svc->preview($batch, array_filter($map, fn ($v) => $v !== null), $request->boolean('update_existing'));

        return redirect()->route('imports.students.show', $batch);
    }

    public function confirm(Request $request, ImportBatch $batch)
    {
        abort_unless($batch->type === 'students', 404);
        $opts = ['skip_invalid' => $request->boolean('skip_invalid'), 'generate_charges' => $request->boolean('generate_charges')];
        if ($opts['generate_charges']) {
            abort_unless($request->user()->hasPermission('charges.generate'), 403);
        }
        if ($batch->rows_total > self::ASYNC_THRESHOLD) {
            ConfirmImportJob::dispatch($batch->id, $request->user()->id, $opts);

            return redirect()->route('imports.students.show', $batch)->with('info', 'Import volumineux : traitement en arrière-plan. Actualisez cette page pour suivre l’avancement.');
        }
        $done = $this->svc->confirm($batch, $request->user(), $opts['skip_invalid'], $opts['generate_charges']);

        return redirect()->route('imports.students.show', $done)->with('success', "Import terminé : {$done->rows_created} dossier(s) créé(s), {$done->rows_updated} mis à jour, {$done->rows_skipped} ignoré(s).");
    }

    public function report(ImportBatch $batch)
    {
        abort_unless($batch->type === 'students' && $batch->report, 404);
        $path = tempnam(sys_get_temp_dir(), 'rap');
        @unlink($path);
        $path .= '.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(["Rapport d'import {$batch->number} — fichier ".$batch->original_name.' — statut '.$batch->status]));
        $w->addRow(Row::fromValues(['Ligne', 'Matricule', 'Action', 'Erreurs', 'Remarques']));
        foreach ($batch->report as $r) {
            if (! is_array($r)) {
                continue;
            }
            $w->addRow(Row::fromValues(array_map([SpreadsheetReader::class, 'safeCell'], [$r['line'] ?? '', $r['matricule'] ?? '', $r['action'] ?? '', implode(' ; ', $r['errors'] ?? []), implode(' ; ', $r['notes'] ?? [])])));
        }
        $w->close();

        return response()->download($path, "rapport-{$batch->number}.xlsx")->deleteFileAfterSend();
    }
}
