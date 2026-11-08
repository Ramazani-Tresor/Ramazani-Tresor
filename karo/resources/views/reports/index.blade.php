@extends('layouts.app')
@section('title', 'Rapports')
@section('content')
@include('partials.head', ['title' => 'Rapports', 'sub' => 'Détaillés et synthétiques, filtrables, exportables en PDF et Excel avec les mêmes habilitations que l’écran.'])
<div class="row g-3">
    @foreach($reports as $k => $l)<div class="col-md-6 col-xl-4"><a class="card kpi text-decoration-none h-100" href="{{ route('reports.show', $k) }}"><div class="l"><i class="bi bi-file-earmark-bar-graph"></i> Rapport</div><div class="v" style="font-size:1.02rem">{{ $l }}</div></a></div>@endforeach
    <div class="col-md-6 col-xl-4"><a class="card kpi text-decoration-none h-100" href="{{ route('bank.report') }}"><div class="l"><i class="bi bi-bank"></i> Rapport</div><div class="v" style="font-size:1.02rem">Rapprochement bancaire et couverture des relevés</div></a></div>
    <div class="col-md-6 col-xl-4"><a class="card kpi text-decoration-none h-100" href="{{ route('exams.index') }}"><div class="l"><i class="bi bi-mortarboard"></i> Listes</div><div class="v" style="font-size:1.02rem">Contrôle financier des examens</div></a></div>
</div>
@endsection
