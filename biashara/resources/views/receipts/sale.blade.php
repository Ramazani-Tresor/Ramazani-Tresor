@extends('layouts.receipt')
@section('title', 'Reçu '.$sale->number)
@section('toolbar')
    <a href="{{ route('sales.pdf', [$sale, 'format' => $format]) }}">PDF</a>
    <a href="{{ route('sales.show', $sale) }}">Fiche de la vente</a>
@endsection
@section('body')
    @include('partials.receipt-head')
    <div class="doc-title">{{ $format === 'a4' ? 'FACTURE / REÇU' : 'REÇU DE VENTE' }}</div>
    @if($duplicate)<div class="dup">DUPLICATA</div>@endif
    @if($sale->isCancelled())<div class="dup">VENTE ANNULÉE</div>@endif
    <table class="meta">
        <tr><td>N°</td><td class="r"><strong>{{ $sale->number }}</strong></td></tr>
        <tr><td>Date</td><td class="r">{{ $sale->sold_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Caissier</td><td class="r">{{ $sale->user->name }}</td></tr>
        <tr><td>Client</td><td class="r">{{ $sale->customer?->name ?? 'Client de passage' }}</td></tr>
        @if($sale->customer?->phone)<tr><td>Tél. client</td><td class="r">{{ $sale->customer->phone }}</td></tr>@endif
        @if($sale->origin === 'offline')<tr><td colspan="2" class="small">Vente saisie hors ligne, validée à la synchronisation.</td></tr>@endif
    </table>

    @if($format === 'a4')
        <table class="items">
            <thead><tr><th>Réf.</th><th>Désignation</th><th class="r">Qté</th><th class="r">P.U.</th><th class="r">Remise</th><th class="r">Taxe</th><th class="r">Total</th></tr></thead>
            <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $item->product_reference }}</td>
                    <td>{{ $item->product_name }}@if($item->tax_name)<div class="small">{{ $item->tax_name }} {{ rtrim(rtrim($item->tax_rate, '0'), '.') }} %</div>@endif</td>
                    <td class="r">{{ qty($item->quantity) }} {{ $item->unit_symbol }}</td>
                    <td class="r">{{ money($item->unit_price, '') }}</td>
                    <td class="r">{{ (float) $item->discount_amount ? money($item->discount_amount, '') : '—' }}</td>
                    <td class="r">{{ (float) $item->tax_amount ? money($item->tax_amount, '') : '—' }}</td>
                    <td class="r">{{ money($item->line_total, '') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="sep"></div>
        <table class="items">
            @foreach($sale->items as $item)
                <tr><td colspan="2">{{ $item->product_name }}</td></tr>
                <tr>
                    <td class="small">{{ qty($item->quantity) }} × {{ money($item->unit_price, '') }}@if((float) $item->discount_amount) − {{ money($item->discount_amount, '') }}@endif @if((float) $item->tax_amount) + tx {{ money($item->tax_amount, '') }}@endif</td>
                    <td class="r">{{ money($item->line_total, '') }}</td>
                </tr>
            @endforeach
        </table>
        <div class="sep"></div>
    @endif

    <table class="totals">
        <tr><td>Sous-total</td><td class="r">{{ money($sale->subtotal) }}</td></tr>
        @if((float) $sale->discount_total)<tr><td>Remises</td><td class="r">−{{ money($sale->discount_total) }}</td></tr>@endif
        @if((float) $sale->tax_total)<tr><td>Taxes</td><td class="r">{{ money($sale->tax_total) }}</td></tr>@endif
        <tr class="grand"><td>NET À PAYER</td><td class="r">{{ money($sale->total) }}</td></tr>
        @foreach($sale->payments->where('kind', 'sale') as $p)
            <tr><td>{{ $p->method->name }}@if($p->currency !== $sale->currency) ({{ money($p->currency_amount, $p->currency) }} × {{ rtrim(rtrim($p->exchange_rate, '0'), '.') }})@endif</td><td class="r">{{ money($p->amount) }}</td></tr>
        @endforeach
        <tr><td>Montant reçu</td><td class="r">{{ money($sale->amount_received) }}</td></tr>
        @if((float) $sale->change_given)<tr><td>Monnaie rendue</td><td class="r">{{ money($sale->change_given) }}</td></tr>@endif
        @if($sale->payments->where('kind', 'debt_settlement')->count())<tr><td>Règlements ultérieurs</td><td class="r">{{ money($sale->payments->where('kind', 'debt_settlement')->sum('amount')) }}</td></tr>@endif
        @if((float) $sale->returned_total)<tr><td>Retours</td><td class="r">−{{ money($sale->returned_total) }}</td></tr>@endif
        <tr><td><strong>Solde restant dû</strong></td><td class="r"><strong>{{ money($sale->amount_due) }}</strong></td></tr>
    </table>

    <div class="foot">
        <div>{{ $shop['receipt_footer'] }}</div>
        <div class="small">Articles : {{ qty($sale->items->sum('quantity')) }} · Imprimé le {{ now()->format('d/m/Y H:i') }}</div>
    </div>
@endsection
