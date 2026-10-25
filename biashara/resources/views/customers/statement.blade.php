@extends('layouts.app')
@section('title', 'Relevé — '.$customer->name)
@section('content')
<div class="page-head no-print">
    <div><h1>Relevé de compte</h1><div class="sub">{{ $customer->name }}</div></div>
    <form class="filters" method="get">@include('partials.period')<button class="btn btn-light">Afficher</button><button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button></form>
</div>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between mb-3">
        <div><div class="fw-bold fs-5 text-navy">{{ $shop['shop_name'] }}</div><div class="small">{{ $shop['shop_address'] }} {{ $shop['shop_phone'] }}</div></div>
        <div class="text-end"><div class="fw-bold">RELEVÉ DE COMPTE CLIENT</div><div class="small">{{ $customer->name }} ({{ $customer->code }})<br>Du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</div></div>
    </div>
    <table class="table table-sm">
        <thead><tr><th>Date</th><th>Opération</th><th class="num">Débit</th><th class="num">Crédit</th><th class="num">Solde période</th></tr></thead>
        <tbody>
        @forelse($lines as $l)
            <tr><td>{{ \Illuminate\Support\Carbon::parse($l['date'])->format('d/m/Y H:i') }}</td><td>{{ $l['label'] }}</td><td class="num">{{ (float) $l['debit'] ? money($l['debit']) : '' }}</td><td class="num">{{ (float) $l['credit'] ? money($l['credit']) : '' }}</td><td class="num">{{ money($l['balance']) }}</td></tr>
        @empty
            <tr><td colspan="5" class="empty">Aucune opération sur la période.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr><th colspan="4" class="text-end">Solde total restant dû à ce jour</th><th class="num text-danger">{{ money($balance) }}</th></tr></tfoot>
    </table>
    <p class="small text-muted-2 mb-0">Débit : ventes et remboursements. Crédit : paiements, règlements de créance, retours. Édité le {{ now()->format('d/m/Y H:i') }}.</p>
</div></div>
@endsection
