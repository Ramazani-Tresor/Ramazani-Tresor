@extends('layouts.app')
@section('title', 'Tableau de bord')
@push('scripts-before')<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>@endpush
@php
    $chart = fn ($type, $data, $extra = []) => json_encode(['type' => $type, 'labels' => array_map('strval', array_keys($data)), 'data' => array_values($data)] + $extra, JSON_UNESCAPED_UNICODE);
    $fam = collect($s['by_family'])->mapWithKeys(fn ($v, $k) => [\App\Support\Labels::status('family', $k) => $v])->all();
    $kpi = fn ($label, $value, $hint = '', $cls = '', $tip = null) => compact('label', 'value', 'hint', 'cls', 'tip');
@endphp
@section('content')
@include('partials.head', ['title' => 'Tableau de bord', 'sub' => ($s['year'] ? 'Année académique '.e($s['year']->code).' ('.$s['year']->starts_on->format('d/m/Y').' – '.$s['year']->ends_on->format('d/m/Y').')' : 'Aucune année académique en cours').' · indicateurs limités à votre périmètre'])
<div class="card mb-3"><div class="card-body">@include('partials.filters', ['show' => ['year', 'unit', 'family', 'category', 'status']])</div></div>

<div class="row g-3 mb-3">
    @foreach([
        $kpi('Effectif total', $s['total'], empty($f['status']) ? 'agents en fonction' : \App\Support\Labels::status('employee', $f['status']), 'accent', 'Agents dont le statut n’est pas une fin de fonctions (décès, mutation externe, retraite, démission, départ).'),
        $kpi('Personnel académique', $s['by_family']['academic'] ?? 0),
        $kpi('Personnel scientifique', $s['by_family']['scientific'] ?? 0),
        $kpi('Administratif et ouvrier', $s['by_family']['administrative'] ?? 0),
        $kpi('Docteurs', $s['doctors'], 'dans l’effectif', '', 'Agents ayant une qualification « grade de docteur » ou « thèse de doctorat ».'),
        $kpi('DEA', $s['dea'], 'diplômes enregistrés'),
        $kpi('Promotions', $s['promotions'], 'validées dans l’année', '', 'Décisions de promotion validées dont la date d’effet tombe dans l’année académique.'),
        $kpi('Agents en congé', $s['on_leave'], 'à ce jour', 'warn-k'),
        $kpi('Mutations', $s['mutations'], 'dans l’année'),
        $kpi('Départs', $s['departures'], 'retraite, démission, mutation externe'),
        $kpi('Décès', $s['deaths'], 'dans l’année', 'alert-k'),
        $kpi('Suspensions', $s['suspensions'], 'en vigueur dans l’année', 'alert-k'),
    ] as $k)
        <div class="col-6 col-md-4 col-xl-3"><div class="card kpi {{ $k['cls'] }}"><div class="l">{{ $k['label'] }} @if($k['tip'])<i class="bi bi-info-circle tip" tabindex="0" data-bs-toggle="tooltip" title="{{ $k['tip'] }}"></i>@endif</div><div class="v">{{ $k['value'] }}</div><div class="h">{{ $k['hint'] }}</div></div></div>
    @endforeach
</div>

@if($s['finance'] !== null)
<h2 class="h6 text-uppercase text-muted-2 fw-bold mb-2" style="letter-spacing:.06em">Finances de l’année (par devise, jamais additionnées entre devises)</h2>
<div class="row g-3 mb-3">
    @forelse($s['finance'] as $cur => $t)
        @foreach([['Primes prévues', 'planned', ''], ['Primes payées', 'paid', 'accent'], ['Acomptes', 'advances', ''], ['Arriérés', 'arrears', 'alert-k'], ['Soutiens sociaux', 'support', '']] as [$l, $k, $cls])
            <div class="col-6 col-md-4 col-xl"><div class="card kpi {{ $cls }}"><div class="l">{{ $l }}</div><div class="v" style="font-size:1.15rem">{{ money($t[$k], $cur) }}</div>@if($k === 'arrears' && \App\Support\Money::isPositive($t['overpaid']))<div class="h">Trop-perçus à régulariser : {{ money($t['overpaid'], $cur) }}</div>@endif</div></div>
        @endforeach
    @empty
        <div class="col-12"><div class="card"><div class="card-body text-muted-2 small">Aucune prime générée pour cette année.</div></div></div>
    @endforelse
</div>
@endif

<div class="row g-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Effectif par famille</div><div class="card-body"><div class="chart-box"><canvas data-chart='{{ $chart('doughnut', $fam) }}' aria-label="Répartition de l’effectif par famille"></canvas></div></div></div></div>
    <div class="col-lg-8"><div class="card h-100"><div class="card-header">Effectif par grade</div><div class="card-body"><div class="chart-box"><canvas data-chart='{{ $chart('bar', $s['by_grade'], ['label' => 'Agents']) }}' aria-label="Effectif par grade"></canvas></div></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Effectif par faculté</div><div class="card-body">
        @if($s['by_faculty'])<div class="chart-box"><canvas data-chart='{{ $chart('bar', $s['by_faculty'], ['label' => 'Agents', 'horizontal' => true]) }}' aria-label="Effectif par faculté"></canvas></div>@else @include('partials.empty', ['text' => 'Aucune affectation en faculté.']) @endif
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Effectif par service et autres unités</div><div class="card-body"><ul class="stat-list">@forelse($s['by_service'] as $k => $v)<li><span>{{ $k }}</span><b>{{ $v }}</b></li>@empty<li class="text-muted-2">—</li>@endforelse</ul></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Thèses soutenues par année académique</div><div class="card-body">
        @if($s['theses_by_year'])<div class="chart-box"><canvas data-chart='{{ $chart('line', $s['theses_by_year'], ['label' => 'Thèses']) }}' aria-label="Thèses par année"></canvas></div>@else @include('partials.empty', ['text' => 'Aucune thèse enregistrée.']) @endif
        <a class="small" href="{{ route('stats.scientific') }}">Statistiques scientifiques détaillées →</a>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Effectif par catégorie</div><div class="card-body"><ul class="stat-list">@foreach($s['by_category'] as $k => $v)<li><span>{{ $k }}</span><b>{{ $v }}</b></li>@endforeach</ul></div></div></div>
    @if($mandates->count())
    <div class="col-12"><div class="card"><div class="card-header"><span><i class="bi bi-alarm text-warning"></i> Mandats arrivant à expiration ({{ config('grh.alert_days_mandate') }} jours)</span><a href="{{ route('career.index') }}" class="small">Toutes les échéances</a></div>
        <div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Agent</th><th>Grade</th><th>Mandat</th><th>Échéance</th><th>Affectation</th></tr></thead><tbody>
        @foreach($mandates as $e)<tr><td data-l="Agent"><a href="{{ route('employees.show', $e) }}">{{ $e->fullName() }}</a></td><td data-l="Grade">{{ $e->grade?->name }}</td><td data-l="Mandat">{{ $e->mandate?->name }}</td><td data-l="Échéance" class="{{ $e->mandate_end->isPast() ? 'text-danger fw-600' : '' }}">{{ $e->mandate_end->format('d/m/Y') }}</td><td data-l="Affectation">{{ $e->activeAssignment?->orgUnit?->name }}</td></tr>@endforeach
        </tbody></table></div></div></div>
    @endif
</div>
@endsection
