@extends('layouts.app')
@section('title', 'Sortie de stock')
@section('content')
<div class="page-head"><div><h1>Sortie de stock</h1><div class="sub">Pertes, casses, produits détériorés, retours fournisseurs, mise au rebut</div></div></div>
<div class="card" style="max-width:720px">
    <form class="card-body row g-3" method="post" action="{{ route('stock.exit.store') }}" data-confirm="Confirmer cette sortie de stock ? Elle sera tracée dans le journal des mouvements.">
        @csrf
        <div class="col-12"><label class="form-label">Article *</label><select name="product_id" class="form-select" required><option value="">— Choisir —</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} — vendable {{ qty($p->stock_quantity) }}, endommagé {{ qty($p->damaged_quantity) }} {{ $p->unit->symbol }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Nature *</label><select name="type" class="form-select" required>
            <option value="loss" @selected(old('type') === 'loss')>Perte / casse / détérioration (stock vendable)</option>
            <option value="supplier_return" @selected(old('type') === 'supplier_return')>Retour au fournisseur (stock vendable)</option>
            <option value="damaged_writeoff" @selected(old('type') === 'damaged_writeoff')>Mise au rebut du stock endommagé</option>
        </select></div>
        <div class="col-md-6"><label class="form-label">Quantité *</label><input name="quantity" class="form-control text-end" inputmode="decimal" value="{{ old('quantity') }}" required></div>
        <div class="col-12"><label class="form-label">Motif détaillé *</label><input name="reason" class="form-control" value="{{ old('reason') }}" required maxlength="255" placeholder="Ex. : 3 bouteilles cassées lors du déchargement"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Enregistrer la sortie</button><a href="{{ route('stock.index') }}" class="btn btn-light">Annuler</a></div>
    </form>
</div>
@endsection
