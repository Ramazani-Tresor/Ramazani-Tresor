@extends('layouts.app')
@section('title', 'Demandes de correction')
@section('content')
@include('partials.head', ['title' => 'Demandes de correction des agents', 'sub' => 'Les rubriques d’identité acceptées sont appliquées au dossier (modification historisée) ; les autres demandes guident une action manuelle (diplôme, carrière…).'])
<div class="card mb-3"><div class="card-header">En attente ({{ $pending->count() }})</div>
@if($pending->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Agent</th><th>Rubrique</th><th>Valeur actuelle</th><th>Valeur demandée</th><th>Justification</th><th>Décision</th></tr></thead><tbody>
@foreach($pending as $r)<tr><td data-l="Agent"><a href="{{ route('employees.show', $r->employee) }}">{{ $r->employee->fullName() }}</a></td><td data-l="Rubrique">{{ $fields[$r->field] ?? $r->field }}</td><td data-l="Actuelle">{{ $r->current_value ?: '—' }}</td><td data-l="Demandée" class="fw-600">{{ $r->requested_value }}</td><td data-l="Justification">{{ $r->justification }} @if($r->document_id)<a href="{{ route('documents.download', $r->document_id) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endif</td>
    <td><form method="post" action="{{ route('corrections.handle', $r) }}" class="d-flex flex-wrap gap-1">@csrf<input name="response" class="form-control form-control-sm" placeholder="Réponse à l’agent" required aria-label="Réponse" style="max-width:180px"><button name="decision" value="accept" class="btn btn-sm btn-primary">Accepter</button><button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Rejeter</button></form></td></tr>@endforeach
</tbody></table></div>@else <div class="card-body small text-muted-2">Aucune demande en attente.</div> @endif
</div>
<div class="card"><div class="card-header">Traitées récemment</div>@if($done->count())<ul class="list-group list-group-flush">@foreach($done as $r)<li class="list-group-item small">{{ $r->employee->fullName() }} — {{ $fields[$r->field] ?? $r->field }} : {!! \App\Support\Labels::badge('correction', $r->status) !!} par {{ $r->handler?->name }} le {{ $r->handled_at?->format('d/m/Y') }}</li>@endforeach</ul>@else <div class="card-body small text-muted-2">—</div> @endif</div>
@endsection
