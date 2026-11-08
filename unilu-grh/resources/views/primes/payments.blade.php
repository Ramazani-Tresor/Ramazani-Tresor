@extends('layouts.app')
@section('title', 'Paiements et acomptes')
@php $u = auth()->user(); @endphp
@section('content')
@include('partials.head', ['title' => 'Paiements et acomptes', 'sub' => 'Chaque mouvement est validé par une autre personne que son auteur. Un mouvement validé n’est jamais supprimé : il se corrige par contrepassation ou régularisation.'])
<div class="card mb-3"><div class="card-header">À valider ({{ $pending->count() }})</div>
@if($pending->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>N°</th><th>Agent</th><th>Prime / période</th><th>Nature</th><th class="num">Montant</th><th>Date / mode</th><th>Saisi par</th><th></th></tr></thead><tbody>
@foreach($pending as $p)<tr><td data-l="N°" class="nw"><a href="{{ route('primes.show', $p->prime_entitlement_id) }}">{{ $p->number }}</a></td><td data-l="Agent">{{ $p->employee->fullName() }}</td><td data-l="Prime">{{ $p->entitlement->primeType?->name }} — {{ $p->entitlement->period_label }}</td><td data-l="Nature">{{ \App\Support\Labels::status('payment_kind', $p->kind) }}</td><td data-l="Montant" class="num fw-600">{{ money($p->amount, $p->currency) }}</td><td data-l="Date">{{ $p->payment_date->format('d/m/Y') }} · {{ \App\Support\Labels::status('payment_mode', $p->mode) }}</td><td data-l="Saisi par">{{ $p->author?->name }}</td>
    <td class="nw">@if($u->hasPermission('payments.validate') && $p->created_by !== $u->id)<form method="post" action="{{ route('payments.validate', $p) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary">Valider</button></form> <form method="post" action="{{ route('payments.reject', $p) }}" class="d-inline-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif" style="max-width:120px"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>@else<span class="small text-muted-2">Validation par une autre personne</span>@endif</td></tr>@endforeach
</tbody></table></div>@else <div class="card-body small text-muted-2">Aucun mouvement en attente.</div> @endif
</div>
<div class="row g-3">
<div class="col-lg-6"><div class="card h-100"><div class="card-header">Acomptes non régularisés ({{ $advances->count() }})</div>
@if($advances->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Agent</th><th>Période</th><th class="num">Acomptes</th><th class="num">Solde restant</th></tr></thead><tbody>@foreach($advances as $e)<tr><td data-l="Agent">{{ $e->employee->fullName() }}</td><td data-l="Période"><a href="{{ route('primes.show', $e) }}">{{ $e->period_label }}</a></td><td data-l="Acomptes" class="num">{{ money($e->advancesAmount(), $e->currency) }}</td><td data-l="Solde" class="num fw-600">{{ money($e->balance(), $e->currency) }}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucun.</div> @endif
</div></div>
<div class="col-lg-6"><div class="card h-100"><div class="card-header">Derniers mouvements traités</div>
@if($recent->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>N°</th><th>Agent</th><th class="num">Montant</th><th>Statut</th></tr></thead><tbody>@foreach($recent as $p)<tr><td data-l="N°"><a href="{{ route('primes.show', $p->prime_entitlement_id) }}">{{ $p->number }}</a></td><td data-l="Agent">{{ $p->employee->fullName() }}</td><td data-l="Montant" class="num">{{ money($p->amount, $p->currency) }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('payment', $p->status) !!} <span class="mini">{{ \App\Support\Labels::status('payment_kind', $p->kind) }}</span></td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">—</div> @endif
</div></div>
</div>
@endsection
