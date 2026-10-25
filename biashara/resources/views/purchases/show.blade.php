@extends('layouts.app')
@section('title', 'Bon '.$purchase->number)
@section('content')
<div class="page-head">
    <div><h1>Bon d'entrée {{ $purchase->number }} @include('purchases._status')</h1>
        <div class="sub">{{ $purchase->supplier->name }} · {{ $purchase->purchase_date->format('d/m/Y') }}{{ $purchase->supplier_reference ? ' · réf. '.$purchase->supplier_reference : '' }}</div></div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('purchases.print', $purchase) }}" target="_blank" class="btn btn-light"><i class="bi bi-printer me-1"></i>Imprimer</a>
        @if($purchase->isDraft())
            @if(can_do('purchases.create'))<a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Modifier</a>
                <form method="post" action="{{ route('purchases.cancel', $purchase) }}" data-confirm="Annuler ce brouillon ?">@csrf<button class="btn btn-outline-danger">Annuler le brouillon</button></form>@endif
            @if(can_do('purchases.validate'))<form method="post" action="{{ route('purchases.validate', $purchase) }}" data-confirm="Valider ce bon ? Le stock sera augmenté et le coût moyen recalculé.">@csrf<button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Valider l'entrée</button></form>@endif
        @endif
    </div>
</div>
<div class="card">
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Référence</th><th>Article</th><th class="num">Quantité</th><th class="num">Coût unitaire</th><th class="num">Total</th></tr></thead>
        <tbody>@foreach($purchase->items as $i)<tr><td>{{ $i->product->reference }}</td><td>{{ $i->product->name }}</td><td class="num">{{ qty($i->quantity) }} {{ $i->product->unit->symbol }}</td><td class="num">{{ money($i->unit_cost) }}</td><td class="num">{{ money($i->line_total) }}</td></tr>@endforeach</tbody>
        <tfoot><tr><th colspan="4" class="text-end">Total</th><th class="num">{{ money($purchase->total) }}</th></tr></tfoot>
    </table></div>
    <div class="card-body small text-muted-2">
        Saisi par {{ $purchase->user->name }} le {{ $purchase->created_at->format('d/m/Y H:i') }}
        @if($purchase->validator) · validé par {{ $purchase->validator->name }} le {{ $purchase->validated_at->format('d/m/Y H:i') }}@endif
        · {{ $purchase->paid_from_cash ? 'Payé en espèces depuis la caisse' : 'Paiement hors caisse' }}
        @if($purchase->notes)<div class="mt-1">Notes : {{ $purchase->notes }}</div>@endif
    </div>
</div>
@endsection
