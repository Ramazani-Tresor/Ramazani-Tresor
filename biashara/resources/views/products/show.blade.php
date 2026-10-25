@extends('layouts.app')
@section('title', $product->name)
@section('content')
<div class="page-head">
    <div><h1>{{ $product->name }}</h1><div class="sub">{{ $product->reference }} · {{ $product->category->name }} · {{ $product->isService() ? 'Service' : 'Produit physique' }}</div></div>
    <div class="d-flex gap-2">
        @if(can_do('products.manage'))
            <a href="{{ route('products.edit', $product) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Modifier</a>
            <form method="post" action="{{ route('products.toggle', $product) }}" data-confirm="{{ $product->is_active ? 'Désactiver cet article ? Il ne pourra plus être vendu, son historique est conservé.' : 'Réactiver cet article ?' }}">@csrf @method('PATCH')
                <button class="btn {{ $product->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $product->is_active ? 'Désactiver' : 'Réactiver' }}</button></form>
        @endif
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            @if($product->photoUrl())<img src="{{ $product->photoUrl() }}" class="card-img-top" alt="" style="max-height:240px;object-fit:cover">@endif
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Prix de vente</span><strong>{{ money($product->sale_price) }}</strong></li>
                @if($showCost)
                    <li class="list-group-item d-flex justify-content-between"><span>Coût de référence</span><span>{{ money($product->cost_price) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Coût moyen pondéré</span><span>{{ money($product->average_cost) }}</span></li>
                @endif
                <li class="list-group-item d-flex justify-content-between"><span>Taxe</span><span>{{ $product->tax ? $product->tax->name.' ('.rtrim(rtrim($product->tax->rate, '0'), '.').' %)' : 'Aucune' }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Unité</span><span>{{ $product->unit->name }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Code-barres</span><span>{{ $product->barcode ?? '—' }}</span></li>
                @unless($product->isService())
                    <li class="list-group-item d-flex justify-content-between"><span>Stock vendable</span><strong>{{ qty($product->stock_quantity) }} {{ $product->unit->symbol }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Stock endommagé</span><span>{{ qty($product->damaged_quantity) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Seuil d'alerte</span><span>{{ qty($product->alert_threshold) }}</span></li>
                @endunless
            </ul>
            @if($product->description)<div class="card-body small">{{ $product->description }}</div>@endif
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Mouvements de stock</div>
            @if($product->isService())
                <div class="empty"><i class="bi bi-info-circle"></i>Un service ne génère aucun mouvement de stock.</div>
            @elseif($movements)
                <div class="table-responsive"><table class="table table-sm">
                    <thead><tr><th>Date</th><th>Type</th><th class="num">Qté</th><th class="num">Solde</th><th>Document</th><th>Utilisateur</th></tr></thead>
                    <tbody>
                    @forelse($movements as $m)
                        <tr>
                            <td class="small">{{ $m->occurred_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $m->typeLabel() }}@if($m->bucket === 'damaged') <span class="status status-warn">endommagé</span>@endif<div class="small text-muted-2">{{ $m->reason }}</div></td>
                            <td class="num {{ (float) $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ (float) $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }}</td>
                            <td class="num">{{ qty($m->balance_after) }}</td>
                            <td class="small">{{ $m->source_number }}</td>
                            <td class="small">{{ $m->user->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Aucun mouvement.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="card-body">{{ $movements->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
