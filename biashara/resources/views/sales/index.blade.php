@extends('layouts.app')
@section('title', 'Historique des ventes')
@section('content')
<div class="page-head">
    <div><h1>Historique des ventes</h1><div class="sub">{{ $totals['count'] }} vente(s) · {{ money($totals['total']) }} · reste dû {{ money($totals['due']) }}</div></div>
    @if(can_do('sales.create'))<a href="{{ route('pos') }}" class="btn btn-primary"><i class="bi bi-cart3 me-1"></i>Nouvelle vente</a>@endif
</div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        @include('partials.period')
        <div><label class="form-label small mb-1">Recherche</label><input type="search" name="q" class="form-control" placeholder="N° ou client" value="{{ request('q') }}"></div>
        @if($users->isNotEmpty())<div><label class="form-label small mb-1">Vendeur</label><select name="user_id" class="form-select"><option value="">Tous</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>@endif
        <div><label class="form-label small mb-1">Statut</label><select name="status" class="form-select"><option value="">Tous</option><option value="validated" @selected(request('status') === 'validated')>Validées</option><option value="cancelled" @selected(request('status') === 'cancelled')>Annulées</option></select></div>
        <div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="credit" value="1" id="cr" @checked(request('credit'))><label class="form-check-label" for="cr">Avec reste dû</label></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Vendeur</th><th class="num">Total</th><th class="num">Payé</th><th class="num">Reste dû</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($sales as $s)
            <tr>
                <td><a href="{{ route('sales.show', $s) }}" class="fw-600">{{ $s->number }}</a>@if($s->origin === 'offline') <i class="bi bi-wifi-off text-muted-2" title="Saisie hors ligne"></i>@endif</td>
                <td class="text-nowrap">{{ $s->sold_at->format('d/m/Y H:i') }}</td>
                <td>{{ $s->customer?->name ?? 'Passage' }}</td>
                <td>{{ $s->user->name }}</td>
                <td class="num">{{ money($s->total) }}</td>
                <td class="num">{{ money($s->amount_paid) }}</td>
                <td class="num {{ (float) $s->amount_due ? 'text-danger fw-600' : '' }}">{{ money($s->amount_due) }}</td>
                <td>@include('sales._status', ['sale' => $s])</td>
                <td class="text-end"><a href="{{ route('sales.receipt', $s) }}" target="_blank" class="btn btn-sm btn-light" title="Reçu"><i class="bi bi-printer"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty"><i class="bi bi-receipt"></i>Aucune vente sur la période.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $sales->links() }}</div>
</div>
@endsection
