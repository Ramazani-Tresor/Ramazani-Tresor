@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<div class="page-head">
    <div><h1>{{ $customer->name }}</h1><div class="sub">{{ $customer->code }} · {{ $customer->phone }} · {{ $customer->address }}{{ $customer->credit_limit !== null ? ' · plafond '.money($customer->credit_limit) : '' }}</div></div>
    <div class="d-flex gap-2"><a href="{{ route('customers.statement', $customer) }}" class="btn btn-light"><i class="bi bi-file-text me-1"></i>Relevé de compte</a>
        @if(can_do('customers.manage'))<a href="{{ route('customers.edit', $customer) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Modifier</a>@endif</div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card kpi mb-3"><div class="label">Solde restant dû</div><div class="value {{ (float) $balance > 0 ? 'text-danger' : 'text-success' }}">{{ money($balance) }}</div><div class="hint">{{ $openSales->count() }} vente(s) non soldée(s)</div></div>
        @if(can_do('customers.settle') && (float) $balance > 0)
        <div class="card" id="reglement"><div class="card-header">Encaisser un règlement</div>
            <form class="card-body" method="post" action="{{ route('customers.settle', $customer) }}" data-confirm="Enregistrer ce règlement ? Il sera imputé sur les ventes les plus anciennes (ou la vente choisie).">
                @csrf
                <input type="hidden" name="client_uuid" data-uuid value="{{ old('client_uuid') }}">
                <div class="mb-2"><label class="form-label">Montant *</label><input name="amount" class="form-control text-end fw-bold" inputmode="decimal" value="{{ old('amount', (string) $balance) }}" required></div>
                <div class="mb-2"><label class="form-label">Mode *</label><select name="payment_method_id" class="form-select">@foreach($paymentMethods as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                <div class="mb-2"><label class="form-label">Imputer sur</label><select name="sale_id" class="form-select"><option value="">Les plus anciennes d'abord</option>@foreach($openSales as $s)<option value="{{ $s->id }}">{{ $s->number }} — dû {{ money($s->amount_due) }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Référence</label><input name="reference" class="form-control" maxlength="100"></div>
                <button class="btn btn-primary w-100"><i class="bi bi-cash me-1"></i>Enregistrer le règlement</button>
                <div class="form-text">Un règlement n'est pas une vente : il n'affecte ni le stock ni le chiffre d'affaires.</div>
            </form>
        </div>
        @endif
    </div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header">Historique des achats</div>
            <div class="table-responsive"><table class="table table-hover"><thead><tr><th>N°</th><th>Date</th><th class="num">Total</th><th class="num">Payé</th><th class="num">Reste dû</th><th>Statut</th></tr></thead><tbody>
            @forelse($sales as $s)<tr><td><a href="{{ route('sales.show', $s) }}">{{ $s->number }}</a></td><td>{{ $s->sold_at->format('d/m/Y') }}</td><td class="num">{{ money($s->total) }}</td><td class="num">{{ money($s->amount_paid) }}</td><td class="num">{{ money($s->amount_due) }}</td><td>@include('sales._status', ['sale' => $s])</td></tr>
            @empty<tr><td colspan="6" class="empty">Aucun achat.</td></tr>@endforelse
            </tbody></table></div><div class="card-body">{{ $sales->links() }}</div></div>
        <div class="card"><div class="card-header">Règlements reçus</div>
            <div class="table-responsive"><table class="table"><thead><tr><th>N°</th><th>Date</th><th>Mode</th><th>Reçu par</th><th class="num">Montant</th></tr></thead><tbody>
            @forelse($settlements as $st)<tr><td><a href="{{ route('settlements.show', $st) }}" target="_blank">{{ $st->number }}</a></td><td>{{ $st->paid_at->format('d/m/Y H:i') }}</td><td>{{ $st->method->name }}</td><td>{{ $st->user->name }}</td><td class="num">{{ money($st->amount) }}</td></tr>
            @empty<tr><td colspan="5" class="empty">Aucun règlement.</td></tr>@endforelse
            </tbody></table></div></div>
    </div>
</div>
@endsection
