<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>{{ $report['title'] }}</title>
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #111; }
    h1 { font-size: 15px; color: #0B1F3A; margin: 0 0 2px; }
    .meta { color: #555; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0B1F3A; color: #fff; text-align: left; padding: 4px; font-size: 8.5px; }
    td { border-bottom: 1px solid #DCE5F2; padding: 3px 4px; }
    .num { text-align: right; white-space: nowrap; }
    tfoot td { font-weight: bold; border-top: 2px solid #0B1F3A; }
    .note { margin-top: 8px; color: #555; }
</style></head>
<body>
    <h1>{{ $shop['shop_name'] }} — {{ $report['title'] }}</h1>
    <div class="meta">Période du {{ $filters['from']->format('d/m/Y') }} au {{ $filters['to']->format('d/m/Y') }} · généré le {{ $generatedAt->format('d/m/Y H:i') }} · montants en {{ $shop['currency_code'] }}</div>
    <table>
        <thead><tr>@foreach($report['columns'] as $k => $label)<th class="{{ in_array($k, $report['numeric']) ? 'num' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
        <tbody>@foreach($report['rows'] as $row)<tr>@foreach($report['columns'] as $k => $label)<td class="{{ in_array($k, $report['numeric']) ? 'num' : '' }}">{{ in_array($k, $report['numeric']) ? report_cell($row[$k]) : $row[$k] }}</td>@endforeach</tr>@endforeach</tbody>
        @if($report['totals'])<tfoot><tr>@foreach($report['columns'] as $k => $label)<td class="num">{{ isset($report['totals'][$k]) ? report_cell($report['totals'][$k]) : ($loop->first ? 'Total' : '') }}</td>@endforeach</tr></tfoot>@endif
    </table>
    @if($report['note'])<p class="note">{{ $report['note'] }}</p>@endif
</body></html>
