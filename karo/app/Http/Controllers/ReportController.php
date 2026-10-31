<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\ReportService;
use App\Support\Filters;
use App\Support\Import\SpreadsheetReader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index()
    {
        return view('reports.index', ['reports' => ReportService::REPORTS]);
    }

    public function show(Request $request, string $type)
    {
        abort_unless(isset(ReportService::REPORTS[$type]), 404);
        $filters = Filters::from($request, 'reports.view');

        return view('reports.show', ['type' => $type, 'filters' => $filters, 'r' => $this->reports->build($type, $filters)]);
    }

    public function export(Request $request, string $type, string $format)
    {
        abort_unless(isset(ReportService::REPORTS[$type]) && in_array($format, ['xlsx', 'pdf'], true), 404);
        $filters = Filters::from($request, 'reports.view');   // mêmes périmètres que l'écran
        $r = $this->reports->build($type, $filters);
        AuditService::log('report.export', null, "Export {$format} : {$r['title']} ({$filters->year?->code})", null, $filters->values);
        $name = "{$type}_".($filters->year?->code ?? '').'_'.now()->format('Ymd_His');
        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', ['r' => $r, 'filters' => $filters])->setPaper('a4', count($r['columns']) > 7 ? 'landscape' : 'portrait')->download($name.'.pdf');
        }
        $path = tempnam(sys_get_temp_dir(), 'rep');
        @unlink($path);
        $path .= '.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues([setting('university_name').' — '.$r['title'].' — '.$filters->year?->code.' — généré le '.now()->format('d/m/Y H:i')]));
        $w->addRow(Row::fromValues(array_values($r['columns'])));
        foreach ($r['rows'] as $row) {
            $w->addRow(Row::fromValues(array_map(fn ($k) => in_array($k, $r['money'], true) ? (float) $row[$k] : SpreadsheetReader::safeCell((string) ($row[$k] ?? '')), array_keys($r['columns']))));
        }
        foreach ($r['totals'] as $cur => $t) {
            $w->addRow(Row::fromValues(array_map(fn ($k) => isset($t[$k]) ? (float) $t[$k] : ($k === array_key_first($r['columns']) ? "Total {$cur}" : ''), array_keys($r['columns']))));
        }
        $w->close();

        return response()->download($path, $name.'.xlsx')->deleteFileAfterSend();
    }
}
