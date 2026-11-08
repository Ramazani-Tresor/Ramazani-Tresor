@extends('layouts.app')
@section('title', 'Caisse '.$session->number)
@section('content')
<div class="page-head">
    <div><h1>Caisse {{ $session->number }} {!! $session->isOpen() ? '<span class="status status-ok">Ouverte</span>' : '<span class="status status-muted">Clôturée</span>' !!}</h1>
        <div class="sub">{{ $session->user->name }} · ouverte le {{ $session->opened_at->format('d/m/Y à H:i') }}@if($session->closed_at) · clôturée le {{ $session->closed_at->format('d/m/Y à H:i') }} par {{ $session->closer?->name }}@endif</div></div>
    <div class="d-flex gap-2 no-print">
        <button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ $session->isOpen() ? 'Imprimer la situation' : 'Imprimer le rapport de clôture' }}</button>
        @if($session->isOpen() && $isMine && can_do('sales.create'))<a href="{{ route('pos') }}" class="btn btn-primary"><i class="bi bi-cart3 me-1"></i>Vendre</a>@endif
    </div>
</div>
<div class="d-none d-print-block mb-3"><strong>{{ $shop['shop_name'] }}</strong> — {{ $session->isOpen() ? 'Situation de caisse' : 'Rapport de clôture de caisse' }} {{ $session->number }}, imprimé le {{ now()->format('d/m/Y H:i') }}</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Fonds initial</div><div class="value">{{ money($session->opening_amount) }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi accent"><div class="label">Espèces théoriques</div><div class="value">{{ money($session->isOpen() ? $expected : $session->expected_cash) }}</div><div class="hint">fonds + entrées − sorties en espèces</div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Paiements électroniques</div><div class="value">{{ money($session->isOpen() ? $electronic : $session->electronic_total) }}</div><div class="hint">hors tiroir-caisse</div></div></div>
    @if(!$session->isOpen())
        <div class="col-6 col-lg-3"><div class="card kpi"><div class="label">Compté / écart</div><div class="value">{{ money($session->counted_cash) }}</div><div class="hint {{ (float) $session->difference ? 'text-danger fw-600' : 'text-success' }}">écart : {{ money($session->difference) }}</div></div></div>
    @endif
</div>
@if(!$session->isOpen() && $session->closing_notes)<div class="alert alert-light border"><strong>Justification :</strong> {{ $session->closing_notes }}</div>@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card"><div class="card-header">Mouvements de caisse</div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Heure</th><th>Type</th><th>Mode</th><th>Libellé</th><th>Par</th><th class="num">Montant</th></tr></thead>
                <tbody>@forelse($movements as $m)
                    <tr><td class="small">{{ $m->created_at->format('d/m H:i') }}</td><td>{{ $m->typeLabel() }}</td><td>{{ $m->paymentMethod->name }}</td><td class="small">{{ $m->description }}</td><td class="small">{{ $m->user->name }}</td><td class="num {{ (float) $m->amount < 0 ? 'text-danger' : 'text-success' }}">{{ money($m->amount) }}</td></tr>
                @empty<tr><td colspan="6" class="empty">Aucun mouvement.</td></tr>@endforelse</tbody>
            </table></div><div class="card-body no-print">{{ $movements->links() }}</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header">Totaux par mode de paiement</div>
            <ul class="list-group list-group-flush">@foreach($byMethod as $b)<li class="list-group-item d-flex justify-content-between"><span>{{ $b['method'] }} <small class="text-muted-2">({{ $b['kind'] === 'cash' ? 'espèces' : 'électronique' }})</small></span><span class="money">{{ money($b['total']) }}</span></li>@endforeach</ul></div>

        @if($session->isOpen() && $isMine)
        <div class="card mb-3 no-print"><div class="card-header">Apport / retrait d'espèces</div>
            <form class="card-body" method="post" action="{{ route('cash.movement') }}" data-confirm="Enregistrer ce mouvement de caisse ?">@csrf
                <div class="btn-group w-100 mb-2" role="group"><input type="radio" class="btn-check" name="type" value="deposit" id="tDep" checked><label class="btn btn-outline-primary" for="tDep">Apport</label><input type="radio" class="btn-check" name="type" value="withdrawal" id="tWit"><label class="btn btn-outline-primary" for="tWit">Retrait</label></div>
                <input name="amount" class="form-control text-end mb-2" inputmode="decimal" placeholder="Montant" required>
                <input name="description" class="form-control mb-2" placeholder="Motif (ex. dépôt en banque)" required maxlength="255">
                <button class="btn btn-light w-100">Enregistrer</button>
            </form></div>
        @endif

        @if($canClose)
        <div class="card border-primary no-print"><div class="card-header">Clôturer la caisse</div>
            <form class="card-body" method="post" action="{{ route('cash.close', $session) }}" data-confirm="Clôturer la caisse ? Plus aucune opération ne sera possible sur cette session.">@csrf
                <label class="form-label">Espèces réellement comptées *</label>
                <div class="input-group input-group-lg mb-2"><input name="counted_cash" id="counted" class="form-control text-end fw-bold" inputmode="decimal" required><span class="input-group-text">{{ setting('currency_symbol') }}</span></div>
                <div class="small mb-2">Théorique : <strong>{{ money($expected) }}</strong> · Écart : <strong id="gap">—</strong></div>
                <textarea name="closing_notes" class="form-control mb-2" rows="2" placeholder="Justification (obligatoire en cas d'écart)" maxlength="1000"></textarea>
                <button class="btn btn-primary w-100"><i class="bi bi-lock me-1"></i>Clôturer</button>
            </form></div>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const c = document.getElementById('counted'); if (!c) return;
    const expected = @json((string) $expected);
    const toC = (v) => Math.round(Number(String(v).replace(/\s/g, '').replace(',', '.')) * 100);
    c.addEventListener('input', () => {
        const d = toC(c.value) - toC(expected);
        const g = document.getElementById('gap');
        g.textContent = isFinite(d) ? new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2 }).format(d / 100) : '—';
        g.className = d === 0 ? 'text-success' : 'text-danger';
    });
    @if($print) window.addEventListener('load', () => window.print()); @endif
})();
</script>
@endpush
