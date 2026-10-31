@extends('layouts.receipt', ['format' => 'a4', 'formats' => false, 'shop' => \App\Support\Settings::all()])
@section('title', 'Bon '.$purchase->number)
@section('body')
    @include('partials.receipt-head', ['shop' => \App\Support\Settings::all()])
    <div class="doc-title">BON D'ENTRÉE EN STOCK</div>
    <table class="meta">
        <tr><td>N°</td><td><strong>{{ $purchase->number }}</strong></td><td>Statut</td><td>{{ ['draft' => 'Brouillon', 'validated' => 'Validé', 'cancelled' => 'Annulé'][$purchase->status] }}</td></tr>
        <tr><td>Fournisseur</td><td>{{ $purchase->supplier->name }}</td><td>Réf. fournisseur</td><td>{{ $purchase->supplier_reference ?? '—' }}</td></tr>
        <tr><td>Date</td><td>{{ $purchase->purchase_date->format('d/m/Y') }}</td><td>Saisi par</td><td>{{ $purchase->user->name }}</td></tr>
    </table>
    <table class="items">
        <thead><tr><th>Réf.</th><th>Article</th><th class="r">Quantité</th><th class="r">Coût unitaire</th><th class="r">Total</th></tr></thead>
        <tbody>@foreach($purchase->items as $i)<tr><td>{{ $i->product->reference }}</td><td>{{ $i->product->name }}</td><td class="r">{{ qty($i->quantity) }} {{ $i->product->unit->symbol }}</td><td class="r">{{ money($i->unit_cost, '') }}</td><td class="r">{{ money($i->line_total, '') }}</td></tr>@endforeach</tbody>
    </table>
    <table class="totals"><tr class="grand"><td>Total</td><td class="r">{{ money($purchase->total) }}</td></tr></table>
    <table style="margin-top:40px"><tr><td>Livré par : ____________________</td><td class="r">Réceptionné par : ____________________</td></tr></table>
@endsection
