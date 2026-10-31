@extends('layouts.app')
@section('title', 'Stock')
@section('content')
<div class="page-head"><div><h1>Stock disponible</h1><div class="sub">Quantités issues du journal des mouvements</div></div>
    <div class="d-flex gap-2">@if(can_do('stock.adjust'))<a href="{{ route('stock.exit') }}" class="btn btn-light"><i class="bi bi-box-arrow-right me-1"></i>Perte / retour fournisseur</a>@endif
        @if(can_do('purchases.create'))<a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-truck me-1"></i>Entrée de stock</a>@endif</div></div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        <div style="flex-basis:240px"><input type="search" name="q" class="form-control" placeholder="Article, référence, code-barres" value="{{ request('q') }}"></div>
        <div><select name="category_id" class="form-select"><option value="">Toutes catégories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div><select name="stock" class="form-select"><option value="">Tous</option><option value="low" @selected(request('stock') === 'low')>Sous le seuil</option><option value="out" @selected(request('stock') === 'out')>En rupture</option><option value="damaged" @selected(request('stock') === 'damaged')>Avec stock endommagé</option></select></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Article</th><th>Catégorie</th><th class="num">Vendable</th><th class="num">Endommagé</th><th class="num">Seuil</th>@if($showCost)<th class="num">CMP</th><th class="num">Valeur</th>@endif<th>État</th></tr></thead>
        <tbody>
        @forelse($products as $p)
            <tr>
                <td><a href="{{ route('products.show', $p) }}" class="fw-600">{{ $p->name }}</a><div class="small text-muted-2">{{ $p->reference }}</div></td>
                <td>{{ $p->category->name }}</td>
                <td class="num fw-600">{{ qty($p->stock_quantity) }} {{ $p->unit->symbol }}</td>
                <td class="num">{{ (float) $p->damaged_quantity ? qty($p->damaged_quantity) : '—' }}</td>
                <td class="num">{{ qty($p->alert_threshold) }}</td>
                @if($showCost)<td class="num">{{ money($p->average_cost) }}</td><td class="num">{{ money(\App\Support\Money::mul($p->stock_quantity, $p->average_cost)) }}</td>@endif
                <td>@if((float) $p->stock_quantity <= 0)<span class="status status-danger">Rupture</span>@elseif($p->isLowStock())<span class="status status-warn">Sous le seuil</span>@else<span class="status status-ok">OK</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty"><i class="bi bi-boxes"></i>Aucun article.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $products->links() }}</div>
</div>
@endsection
