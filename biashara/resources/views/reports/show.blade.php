@extends('layouts.app')
@section('title', $report['title'])
@section('content')
<div class="page-head">
    <div><h1>{{ $report['title'] }}</h1><div class="sub">Du {{ $filters['from']->format('d/m/Y') }} au {{ $filters['to']->format('d/m/Y') }} · {{ count($report['rows']) }} ligne(s) · généré le {{ $generatedAt->format('d/m/Y H:i') }}</div></div>
    @if(can_do('reports.export'))
        <div class="btn-group">
            @foreach(['xlsx' => ['Excel', 'bi-file-earmark-excel'], 'csv' => ['CSV', 'bi-filetype-csv'], 'pdf' => ['PDF', 'bi-file-earmark-pdf']] as $f => [$l, $i])
                <a class="btn btn-light" href="{{ route('reports.export', [$type, $f]) }}?{{ http_build_query(request()->only(['from', 'to', 'user_id', 'product_id', 'category_id'])) }}"><i class="bi {{ $i }} me-1"></i>{{ $l }}</a>
            @endforeach
        </div>
    @endif
</div>
<div class="card mb-3"><div class="card-body"><form class="filters" method="get">
    @include('partials.period', ['from' => $filters['from'], 'to' => $filters['to']])
    <div><label class="form-label small mb-1">Utilisateur</label><select name="user_id" class="form-select"><option value="">Tous</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected($filters['user_id'] == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div><label class="form-label small mb-1">Article</label><select name="product_id" class="form-select"><option value="">Tous</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected($filters['product_id'] == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div><label class="form-label small mb-1">Catégorie</label><select name="category_id" class="form-select"><option value="">Toutes</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected($filters['category_id'] == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Afficher</button>
    <a href="{{ route('reports.index') }}" class="btn btn-link">Tous les rapports</a>
</form></div></div>
@if($report['note'])<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>{{ $report['note'] }}</div>@endif
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover">
        <thead><tr>@foreach($report['columns'] as $k => $label)<th class="{{ in_array($k, $report['numeric']) ? 'num' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse(array_slice($report['rows'], 0, 1000) as $row)
            <tr>@foreach($report['columns'] as $k => $label)<td class="{{ in_array($k, $report['numeric']) ? 'num' : '' }}">{{ in_array($k, $report['numeric']) ? report_cell($row[$k]) : $row[$k] }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) }}" class="empty"><i class="bi bi-inbox"></i>Aucune donnée sur la période.</td></tr>
        @endforelse
        </tbody>
        @if($report['totals'])
            <tfoot><tr class="fw-bold">@foreach($report['columns'] as $k => $label)<td class="{{ in_array($k, $report['numeric']) ? 'num' : '' }}">@if($loop->first && !isset($report['totals'][$k]))Total @elseif(isset($report['totals'][$k])){{ report_cell($report['totals'][$k]) }}@endif</td>@endforeach</tr></tfoot>
        @endif
    </table></div>
    @if(count($report['rows']) > 1000)<div class="card-body small text-muted-2">Affichage limité aux 1 000 premières lignes : utilisez l'export pour la totalité.</div>@endif
</div>
@endsection
