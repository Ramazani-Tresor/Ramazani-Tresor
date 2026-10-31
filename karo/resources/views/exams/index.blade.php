@extends('layouts.app')
@section('title', 'Contrôle financier des examens')
@section('content')
@include('partials.head', ['title' => 'Contrôle financier des examens', 'sub' => 'La conformité financière ne vaut pas admissibilité académique complète.'])
<div class="card"><div class="card-header">Sessions <form method="get"><label class="visually-hidden" for="ey">Année</label><select id="ey" name="year" class="form-select form-select-sm" onchange="this.form.submit()">@foreach($years as $y)<option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->code }}</option>@endforeach</select></form></div>
    <div class="list-group list-group-flush">
        @forelse($sessions as $s)<a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('exams.show', $s) }}"><span><strong>{{ $s->name }}</strong> — semestre {{ $s->semester }}, {{ $s->type === 'resit' ? 'rattrapage' : 'session ordinaire' }}</span><span class="small text-muted-2">seuil {{ rtrim(rtrim($s->threshold_percent, '0'), '.') }} %{{ $s->payment_deadline ? ', échéance '.$s->payment_deadline->format('d/m/Y') : '' }}</span></a>
        @empty<div class="list-group-item">@include('partials.empty', ['text' => 'Aucune session : créez-les dans Années académiques.'])</div>@endforelse
    </div></div>
@endsection
