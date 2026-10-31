@extends('layouts.app')
@section('title', 'Produits et services')
@section('content')
<div class="page-head">
    <div><h1>Produits et services</h1><div class="sub">{{ $products->total() }} article(s)</div></div>
    <div class="d-flex gap-2 flex-wrap">
        @if(can_do('products.import'))<a href="{{ route('products.import') }}" class="btn btn-light"><i class="bi bi-file-earmark-arrow-up me-1"></i>Importer</a>@endif
        @if(can_do('products.manage'))<a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvel article</a>@endif
    </div>
</div>
<div class="card">
    <div class="card-body border-bottom">
        <form class="filters" method="get">
            <div style="flex-basis:240px"><input type="search" name="q" class="form-control" placeholder="Nom, référence, code-barres…" value="{{ request('q') }}"></div>
            <div><select name="category_id" class="form-select"><option value="">Toutes catégories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div><select name="type" class="form-select"><option value="">Produits et services</option><option value="product" @selected(request('type') === 'product')>Produits</option><option value="service" @selected(request('type') === 'service')>Services</option></select></div>
            <div><select name="stock" class="form-select"><option value="">Tout stock</option><option value="low" @selected(request('stock') === 'low')>Sous le seuil</option><option value="out" @selected(request('stock') === 'out')>En rupture</option></select></div>
            <div><select name="status" class="form-select"><option value="">Actifs et inactifs</option><option value="active" @selected(request('status') === 'active')>Actifs</option><option value="inactive" @selected(request('status') === 'inactive')>Inactifs</option></select></div>
            <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Référence</th><th>Désignation</th><th>Catégorie</th><th class="num">Prix de vente</th>@if($showCost)<th class="num">CMP</th>@endif<th class="num">Stock</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($products as $p)
            <tr>
                <td class="small text-muted-2">{{ $p->reference }}</td>
                <td><a href="{{ route('products.show', $p) }}" class="fw-600">{{ $p->name }}</a>@if($p->isService()) <span class="status status-info">Service</span>@endif
                    @if($p->barcode)<div class="small text-muted-2"><i class="bi bi-upc"></i> {{ $p->barcode }}</div>@endif</td>
                <td>{{ $p->category->name }}</td>
                <td class="num">{{ money($p->sale_price) }}</td>
                @if($showCost)<td class="num text-muted-2">{{ money($p->average_cost) }}</td>@endif
                <td class="num">
                    @if($p->isService())—@else
                        <span class="status {{ (float) $p->stock_quantity <= 0 ? 'status-danger' : ($p->isLowStock() ? 'status-warn' : 'status-ok') }}">{{ qty($p->stock_quantity) }} {{ $p->unit->symbol }}</span>
                    @endif
                </td>
                <td>{!! $p->is_active ? '<span class="status status-ok">Actif</span>' : '<span class="status status-muted">Inactif</span>' !!}</td>
                <td class="text-end text-nowrap">@if(can_do('products.manage'))<a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-light" title="Modifier"><i class="bi bi-pencil"></i></a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty"><i class="bi bi-box-seam"></i>Aucun article trouvé.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $products->links() }}</div>
</div>
@endsection
