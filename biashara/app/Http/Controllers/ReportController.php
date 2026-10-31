<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ReportService;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request)
    {
        $available = collect(ReportService::REPORTS)->filter(fn ($r) => $request->user()->hasPermission($r[1]));

        return view('reports.index', ['reports' => $available]);
    }

    public function show(Request $request, string $type)
    {
        [$filters, $report] = $this->build($request, $type);

        return view('reports.show', $this->viewData($type, $filters, $report));
    }

    public function export(Request $request, string $type, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);
        [$filters, $report] = $this->build($request, $type);
        AuditService::log('report.export', null, "Export {$format} : {$report['title']} ({$filters['from']->format('d/m/Y')} – {$filters['to']->format('d/m/Y')})");
        $filename = $type.'_'.$filters['from']->format('Ymd').'_'.$filters['to']->format('Ymd');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf', $this->viewData($type, $filters, $report))->setPaper('a4', count($report['columns']) > 7 ? 'landscape' : 'portrait');

            return $pdf->download($filename.'.pdf');
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_values($report['columns']), ';');
                foreach ($report['rows'] as $row) {
                    fputcsv($out, array_map(fn ($k) => $this->cell($row[$k] ?? '', in_array($k, $report['numeric'], true), true), array_keys($report['columns'])), ';');
                }
                fclose($out);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpt_');
        @unlink($tmp);
        $path = $tmp.'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([Settings::get('shop_name').' — '.$report['title']]));
        $writer->addRow(Row::fromValues(['Période : du '.$filters['from']->format('d/m/Y').' au '.$filters['to']->format('d/m/Y').' — généré le '.now()->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValues(array_values($report['columns'])));
        foreach ($report['rows'] as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($k) => $this->cell($row[$k] ?? '', in_array($k, $report['numeric'], true)), array_keys($report['columns']))));
        }
        if ($report['totals']) {
            $writer->addRow(Row::fromValues(array_map(fn ($k) => isset($report['totals'][$k]) ? (float) $report['totals'][$k] : '', array_keys($report['columns']))));
        }
        $writer->close();

        return response()->download($path, $filename.'.xlsx')->deleteFileAfterSend();
    }

    private function build(Request $request, string $type): array
    {
        abort_unless(isset(ReportService::REPORTS[$type]), 404);
        $this->authorizePermission(ReportService::REPORTS[$type][1]);
        $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date',
            'user_id' => 'nullable|integer', 'product_id' => 'nullable|integer', 'category_id' => 'nullable|integer',
        ]);
        [$from, $to] = $this->period($request);
        $filters = [
            'from' => $from, 'to' => $to,
            'user_id' => $request->integer('user_id') ?: null,
            'product_id' => $request->integer('product_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
        ];

        return [$filters, $this->reports->build($type, $filters)];
    }

    private function viewData(string $type, array $filters, array $report): array
    {
        return [
            'type' => $type, 'filters' => $filters, 'report' => $report,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'shop' => Settings::all(),
            'generatedAt' => now(),
        ];
    }

    private function cell(mixed $value, bool $numeric, bool $csv = false): mixed
    {
        if (! $numeric || $value === '' || $value === null) {
            return (string) $value;
        }

        return $csv ? str_replace('.', ',', (string) $value) : (float) (string) Money::of($value);
    }
}
