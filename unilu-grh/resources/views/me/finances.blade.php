@extends('layouts.app')
@section('title', 'Mes primes')
@section('content')
@include('partials.head', ['title' => 'Mes primes, acomptes et soutiens', 'sub' => 'Montants par devise. Prévu = droit net de la période ; solde = prévu − montants payés.'])
@include('me._tabs')
@forelse($summary as $cur => $t)
<div class="row g-3 mb-3">@foreach([['Primes prévues', 'net', 'accent'], ['Primes payées', 'paid', ''], ['Acomptes', 'advances', ''], ['Solde restant', 'balance', 'warn-k'], ['Arriérés', 'arrears', 'alert-k']] as [$l, $k, $cls])<div class="col-6 col-md"><div class="card kpi {{ $cls }}"><div class="l">{{ $l }}</div><div class="v" style="font-size:1.15rem">{{ money($t[$k], $cur) }}</div></div></div>@endforeach</div>
@empty <div class="card mb-3"><div class="card-body small text-muted-2">Aucune prime enregistrée.</div></div> @endforelse
<div class="card mb-3"><div class="card-header">Détail par période</div>
@if($entitlements->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Période</th><th>Prime</th><th class="num">Prévu</th><th class="num">Payé</th><th class="num">dont acomptes</th><th class="num">Solde</th></tr></thead><tbody>@foreach($entitlements as $en)<tr><td data-l="Période">{{ $en->period_label }}</td><td data-l="Prime">{{ $en->primeType?->name }}</td><td data-l="Prévu" class="num">{{ money($en->netAmount(), $en->currency) }}</td><td data-l="Payé" class="num">{{ money($en->paidAmount(), $en->currency) }}</td><td data-l="Acomptes" class="num">{{ money($en->advancesAmount(), $en->currency) }}</td><td data-l="Solde" class="num fw-600">{{ money($en->balance(), $en->currency) }}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">—</div> @endif
</div>
<div class="row g-3">
<div class="col-lg-7"><div class="card"><div class="card-header">Paiements et acomptes</div>@if($payments->count())<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>Date</th><th>Nature</th><th>Période</th><th class="num">Montant</th><th>Statut</th></tr></thead><tbody>@foreach($payments as $p)<tr><td data-l="Date">{{ $p->payment_date->format('d/m/Y') }}</td><td data-l="Nature">{{ \App\Support\Labels::status('payment_kind', $p->kind) }}</td><td data-l="Période">{{ $p->entitlement->period_label }}</td><td data-l="Montant" class="num">{{ money($p->amount, $p->currency) }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('payment', $p->status) !!}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body small text-muted-2">—</div> @endif</div></div>
<div class="col-lg-5"><div class="card"><div class="card-header">Soutiens sociaux</div><ul class="list-group list-group-flush">@forelse($supports as $s)<li class="list-group-item"><b>{{ $s->type->name }}</b> {!! \App\Support\Labels::badge('support', $s->status) !!}<br><span class="small">{{ $s->amount_granted !== null ? money($s->amount_granted, $s->currency) : 'Montant à déterminer' }} {{ $s->deceased_name ? '— '.$s->deceased_name : '' }}</span></li>@empty<li class="list-group-item small text-muted-2">Aucun.</li>@endforelse</ul></div></div>
</div>
@endsection
