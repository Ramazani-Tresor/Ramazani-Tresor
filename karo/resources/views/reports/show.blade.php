@extends('layouts.app')
@section('title', $r['title'])
@section('content')
@include('partials.head', ['title' => $r['title'], 'crumbs' => ['Rapports' => route('reports.index')], 'sub' => 'Année '.e($filters->year?->code).' · '.count($r['rows']).' ligne(s) · généré le '.now()->format('d/m/Y H:i'),
    'actions' => can_do('reports.export') ? '<a class="btn btn-light" href="'.route('reports.export', [$type, 'xlsx']).'?'.http_build_query(array_filter($filters->values)).'"><i class="bi bi-file-earmark-excel"></i> Excel</a><a class="btn btn-light" href="'.route('reports.export', [$type, 'pdf']).'?'.http_build_query(array_filter($filters->values)).'"><i class="bi bi-file-earmark-pdf"></i> PDF</a>' : ''])
<div class="card mb-3"><div class="card-body">@include('partials.filters', ['show' => $type === 'payments' ? ['year', 'faculty', 'promotion', 'account', 'currency', 'period', 'status'] : ['year', 'faculty', 'program', 'promotion', 'fee', 'currency', ...($type === 'balances' ? ['status'] : [])],
    'statuses' => $type === 'payments' ? \App\Support\Labels::STATUSES['payment'] : ['due' => 'Avec solde dû uniquement']])</div></div>
@if($r['note'])<div class="alert alert-info small"><i class="bi bi-info-circle"></i> {{ $r['note'] }}</div>@endif
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover table-stack mb-0">
        <thead><tr>@foreach($r['columns'] as $k => $l)<th class="{{ in_array($k, $r['money']) ? 'num' : '' }}">{{ $l }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse(array_slice($r['rows'], 0, 2000) as $row)
            <tr>@foreach($r['columns'] as $k => $l)<td data-l="{{ $l }}" class="{{ in_array($k, $r['money']) ? 'num' : '' }}">{{ in_array($k, $r['money']) ? money($row[$k]) : $row[$k] }}</td>@endforeach</tr>
        @empty<tr><td colspan="{{ count($r['columns']) }}">@include('partials.empty', ['text' => 'Aucune donnée pour ces filtres.'])</td></tr>@endforelse
        </tbody>
        @if($r['totals'])<tfoot>@foreach($r['totals'] as $cur => $t)<tr class="fw-bold">@foreach($r['columns'] as $k => $l)<td class="{{ in_array($k, $r['money']) ? 'num' : '' }}">{{ isset($t[$k]) ? money($t[$k], $cur) : ($loop->first ? 'Total '.$cur : '') }}</td>@endforeach</tr>@endforeach</tfoot>@endif
    </table></div>
    @if(count($r['rows']) > 2000)<div class="card-body small text-muted-2">Affichage limité à 2 000 lignes : utilisez l’export.</div>@endif
</div>
@endsection
