@extends('layouts.app')
@section('title', 'Mouvements de stock')
@section('content')
<div class="page-head"><div><h1>Journal des mouvements de stock</h1><div class="sub">Chaque variation de quantité, avec son motif, son auteur et son document d'origine</div></div></div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        @include('partials.period')
        <div><label class="form-label small mb-1">Article</label><select name="product_id" class="form-select"><option value="">Tous</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-1">Type</label><select name="type" class="form-select"><option value="">Tous</option>@foreach(\App\Models\StockMovement::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-1">Utilisateur</label><select name="user_id" class="form-select"><option value="">Tous</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-sm table-hover">
        <thead><tr><th>Date</th><th>Article</th><th>Type</th><th class="num">Quantité</th><th class="num">Solde</th><th>Document</th><th>Motif</th><th>Utilisateur</th></tr></thead>
        <tbody>
        @forelse($movements as $m)
            <tr>
                <td class="small text-nowrap">{{ $m->occurred_at->format('d/m/Y H:i') }}</td>
                <td><a href="{{ route('products.show', $m->product) }}">{{ $m->product->name }}</a></td>
                <td>{{ $m->typeLabel() }}@if($m->bucket === 'damaged') <span class="status status-warn">endommagé</span>@endif</td>
                <td class="num {{ (float) $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ (float) $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }}</td>
                <td class="num">{{ qty($m->balance_after) }}</td>
                <td class="small">{{ $m->source_number }}</td>
                <td class="small">{{ $m->reason }}</td>
                <td class="small">{{ $m->user->name }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty"><i class="bi bi-arrow-left-right"></i>Aucun mouvement sur la période.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $movements->links() }}</div>
</div>
@endsection
