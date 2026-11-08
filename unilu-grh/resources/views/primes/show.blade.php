@extends('layouts.app')
@section('title', 'Prime '.$e->period_label)
@php $m = fn ($v) => money($v, $e->currency); $u = auth()->user(); $bal = $e->balance(); @endphp
@section('content')
@include('partials.head', ['title' => ($e->primeType?->name).' — '.$e->period_label, 'crumbs' => ['Primes' => route('primes.index')], 'sub' => '<a href="'.route('employees.show', $e->employee).'#finances">'.e($e->employee->fullName()).'</a> · '.e($e->employee->matricule).' · '.$e->period_start->format('d/m/Y').' → '.$e->period_end->format('d/m/Y')])
<div class="row g-3 mb-3">
    @foreach([['Taux de la période', $e->base_amount, ''], ['Montant calculé', $e->amount, ''], ['Ajustements', \App\Support\Money::sum($e->adjustments->pluck('amount')->all()), ''], ['Prévu net', $e->netAmount(), 'accent'], ['Payé', $e->paidAmount(), ''], ['Solde', $bal, \App\Support\Money::isNegative($bal) ? 'alert-k' : 'warn-k']] as [$l, $v, $cls])
        <div class="col-6 col-md-4 col-xl-2"><div class="card kpi {{ $cls }}"><div class="l">{{ $l }}</div><div class="v" style="font-size:1.1rem">{{ $m($v) }}</div></div></div>
    @endforeach
</div>
@if(\App\Support\Money::isNegative($bal))<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Trop-perçu de {{ $m(\App\Support\Money::of($bal)->negated()) }} : à récupérer par une régularisation (montant négatif, traçable).</div>@endif
<div class="row g-3">
<div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">Mouvements (acomptes, paiements, régularisations, contrepassations)</div>
    @if($e->payments->count())<div class="table-responsive"><table class="table table-stack align-middle"><thead><tr><th>N°</th><th>Date</th><th>Nature</th><th class="num">Montant</th><th>Mode / référence</th><th>Saisie / validation</th><th>Statut</th><th></th></tr></thead><tbody>
    @foreach($e->payments->sortBy('id') as $p)<tr>
        <td data-l="N°" class="nw">{{ $p->number }}</td><td data-l="Date">{{ $p->payment_date->format('d/m/Y') }}</td><td data-l="Nature">{{ \App\Support\Labels::status('payment_kind', $p->kind) }}</td>
        <td data-l="Montant" class="num fw-600">{{ money($p->amount, $p->currency) }}</td><td data-l="Mode">{{ \App\Support\Labels::status('payment_mode', $p->mode) }} {{ $p->reference }} @if($p->beneficiary)<br><span class="small">Ayant droit : {{ $p->beneficiary }}</span>@endif</td>
        <td data-l="Circuit" class="small">{{ $p->author?->name }} @if($p->validator)/ {{ $p->validator->name }}@endif @if($p->rejection_reason)<br>{{ $p->rejection_reason }}@endif @if($p->notes)<br><span class="text-muted-2">{{ $p->notes }}</span>@endif</td>
        <td data-l="Statut">{!! \App\Support\Labels::badge('payment', $p->status) !!}</td>
        <td class="nw">@if($p->status === 'pending' && $u->hasPermission('payments.validate'))<form method="post" action="{{ route('payments.validate', $p) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary">Valider</button></form> <form method="post" action="{{ route('payments.reject', $p) }}" class="d-inline-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif" style="max-width:110px"><button class="btn btn-sm btn-outline-danger">Rejeter</button></form>
            @elseif($p->status === 'validated' && $p->kind !== 'reversal' && $u->hasPermission('payments.validate'))<form method="post" action="{{ route('payments.reverse', $p) }}" class="d-inline-flex gap-1" data-confirm="Contrepasser {{ $p->number }} ? Une écriture inverse sera créée (rien n’est supprimé).">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Motif" required aria-label="Motif" style="max-width:110px"><button class="btn btn-sm btn-light">Contrepasser</button></form>@endif</td>
    </tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">Aucun mouvement.</div> @endif
    </div>
    <div class="card"><div class="card-header">Ajustements du droit</div>
    @if($e->adjustments->count())<ul class="list-group list-group-flush">@foreach($e->adjustments as $a)<li class="list-group-item"><div class="d-flex justify-content-between"><b class="{{ \App\Support\Money::isNegative($a->amount) ? 'text-danger' : '' }}">{{ $m($a->amount) }}</b><span class="small text-muted-2">{{ $a->created_at->format('d/m/Y H:i') }} · {{ $a->author?->name ?? 'Système' }}</span></div><div class="small">{{ $a->reason }}</div></li>@endforeach</ul>@else <div class="card-body small text-muted-2">Aucun ajustement.</div> @endif
    @if($u->hasPermission('payments.create'))<div class="card-body border-top"><form method="post" action="{{ route('primes.adjust', $e) }}" class="row g-2 align-items-end">@csrf
        @include('partials.field', ['name' => 'amount', 'label' => 'Montant signé (négatif = réduction)', 'type' => 'number', 'required' => true, 'col' => 'col-md-4', 'attrs' => 'step="0.01"'])
        @include('partials.field', ['name' => 'reason', 'label' => 'Motif', 'required' => true, 'col' => 'col-md-6'])
        <div class="col-md-2"><button class="btn btn-light w-100">Ajuster</button></div>
    </form></div>@endif
    </div>
</div>
<div class="col-lg-5">
    @if($u->hasPermission('payments.create'))
    <div class="card mb-3"><div class="card-header">Saisir un mouvement</div><div class="card-body"><form method="post" action="{{ route('primes.record', $e) }}" class="row g-2">@csrf
        @include('partials.field', ['name' => 'kind', 'label' => 'Nature', 'type' => 'select', 'required' => true, 'options' => ['payment' => 'Paiement du solde (acomptes déduits)', 'advance' => 'Acompte', 'regularization' => 'Régularisation d’un trop-perçu'], 'col' => 'col-12'])
        @include('partials.field', ['name' => 'amount', 'label' => 'Montant ('.$e->currency.')', 'type' => 'decimal', 'col' => 'col-md-6', 'help' => 'Paiement : laisser vide pour payer tout le solde restant.'])
        @include('partials.field', ['name' => 'payment_date', 'label' => 'Date', 'type' => 'date', 'value' => now()->toDateString(), 'required' => true, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'mode', 'label' => 'Mode', 'type' => 'select', 'required' => true, 'options' => \App\Support\Labels::options('payment_mode'), 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'reference', 'label' => 'Référence', 'col' => 'col-md-6'])
        @if($e->employee->status === 'deceased')@include('partials.field', ['name' => 'beneficiary', 'label' => 'Ayant droit bénéficiaire', 'required' => true, 'col' => 'col-12'])@endif
        @include('partials.field', ['name' => 'notes', 'label' => 'Observations', 'col' => 'col-12'])
        <div class="col-12"><button class="btn btn-primary">Enregistrer (à valider)</button></div>
    </form></div></div>
    @endif
    <div class="card"><div class="card-header">Détail du calcul</div><div class="card-body small">
        @php $c = $e->calculation ?? []; @endphp
        <dl class="kv"><dt>Jours de la période</dt><dd>{{ $c['jours'] ?? '—' }}</dd><dt>Jours à 100 %</dt><dd>{{ $c['jours_pleins'] ?? '—' }}</dd><dt>Jours réduits</dt><dd>{{ $c['jours_reduits'] ?? '—' }}</dd><dt>Jours sans droit</dt><dd>{{ $c['jours_sans_droit'] ?? '—' }}</dd><dt>Ratio</dt><dd>{{ $e->eligible_ratio }}</dd><dt>Prorata</dt><dd>{{ ($c['prorata'] ?? true) ? 'oui' : 'non' }}</dd><dt>Règles</dt><dd>{{ implode(' ; ', $c['regles'] ?? []) ?: 'Aucune situation particulière' }}</dd></dl>
    </div></div>
</div>
</div>
@endsection
