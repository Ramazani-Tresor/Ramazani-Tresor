@extends('layouts.app')
@section('title', 'Retour sur la vente '.$sale->number)
@section('content')
<div class="page-head"><div><h1>Retour sur la vente {{ $sale->number }}</h1><div class="sub">{{ $sale->sold_at->format('d/m/Y H:i') }} · {{ $sale->customer?->name ?? 'Client de passage' }} · payé {{ money($sale->amount_paid) }}, reste dû {{ money($sale->amount_due) }}, déjà remboursé {{ money($sale->refunded_total) }}</div></div></div>
<form method="post" action="{{ route('returns.store', $sale) }}" data-confirm="Enregistrer ce retour ? Le stock et la caisse seront mis à jour.">
    @csrf
    <input type="hidden" name="client_uuid" data-uuid value="{{ old('client_uuid') }}">
    <div class="card mb-3">
        <div class="card-header">Articles vendus — saisissez les quantités retournées</div>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Article</th><th class="num">Vendu</th><th class="num">Déjà retourné</th><th class="num">Valeur unitaire</th><th style="width:140px">Qté retournée</th><th style="width:200px">État</th></tr></thead>
            <tbody>
            @foreach($sale->items as $i)
                @php $max = \App\Support\Money::of($i->returnableQuantity()); @endphp
                <tr>
                    <td>{{ $i->product_name }}@if($i->product_type === 'service') <span class="status status-info">Service</span>@endif</td>
                    <td class="num">{{ qty($i->quantity) }}</td>
                    <td class="num">{{ qty($i->returned_quantity) }}</td>
                    <td class="num">{{ money($i->unitValue()) }}</td>
                    <td><input name="lines[{{ $i->id }}][quantity]" class="form-control text-end rq" inputmode="decimal" data-max="{{ (string) $max }}" data-unit="{{ $i->unitValue() }}" value="{{ old('lines.'.$i->id.'.quantity', 0) }}" @disabled($max->isZero())><div class="form-text">max {{ qty($max) }}</div></td>
                    <td><select name="lines[{{ $i->id }}][condition]" class="form-select">
                        <option value="resaleable">Revendable → stock</option>
                        <option value="damaged" @selected(old('lines.'.$i->id.'.condition') === 'damaged')>Endommagé → stock séparé</option>
                    </select></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6"><div class="card"><div class="card-body">
            <div class="mb-3"><label class="form-label">Motif du retour *</label><input name="reason" class="form-control" value="{{ old('reason') }}" required maxlength="255"></div>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Montant remboursé</label><input name="refund_amount" class="form-control text-end" inputmode="decimal" id="refund" value="{{ old('refund_amount') }}" placeholder="Automatique">
                    <div class="form-text">Vide : remboursement maximal autorisé. La valeur retournée éteint d'abord la créance.</div></div>
                <div class="col-md-6"><label class="form-label">Mode de remboursement</label><select name="refund_method_id" class="form-select">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
            </div>
            <div class="mt-2 small">Valeur estimée du retour : <strong id="estValue">0</strong></div>
        </div></div></div>
        @if($needsSupervisor)
        <div class="col-lg-6"><div class="card border-warning"><div class="card-header"><i class="bi bi-shield-lock me-1"></i>Autorisation d'un responsable</div><div class="card-body row g-2">
            <p class="small mb-1">Votre rôle ne permet pas d'autoriser seul un retour. Un responsable doit saisir ses identifiants.</p>
            <div class="col-md-6"><input name="supervisor_username" class="form-control" placeholder="Identifiant du responsable" autocomplete="off" required></div>
            <div class="col-md-6"><input type="password" name="supervisor_password" class="form-control" placeholder="Mot de passe" autocomplete="new-password" required></div>
        </div></div></div>
        @endif
    </div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Enregistrer le retour</button><a href="{{ route('sales.show', $sale) }}" class="btn btn-light">Annuler</a></div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    const inputs = document.querySelectorAll('.rq');
    const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
    function upd() {
        let t = 0;
        inputs.forEach(i => {
            const v = Number(i.value.replace(',', '.')) || 0; const max = Number(i.dataset.max);
            i.classList.toggle('is-invalid', v > max || v < 0);
            t += v * Number(i.dataset.unit);
        });
        document.getElementById('estValue').textContent = fmt(t) + ' {{ setting('currency_symbol') }}';
    }
    inputs.forEach(i => i.addEventListener('input', upd)); upd();
})();
</script>
@endpush
