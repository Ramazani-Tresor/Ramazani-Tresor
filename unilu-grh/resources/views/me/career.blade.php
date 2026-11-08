@extends('layouts.app')
@section('title', 'Ma carrière')
@php $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; @endphp
@section('content')
@include('partials.head', ['title' => 'Ma carrière', 'sub' => 'Décisions validées et affectations successives.'])
@include('me._tabs')
<div class="row g-3">
<div class="col-lg-7"><div class="card"><div class="card-header">Promotions et décisions</div><div class="card-body">
    @if($career->count())<ul class="timeline">@foreach($career as $ev)<li><span class="dot"></span><div class="when">{{ $d($ev->effective_date) }}</div><div class="what">{{ \App\Support\Labels::status('career_type', $ev->type) }} {{ $ev->newGrade ? '— '.$ev->newGrade->name : '' }} {{ $ev->newMandate ? '— '.$ev->newMandate->name : '' }}</div><div class="small text-muted-2">Décision {{ $ev->decision_number }} du {{ $d($ev->decision_date) }} · {{ $ev->signing_authority }}</div></li>@endforeach</ul>
    @else @include('partials.empty', ['text' => 'Aucune décision enregistrée.']) @endif
</div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-header">Affectations successives</div><ul class="list-group list-group-flush">@forelse($assignments as $a)<li class="list-group-item"><b>{{ $a->orgUnit?->name }}</b><br><span class="small text-muted-2">{{ $d($a->start_date) }} → {{ $a->end_date ? $d($a->end_date) : 'en cours' }} · {{ $a->reason }}</span></li>@empty<li class="list-group-item small text-muted-2">—</li>@endforelse</ul></div></div>
</div>
@endsection
