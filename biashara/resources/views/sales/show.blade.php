@extends('layouts.app')
@section('title', 'Vente '.$sale->number)
@section('content')
<div class="page-head">
    <div><h1>Vente {{ $sale->number }} @include('sales._status')</h1>
        <div class="sub">{{ $sale->sold_at->format('d/m/Y à H:i') }} · {{ $sale->user->name }} · {{ $sale->customer?->name ?? 'Client de passage' }}{{ $sale->origin === 'offline' ? ' · saisie hors ligne' : '' }}</div></div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group">
            <a href="{{ route('sales.receipt', [$sale, 'format' => '80']) }}" target="_blank" class="btn btn-light"><i class="bi bi-printer me-1"></i>Ticket</a>
            <a href="{{ route('sales.receipt', [$sale, 'format' => 'a4']) }}" target="_blank" class="btn btn-light">A4</a>
            <a href="{{ route('sales.pdf', [$sale, 'format' => 'a4']) }}" class="btn btn-light"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
        @if(!$sale->isCancelled())
            @if(can_do('returns.create'))<a href="{{ route('returns.create', $sale) }}" class="btn btn-outline-primary"><i class="bi bi-arrow-counterclockwise me-1"></i>Retour</a>@endif
            @if(can_do('sales.cancel'))<button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="bi bi-x-octagon me-1"></i>Annuler la vente</button>@endif
            @if((float) $sale->amount_due && $sale->customer && can_do('customers.settle'))<a href="{{ route('customers.show', $sale->customer) }}#reglement" class="btn btn-primary"><i class="bi bi-cash me-1"></i>Encaisser la créance</a>@endif
        @endif
    </div>
</div>
@if($sale->isCancelled())
    <div class="alert alert-secondary"><i class="bi bi-x-octagon me-1"></i>Vente annulée le {{ $sale->cancelled_at->format('d/m/Y H:i') }} par {{ $sale->canceller?->name }} — motif : {{ $sale->cancel_reason }}. Elle reste consultable ; les opérations correctrices figurent ci-dessous.</div>
@endif
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header">Articles (prix et désignations au moment de la vente)</div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Article</th><th class="num">Qté</th><th class="num">P.U.</th><th class="num">Remise</th><th class="num">Taxe</th><th class="num">Total</th><th class="num">Retourné</th></tr></thead>
                <tbody>@foreach($sale->items as $i)
                    <tr><td>{{ $i->product_name }}<div class="small text-muted-2">{{ $i->product_reference }}</div></td><td class="num">{{ qty($i->quantity) }} {{ $i->unit_symbol }}</td><td class="num">{{ money($i->unit_price) }}</td><td class="num">{{ money($i->discount_amount) }}</td><td class="num">{{ money($i->tax_amount) }}</td><td class="num fw-600">{{ money($i->line_total) }}</td><td class="num">{{ (float) $i->returned_quantity ? qty($i->returned_quantity) : '—' }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </div>
        <div class="card mb-3"><div class="card-header">Paiements, règlements et remboursements</div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Date</th><th>Nature</th><th>Mode</th><th>Devise reçue</th><th>Par</th><th class="num">Montant</th></tr></thead>
                <tbody>@forelse($sale->payments as $p)
                    <tr><td>{{ $p->paid_at->format('d/m/Y H:i') }}</td><td>{{ \App\Models\Payment::KINDS[$p->kind] }}</td><td>{{ $p->method->name }}{{ $p->reference ? ' · '.$p->reference : '' }}</td><td>{{ $p->currency !== $sale->currency ? money($p->currency_amount, $p->currency).' au taux '.rtrim(rtrim($p->exchange_rate, '0'), '.') : $p->currency }}</td><td>{{ $p->user->name }}</td><td class="num {{ (float) $p->amount < 0 ? 'text-danger' : '' }}">{{ money($p->amount) }}</td></tr>
                @empty<tr><td colspan="6" class="empty">Aucun paiement (vente à crédit).</td></tr>@endforelse</tbody>
            </table></div>
        </div>
        @if($sale->returns->isNotEmpty())
            <div class="card"><div class="card-header">Retours et annulations</div>
                <div class="table-responsive"><table class="table"><thead><tr><th>N°</th><th>Date</th><th>Motif</th><th>Autorisé par</th><th class="num">Valeur</th><th class="num">Remboursé</th></tr></thead><tbody>
                @foreach($sale->returns as $r)<tr><td><a href="{{ route('returns.show', $r) }}" target="_blank">{{ $r->number }}</a></td><td>{{ $r->returned_at->format('d/m/Y H:i') }}</td><td>{{ $r->reason }}</td><td>{{ $r->authorizer->name }}</td><td class="num">{{ money($r->total_value) }}</td><td class="num">{{ money($r->refund_amount) }}</td></tr>@endforeach
                </tbody></table></div></div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="card"><ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between"><span>Sous-total</span><span>{{ money($sale->subtotal) }}</span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Remises</span><span>−{{ money($sale->discount_total) }}</span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Taxes</span><span>{{ money($sale->tax_total) }}</span></li>
            <li class="list-group-item d-flex justify-content-between fs-5 fw-bold"><span>Total</span><span>{{ money($sale->total) }}</span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Montant reçu</span><span>{{ money($sale->amount_received) }}</span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Monnaie rendue</span><span>{{ money($sale->change_given) }}</span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Payé (imputé)</span><span>{{ money($sale->amount_paid) }}</span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Retours</span><span>{{ money($sale->returned_total) }}</span></li>
            <li class="list-group-item d-flex justify-content-between"><span>Remboursé</span><span>{{ money($sale->refunded_total) }}</span></li>
            <li class="list-group-item d-flex justify-content-between fw-bold {{ (float) $sale->amount_due ? 'text-danger' : '' }}"><span>Reste dû</span><span>{{ money($sale->amount_due) }}</span></li>
            <li class="list-group-item small text-muted-2">Reçu imprimé {{ $sale->print_count }} fois · identifiant {{ $sale->client_uuid }}</li>
        </ul></div>
    </div>
</div>

@if(can_do('sales.cancel') && !$sale->isCancelled())
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="{{ route('sales.cancel', $sale) }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Annuler la vente {{ $sale->number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body">
            <p class="small">La vente n'est pas supprimée : les articles restants sont remis en stock, la créance est éteinte et le montant payé non encore remboursé (<strong>{{ money(\App\Support\Money::of($sale->amount_paid)->minus(\App\Support\Money::of($sale->refunded_total))) }}</strong>) est remboursé depuis votre caisse ouverte.</p>
            <div class="mb-3"><label class="form-label">Motif *</label><input name="reason" class="form-control" required maxlength="255"></div>
            <div><label class="form-label">Mode de remboursement</label><select name="refund_method_id" class="form-select">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Retour</button><button class="btn btn-danger">Confirmer l'annulation</button></div>
    </form>
</div></div>
@endif
@endsection
