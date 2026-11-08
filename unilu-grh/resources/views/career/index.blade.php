@extends('layouts.app')
@section('title', 'Décisions de carrière')
@php $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; $u = auth()->user(); @endphp
@section('content')
@include('partials.head', ['title' => 'Décisions de carrière', 'sub' => 'Nominations, promotions et renouvellements de mandat en attente de validation, et mandats arrivant à expiration. Les décisions se préparent depuis la fiche de l’agent.'])
<div class="card mb-3"><div class="card-header">À valider ({{ $pending->count() }})</div>
@if($pending->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Agent</th><th>Évolution</th><th>Effet</th><th>Décision</th><th>Saisie par</th><th></th></tr></thead><tbody>
@foreach($pending as $ev)<tr>
    <td data-l="Agent"><a href="{{ route('employees.show', $ev->employee) }}#carriere">{{ $ev->employee->fullName() }}</a><br><span class="small text-muted-2">{{ $ev->employee->matricule }}</span></td>
    <td data-l="Évolution">{{ \App\Support\Labels::status('career_type', $ev->type) }} : {{ $ev->oldGrade?->name ?? '…' }} → <b>{{ $ev->newGrade?->name ?? $ev->newMandate?->name ?? $ev->newCategory?->name }}</b></td>
    <td data-l="Effet">{{ $d($ev->effective_date) }}</td><td data-l="Décision">{{ $ev->decision_number }} du {{ $d($ev->decision_date) }}<br><span class="small text-muted-2">{{ $ev->signing_authority }}</span></td>
    <td data-l="Saisie par">{{ $ev->author?->name }}</td>
    <td class="nw">@if($u->canOnEmployee('career.validate', $ev->employee))<form method="post" action="{{ route('career.validate', $ev) }}" class="d-inline" data-confirm="Valider la décision {{ $ev->decision_number }} ?">@csrf<button class="btn btn-sm btn-primary">Valider</button></form> <form method="post" action="{{ route('career.reject', $ev) }}" class="d-inline-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif" style="max-width:140px"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>@else<span class="small text-muted-2">En attente d’un validateur</span>@endif</td>
</tr>@endforeach</tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucune décision en attente.'])</div> @endif
</div>
<div class="row g-3">
<div class="col-lg-6"><div class="card h-100"><div class="card-header"><i class="bi bi-alarm text-warning"></i> Mandats expirant sous {{ config('grh.alert_days_mandate') }} jours</div>
@if($mandates->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Agent</th><th>Mandat</th><th>Échéance</th></tr></thead><tbody>@foreach($mandates as $e)<tr><td data-l="Agent"><a href="{{ route('employees.show', $e) }}">{{ $e->fullName() }}</a></td><td data-l="Mandat">{{ $e->grade?->name }} · {{ $e->mandate?->name }}</td><td data-l="Échéance" class="{{ $e->mandate_end->isPast() ? 'text-danger fw-600' : '' }}">{{ $d($e->mandate_end) }}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucune échéance proche.</div> @endif
</div></div>
<div class="col-lg-6"><div class="card h-100"><div class="card-header">Dernières décisions traitées</div>
@if($recent->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Agent</th><th>Évolution</th><th>Statut</th></tr></thead><tbody>@foreach($recent as $ev)<tr><td data-l="Agent">{{ $ev->employee->fullName() }}</td><td data-l="Évolution">{{ \App\Support\Labels::status('career_type', $ev->type) }} {{ $ev->newGrade?->name }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('decision', $ev->status) !!}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">—</div> @endif
</div></div>
</div>
@endsection
