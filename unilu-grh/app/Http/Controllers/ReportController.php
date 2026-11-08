<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\OrgUnit;
use App\Services\ReportService;
use App\Support\Import\SpreadsheetSafe;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        return view('reports.index', [
            'types' => collect(ReportService::TYPES)->filter(fn ($t) => $u->hasPermission($t[1]))->groupBy(fn ($t) => $t[2], true),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'units' => OrgUnit::query()->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request, string $type, string $format, ReportService $reports)
    {
        $f = array_filter($request->only(['year', 'unit', 'family', 'status', 'period']), fn ($v) => $v !== null && $v !== '');
        $r = $reports->build($type, $request->user(), $f);
        $file = 'unilu-grh-'.$type.'-'.now()->format('Ymd-His');
        if ($format === 'html') {
            return view('reports.preview', ['r' => $r, 'f' => $f]);
        }
        if ($format === 'pdf') {
            return Pdf::loadView('pdf.report', ['r' => $r])->setPaper('a4', count($r['columns']) > 6 ? 'landscape' : 'portrait')->download($file.'.pdf');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'grh');
        @unlink($tmp);
        $path = $tmp.'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $bold = (new Style)->setFontBold();
        $w->addRow(Row::fromValues([setting('university_name').' — '.$r['title']], $bold));
        $w->addRow(Row::fromValues([$r['period'].' · Référence '.$r['reference'].' · Édité le '.$r['generated_at']->format('d/m/Y H:i')]));
        $w->addRow(Row::fromValues([]));
        $cols = $r['columns'];
        if ($r['groups']) {
            array_unshift($cols, 'Groupe');
        }
        $w->addRow(Row::fromValues($cols, $bold));
        foreach ($r['rows'] as $i => $row) {
            $w->addRow(Row::fromValues(array_map(fn ($v) => is_string($v) ? SpreadsheetSafe::cell($v) : $v, $r['groups'] ? [$r['groups'][$i], ...$row] : $row)));
        }
        foreach ($r['summary'] as $k => $v) {
            $w->addRow(Row::fromValues([$k, $v], $bold));
        }
        $w->close();

        return response()->download($path, $file.'.xlsx')->deleteFileAfterSend();
    }
}
