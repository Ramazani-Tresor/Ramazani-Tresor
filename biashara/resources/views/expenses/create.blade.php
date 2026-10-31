@extends('layouts.app')
@section('title', 'Nouvelle dépense')
@section('content')
<div class="page-head"><div><h1>Nouvelle dépense</h1><div class="sub">{{ can_do('expenses.approve') ? 'Vous pouvez valider : la dépense sera validée immédiatement.' : 'La dépense sera soumise à validation.' }}</div></div></div>
<div class="card" style="max-width:820px">
    <form class="card-body row g-3" method="post" action="{{ route('expenses.store') }}" enctype="multipart/form-data">@csrf
        <div class="col-md-6"><label class="form-label">Catégorie *</label><select name="expense_category_id" class="form-select" required>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('expense_category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Date *</label><input type="date" name="expense_date" class="form-control" value="{{ old('expense_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
        <div class="col-md-3"><label class="form-label">Montant *</label><input name="amount" class="form-control text-end fw-bold" inputmode="decimal" value="{{ old('amount') }}" required></div>
        <div class="col-md-6"><label class="form-label">Bénéficiaire *</label><input name="beneficiary" class="form-control" value="{{ old('beneficiary') }}" required maxlength="150"></div>
        <div class="col-md-6"><label class="form-label">Mode de paiement *</label><select name="payment_method_id" class="form-select" id="pm">@foreach($paymentMethods as $m)<option value="{{ $m->id }}" data-kind="{{ $m->kind }}" @selected(old('payment_method_id') == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label">Motif *</label><input name="reason" class="form-control" value="{{ old('reason') }}" required maxlength="255"></div>
        <div class="col-md-6"><label class="form-label">Pièce justificative (PDF, JPG, PNG — 4 Mo)</label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="paid_from_cash" value="1" id="pfc" @checked(old('paid_from_cash'))><label class="form-check-label" for="pfc">Payée en espèces depuis la caisse</label></div></div>
        <div class="col-12 small text-muted-2" id="cashHint">La caisse n'est débitée que lorsque la dépense est validée et payée depuis la caisse (session ouverte du valideur){{ $hasSession ? '' : ' — vous n\'avez pas de caisse ouverte actuellement' }}.</div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a href="{{ route('expenses.index') }}" class="btn btn-light">Annuler</a></div>
    </form>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const pm = document.getElementById('pm'), cb = document.getElementById('pfc');
    const sync = () => { const cash = pm.selectedOptions[0]?.dataset.kind === 'cash'; cb.disabled = !cash; if (!cash) cb.checked = false; };
    pm.addEventListener('change', sync); sync();
})();
</script>
@endpush
