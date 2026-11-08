@extends('layouts.app')
@section('title', 'Statistiques scientifiques')
@push('scripts-before')<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>@endpush
@php $chart = fn ($type, $data, $extra = []) => json_encode(['type' => $type, 'labels' => array_map('strval', array_keys($data)), 'data' => array_values($data)] + $extra, JSON_UNESCAPED_UNICODE); $q = http_build_query($f); @endphp
@section('content')
@include('partials.head', ['title' => 'Statistiques scientifiques', 'sub' => 'Diplômes, mémoires et thèses du personnel (le DEA, le mémoire de recherche et la thèse de doctorat sont comptés séparément).', 'actions' => (can_do('reports.view') ? '<a class="btn btn-light" href="'.route('reports.export', ['scientific', 'xlsx']).'?'.$q.'"><i class="bi bi-file-earmark-excel"></i> Excel</a><a class="btn btn-light" href="'.route('reports.export', ['scientific', 'pdf']).'?'.$q.'"><i class="bi bi-file-earmark-pdf"></i> PDF</a>' : '')])
<div class="card mb-3"><div class="card-body">@include('partials.filters', ['show' => ['year', 'unit', 'family']])</div></div>
<div class="row g-3 mb-3">
    @foreach([['Total des DEA', 'total_dea', 'accent'], ['Mémoires de recherche', 'total_research_theses', ''], ['Mémoires de DEA', 'total_dea_theses', ''], ['Thèses soutenues', 'total_theses', 'accent'], ['Docteurs', 'total_doctors', ''], ['Docteurs — personnel scientifique', 'doctors_scientific', ''], ['Docteurs parmi les candidats à la promotion', 'doctors_promotion_candidates', 'warn-k']] as [$l, $k, $cls])
        <div class="col-6 col-md-4 col-xl-3"><div class="card kpi {{ $cls }}"><div class="l">{{ $l }}</div><div class="v">{{ $s[$k] }}</div>@if($k === 'doctors_promotion_candidates')<div class="h">sur {{ $s['promotion_candidates'] }} candidat(s) (promotions en attente)</div>@endif</div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Thèses par année académique</div><div class="card-body">@if($s['theses_by_year'])<div class="chart-box"><canvas data-chart='{{ $chart('bar', $s['theses_by_year'], ['label' => 'Thèses']) }}'></canvas></div>@else @include('partials.empty') @endif</div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">DEA par année académique</div><div class="card-body">@if($s['dea_by_year'])<div class="chart-box"><canvas data-chart='{{ $chart('bar', $s['dea_by_year'], ['label' => 'DEA']) }}'></canvas></div>@else @include('partials.empty') @endif</div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Thèses par faculté</div><div class="card-body"><ul class="stat-list">@forelse($s['theses_by_faculty'] as $k => $v)<li><span>{{ $k }}</span><b>{{ $v }}</b></li>@empty<li class="text-muted-2">—</li>@endforelse</ul></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Thèses par département</div><div class="card-body"><ul class="stat-list">@forelse($s['theses_by_department'] as $k => $v)<li><span>{{ $k }}</span><b>{{ $v }}</b></li>@empty<li class="text-muted-2">—</li>@endforelse</ul></div></div></div>
    <div class="col-12"><div class="card"><div class="card-header">Détail ({{ $s['items']->count() }})</div>
        @if($s['items']->count())
        <div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Nature</th><th>Agent</th><th>Intitulé</th><th>Domaine</th><th>Université</th><th>Soutenance</th><th>Année</th><th>Mention</th></tr></thead><tbody>
        @foreach($s['items']->sortBy('nature') as $q)<tr><td data-l="Nature">{{ \App\Support\Labels::status('qualification', $q->nature) }}</td><td data-l="Agent">@if(can_do('employees.view'))<a href="{{ route('employees.show', $q->employee_id) }}">{{ $q->employee?->fullName() }}</a>@endif</td><td data-l="Intitulé">{{ $q->title }}</td><td data-l="Domaine">{{ $q->field }}</td><td data-l="Université">{{ $q->defense_university }}</td><td data-l="Soutenance">{{ $q->defense_date?->format('d/m/Y') }}</td><td data-l="Année">{{ $q->academicYear?->code }}</td><td data-l="Mention">{{ $q->mention }}</td></tr>@endforeach
        </tbody></table></div>
        @else @include('partials.empty') @endif
    </div></div>
</div>
@endsection
