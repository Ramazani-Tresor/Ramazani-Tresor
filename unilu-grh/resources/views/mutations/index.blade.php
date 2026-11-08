@extends('layouts.app')
@section('title', 'Mutations')
@php $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; $u = auth()->user(); @endphp
@section('content')
@include('partials.head', ['title' => 'Mutations', 'sub' => 'Interne : transfert entre structures de l’université (droits maintenus). Externe : départ vers une autre institution (fin des droits ordinaires à la date d’effet, dossier conservé).'])
@foreach([['À valider', $pending], ['Traitées', $done]] as [$title, $list])
<div class="card mb-3"><div class="card-header">{{ $title }} ({{ $list->count() }})</div>
@if($list->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>Agent</th><th>Nature</th><th>Origine → accueil</th><th>Effet</th><th>Décision</th><th>Motif</th><th>Statut</th><th></th></tr></thead><tbody>
@foreach($list as $m)<tr>
    <td data-l="Agent"><a href="{{ route('employees.show', $m->employee) }}#mouvements">{{ $m->employee->fullName() }}</a></td><td data-l="Nature">{{ \App\Support\Labels::status('mutation_kind', $m->kind) }}</td>
    <td data-l="Trajet">{{ $m->fromUnit?->name ?? $m->origin_institution }} → <b>{{ $m->kind === 'internal' ? $m->toUnit?->name : $m->destination_institution }}</b></td>
    <td data-l="Effet">{{ $d($m->effective_date) }}</td><td data-l="Décision">{{ $m->decision_reference }} du {{ $d($m->decision_date) }}</td><td data-l="Motif">{{ \Illuminate\Support\Str::limit($m->reason, 60) }}</td>
    <td data-l="Statut">{!! \App\Support\Labels::badge('decision', $m->status) !!} @if($m->financial_snapshot)<i class="bi bi-cash-coin text-muted-2" title="Situation financière à la date de départ enregistrée"></i>@endif</td>
    <td class="nw">@if($m->status === 'draft' && $u->canOnEmployee('mutations.validate', $m->employee))<form method="post" action="{{ route('mutations.validate', $m) }}" class="d-inline" data-confirm="{{ $m->kind === 'external' ? 'Valider la mutation externe ? L’affectation sera clôturée et les droits ordinaires arrêtés à la date d’effet.' : 'Valider la mutation interne ?' }}">@csrf<button class="btn btn-sm btn-primary">Valider</button></form> <form method="post" action="{{ route('mutations.reject', $m) }}" class="d-inline-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif" style="max-width:130px"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>@endif</td>
</tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucune.</div> @endif
</div>
@endforeach
@endsection
