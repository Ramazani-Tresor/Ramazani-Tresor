@extends('layouts.app')
@section('title', 'Mon compte étudiant')
@section('content')
@include('partials.head', ['title' => 'Mon compte — '.$student->fullName(), 'sub' => e($student->matricule)])
<form method="get" class="mb-3" style="max-width:260px"><label class="form-label" for="py">Année académique</label><select id="py" name="year" class="form-select" onchange="this.form.submit()">@foreach($years as $y)<option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->code }}</option>@endforeach</select></form>
<div class="card mb-3"><div class="card-body">@include('students._situation', ['compact' => true])</div></div>
<div class="card"><div class="card-header">Mes documents
    @if($year)<div class="d-flex gap-1">@foreach(['situation' => 'Situation', 'attestation' => 'Attestation'] as $t => $l)<form method="post" action="{{ route('documents.issue', $student) }}">@csrf<input type="hidden" name="type" value="{{ $t }}"><input type="hidden" name="year" value="{{ $year->id }}"><button class="btn btn-sm btn-light">{{ $l }}</button></form>@endforeach</div>@endif</div>
    <ul class="list-group list-group-flush">@forelse($docs as $d)<li class="list-group-item d-flex justify-content-between"><span>{{ status_label('document_type', $d->type) }} {{ $d->number }} — {{ $d->issued_at->format('d/m/Y') }}</span><a href="{{ route('documents.show', $d) }}" target="_blank" rel="noopener">Ouvrir</a></li>@empty<li class="list-group-item text-muted-2 small">Aucun document.</li>@endforelse</ul></div>
@endsection
