@extends('layouts.app')
@section('title', 'Mes congés')
@section('content')
@include('partials.head', ['title' => 'Mes congés et situations administratives'])
@include('me._tabs')
<div class="card">@if($situations->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Type</th><th>Début</th><th>Fin</th><th>Durée</th><th>Décision</th><th>Statut</th></tr></thead><tbody>@foreach($situations as $s)<tr><td data-l="Type" class="fw-600">{{ $s->type->name }}</td><td data-l="Début">{{ $s->start_date->format('d/m/Y') }}</td><td data-l="Fin">{{ ($s->actual_end_date ?? $s->planned_end_date)?->format('d/m/Y') ?? '—' }}</td><td data-l="Durée">{{ $s->durationDays() ? $s->durationDays().' jours' : '—' }}</td><td data-l="Décision">{{ $s->decision_reference }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('situation', $s->status) !!}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucun congé ni situation enregistré.'])</div> @endif</div>
@endsection
