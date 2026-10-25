@extends('layouts.receipt')
@section('title', 'Retour '.$return->number)
@section('toolbar')<a href="{{ route('sales.show', $return->sale) }}">Fiche de la vente</a>@endsection
@section('body')
    @include('partials.receipt-head')
    <div class="doc-title">{{ $return->is_cancellation ? "JUSTIFICATIF D'ANNULATION" : 'JUSTIFICATIF DE RETOUR' }}</div>
    <table class="meta">
        <tr><td>N°</td><td class="r"><strong>{{ $return->number }}</strong></td></tr>
        <tr><td>Vente d'origine</td><td class="r">{{ $return->sale->number }} du {{ $return->sale->sold_at->format('d/m/Y') }}</td></tr>
        <tr><td>Date</td><td class="r">{{ $return->returned_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Client</td><td class="r">{{ $return->sale->customer?->name ?? 'Client de passage' }}</td></tr>
        <tr><td>Saisi par</td><td class="r">{{ $return->user->name }}</td></tr>
        <tr><td>Autorisé par</td><td class="r">{{ $return->authorizer->name }}</td></tr>
    </table>
    <div class="sep"></div>
    <table class="items">
        <thead><tr><th>Article</th><th class="r">Qté</th><th>État</th><th class="r">Valeur</th></tr></thead>
        @foreach($return->items as $i)
            <tr><td>{{ $i->saleItem->product_name }}</td><td class="r">{{ qty($i->quantity) }}</td><td>{{ $i->condition === 'damaged' ? 'Endommagé' : 'Revendable' }}</td><td class="r">{{ money($i->line_value, '') }}</td></tr>
        @endforeach
    </table>
    <div class="sep"></div>
    <table class="totals">
        <tr class="grand"><td>Valeur retournée</td><td class="r">{{ money($return->total_value) }}</td></tr>
        <tr><td>Imputé sur la créance</td><td class="r">{{ money($return->due_reduction) }}</td></tr>
        <tr><td>Remboursé{{ $return->refundMethod ? ' ('.$return->refundMethod->name.')' : '' }}</td><td class="r">{{ money($return->refund_amount) }}</td></tr>
    </table>
    <p><strong>Motif :</strong> {{ $return->reason }}</p>
    <div class="foot small">Signature du client : ______________________</div>
@endsection
