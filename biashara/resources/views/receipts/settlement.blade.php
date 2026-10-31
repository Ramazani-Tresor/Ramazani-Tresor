@extends('layouts.receipt')
@section('title', 'Règlement '.$settlement->number)
@section('toolbar')<a href="{{ route('customers.show', $settlement->customer) }}">Fiche client</a>@endsection
@section('body')
    @include('partials.receipt-head')
    <div class="doc-title">REÇU DE RÈGLEMENT DE CRÉANCE</div>
    <table class="meta">
        <tr><td>N°</td><td class="r"><strong>{{ $settlement->number }}</strong></td></tr>
        <tr><td>Date</td><td class="r">{{ $settlement->paid_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Client</td><td class="r">{{ $settlement->customer->name }}</td></tr>
        <tr><td>Mode</td><td class="r">{{ $settlement->method->name }}{{ $settlement->reference ? ' — '.$settlement->reference : '' }}</td></tr>
        <tr><td>Reçu par</td><td class="r">{{ $settlement->user->name }}</td></tr>
    </table>
    <div class="sep"></div>
    <table class="items">
        <thead><tr><th>Vente réglée</th><th class="r">Montant imputé</th></tr></thead>
        @foreach($settlement->allocations as $a)
            <tr><td>{{ $a->sale->number }} du {{ $a->sale->sold_at->format('d/m/Y') }}</td><td class="r">{{ money($a->amount, '') }}</td></tr>
        @endforeach
    </table>
    <div class="sep"></div>
    <table class="totals">
        <tr class="grand"><td>Montant réglé</td><td class="r">{{ money($settlement->amount) }}</td></tr>
        <tr><td>Solde restant dû à ce jour</td><td class="r">{{ money($balance) }}</td></tr>
    </table>
    <div class="foot">{{ $shop['receipt_footer'] }}</div>
@endsection
