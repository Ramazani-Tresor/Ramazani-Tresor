@extends('layouts.app')
@section('title', $product->exists ? 'Modifier l\'article' : 'Nouvel article')
@section('content')
<div class="page-head"><h1>{{ $product->exists ? 'Modifier « '.$product->name.' »' : 'Nouvel article' }}</h1></div>
<form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
    @csrf @if($product->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-header">Identification</div><div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">Référence *</label><input name="reference" class="form-control" value="{{ old('reference', $product->reference) }}" required maxlength="50"></div>
                <div class="col-md-8"><label class="form-label">Désignation *</label><input name="name" class="form-control" value="{{ old('name', $product->name) }}" required maxlength="190"></div>
                <div class="col-md-4"><label class="form-label">Type *</label>
                    <select name="type" class="form-select" id="typeSel" @disabled($product->exists && $product->movements()->exists())>
                        <option value="product" @selected(old('type', $product->type) === 'product')>Produit physique</option>
                        <option value="service" @selected(old('type', $product->type) === 'service')>Service</option>
                    </select>
                    @if($product->exists && $product->movements()->exists())<input type="hidden" name="type" value="{{ $product->type }}"><div class="form-text">Type figé : l'article a des mouvements.</div>@endif
                </div>
                <div class="col-md-4"><label class="form-label">Catégorie *</label><select name="category_id" class="form-select" required>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
                    @if($categories->isEmpty())<div class="form-text text-danger">Créez d'abord une catégorie.</div>@endif</div>
                <div class="col-md-4"><label class="form-label">Unité de mesure *</label><select name="unit_id" class="form-select" required>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id', $product->unit_id) == $u->id)>{{ $u->name }} ({{ $u->symbol }})</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Code-barres</label><input name="barcode" class="form-control" value="{{ old('barcode', $product->barcode) }}" maxlength="64" placeholder="Scannez ou saisissez (facultatif)"></div>
                <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" maxlength="2000">{{ old('description', $product->description) }}</textarea></div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-header">Prix et stock</div><div class="card-body">
                <div class="mb-3"><label class="form-label">Prix d'achat / coût de référence *</label><div class="input-group"><input name="cost_price" class="form-control text-end" inputmode="decimal" value="{{ old('cost_price', $product->cost_price ?? '0') }}" required><span class="input-group-text">{{ setting('currency_symbol') }}</span></div></div>
                <div class="mb-3"><label class="form-label">Prix de vente *</label><div class="input-group"><input name="sale_price" class="form-control text-end fw-bold" inputmode="decimal" value="{{ old('sale_price', $product->sale_price) }}" required><span class="input-group-text">{{ setting('currency_symbol') }}</span></div>
                    @if($product->exists)<div class="form-text">Un changement de prix n'affecte pas les ventes déjà enregistrées.</div>@endif</div>
                <div class="mb-3"><label class="form-label">Taxe</label><select name="tax_id" class="form-select"><option value="">Aucune</option>@foreach($taxes as $t)<option value="{{ $t->id }}" @selected(old('tax_id', $product->tax_id) == $t->id)>{{ $t->name }} ({{ rtrim(rtrim($t->rate, '0'), '.') }} %)</option>@endforeach</select></div>
                <div class="mb-3 stock-only"><label class="form-label">Seuil d'alerte</label><input name="alert_threshold" class="form-control text-end" inputmode="decimal" value="{{ old('alert_threshold', $product->alert_threshold) }}"></div>
                @unless($product->exists)
                    <div class="mb-3 stock-only"><label class="form-label">Stock initial</label><input name="initial_stock" class="form-control text-end" inputmode="decimal" value="{{ old('initial_stock') }}" placeholder="0">
                        <div class="form-text">Enregistré comme mouvement « Stock initial », valorisé au coût de référence.</div></div>
                @endunless
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $product->is_active))><label class="form-check-label" for="act">Actif (vendable)</label></div>
            </div></div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button><a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn btn-light">Annuler</a></div>
</form>
@endsection
@push('scripts')
<script>
    (function () {
        const sel = document.getElementById('typeSel');
        const sync = () => document.querySelectorAll('.stock-only').forEach(e => e.classList.toggle('d-none', sel.value === 'service'));
        sel.addEventListener('change', sync); sync();
    })();
</script>
@endpush
