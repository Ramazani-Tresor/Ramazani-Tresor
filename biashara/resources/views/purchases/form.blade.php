@extends('layouts.app')
@section('title', $purchase->exists ? 'Modifier le bon '.$purchase->number : 'Nouveau bon d\'entrée')
@section('content')
<div class="page-head"><div><h1>{{ $purchase->exists ? 'Bon '.$purchase->number.' (brouillon)' : 'Nouveau bon d\'entrée' }}</h1><div class="sub">Le stock n'est modifié qu'à la validation du bon.</div></div></div>
@php
    $lines = old('items', $purchase->exists ? $purchase->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost])->all() : [['product_id' => '', 'quantity' => '', 'unit_cost' => '']]);
@endphp
<form method="post" action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}" id="purchaseForm">
    @csrf @if($purchase->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Fournisseur *</label><select name="supplier_id" class="form-select" required><option value="">— Choisir —</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $purchase->supplier_id ?? request('supplier_id')) == $s->id)>{{ $s->name }}</option>@endforeach</select>
            @if($suppliers->isEmpty())<div class="form-text"><a href="{{ route('suppliers.create') }}">Créer un fournisseur</a></div>@endif</div>
        <div class="col-md-3"><label class="form-label">Référence document fournisseur</label><input name="supplier_reference" class="form-control" value="{{ old('supplier_reference', $purchase->supplier_reference) }}" maxlength="80" placeholder="N° facture / bon de livraison"></div>
        <div class="col-md-2"><label class="form-label">Date *</label><input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', optional($purchase->purchase_date)->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="paid_from_cash" value="1" id="pfc" @checked(old('paid_from_cash', $purchase->paid_from_cash))><label class="form-check-label" for="pfc">Payé en espèces depuis ma caisse</label></div></div>
        <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" value="{{ old('notes', $purchase->notes) }}" maxlength="1000"></div>
    </div></div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Articles</span><button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg"></i> Ajouter une ligne</button></div>
        <div class="table-responsive"><table class="table" id="lines">
            <thead><tr><th style="min-width:260px">Article</th><th style="width:140px" class="num">Quantité</th><th style="width:170px" class="num">Coût unitaire</th><th style="width:170px" class="num">Total</th><th></th></tr></thead>
            <tbody>
            @foreach($lines as $i => $l)
                <tr>
                    <td><select name="items[{{ $i }}][product_id]" class="form-select prod" required><option value="">— Article —</option>@foreach($products as $p)<option value="{{ $p->id }}" data-cost="{{ $p->cost_price }}" @selected($l['product_id'] == $p->id)>{{ $p->reference }} — {{ $p->name }}</option>@endforeach</select></td>
                    <td><input name="items[{{ $i }}][quantity]" class="form-control text-end q" inputmode="decimal" value="{{ $l['quantity'] }}" required></td>
                    <td><input name="items[{{ $i }}][unit_cost]" class="form-control text-end c" inputmode="decimal" value="{{ $l['unit_cost'] }}" required></td>
                    <td class="num lt">—</td>
                    <td><button type="button" class="btn btn-sm btn-light text-danger del" aria-label="Supprimer la ligne"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><th colspan="3" class="text-end">Total du bon</th><th class="num" id="grand">—</th><th></th></tr></tfoot>
        </table></div>
    </div>
    <div class="d-flex gap-2 mt-3 flex-wrap">
        <button class="btn btn-light" name="action" value="draft"><i class="bi bi-save me-1"></i>Enregistrer le brouillon</button>
        @if(can_do('purchases.validate'))<button class="btn btn-primary" name="action" value="validate" data-confirm="Valider ce bon ? Le stock et le coût moyen seront mis à jour ; le bon ne sera plus modifiable."><i class="bi bi-check2-circle me-1"></i>Enregistrer et valider</button>@endif
        <a href="{{ route('purchases.index') }}" class="btn btn-link">Annuler</a>
    </div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#lines tbody');
    const toC = (v) => { const n = Number(String(v).replace(/\s/g, '').replace(',', '.')); return isFinite(n) ? n : 0; };
    const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
    function recalc() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const t = Math.round(toC(tr.querySelector('.q').value) * toC(tr.querySelector('.c').value) * 100) / 100; // affichage indicatif ; le serveur recalcule en décimal exact
            tr.querySelector('.lt').textContent = fmt(t); total += t;
        });
        document.getElementById('grand').textContent = fmt(total);
    }
    function renumber() { tbody.querySelectorAll('tr').forEach((tr, i) => tr.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`))); }
    document.getElementById('addLine').addEventListener('click', () => {
        const tr = tbody.querySelector('tr').cloneNode(true);
        tr.querySelectorAll('input').forEach(i => i.value = ''); tr.querySelector('select').value = '';
        tbody.appendChild(tr); renumber(); recalc();
    });
    tbody.addEventListener('click', (e) => { if (e.target.closest('.del') && tbody.rows.length > 1) { e.target.closest('tr').remove(); renumber(); recalc(); } });
    tbody.addEventListener('input', recalc);
    tbody.addEventListener('change', (e) => {
        if (e.target.classList.contains('prod')) { const c = e.target.closest('tr').querySelector('.c'); if (!c.value) c.value = e.target.selectedOptions[0]?.dataset.cost || ''; recalc(); }
    });
    recalc();
})();
</script>
@endpush
