@extends('layouts.app')
@section('title', 'Entrées de stock')
@section('content')
<div class="page-head"><div><h1>Entrées de stock</h1><div class="sub">Bons d'approvisionnement fournisseurs</div></div>
    @if(can_do('purchases.create'))<a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouveau bon d'entrée</a>@endif</div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        <div><input type="search" name="q" class="form-control" placeholder="N° de bon ou réf. fournisseur" value="{{ request('q') }}"></div>
        <div><select name="supplier_id" class="form-select"><option value="">Tous fournisseurs</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
        <div><select name="status" class="form-select"><option value="">Tous statuts</option><option value="draft" @selected(request('status') === 'draft')>Brouillons</option><option value="validated" @selected(request('status') === 'validated')>Validés</option><option value="cancelled" @selected(request('status') === 'cancelled')>Annulés</option></select></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>N°</th><th>Date</th><th>Fournisseur</th><th>Réf. fournisseur</th><th>Saisi par</th><th>Statut</th><th class="num">Total</th></tr></thead>
        <tbody>
        @forelse($purchases as $p)
            <tr><td><a href="{{ route('purchases.show', $p) }}" class="fw-600">{{ $p->number }}</a></td><td>{{ $p->purchase_date->format('d/m/Y') }}</td><td>{{ $p->supplier->name }}</td><td>{{ $p->supplier_reference }}</td><td>{{ $p->user->name }}</td><td>@include('purchases._status', ['purchase' => $p])</td><td class="num">{{ money($p->total) }}</td></tr>
        @empty
            <tr><td colspan="7" class="empty"><i class="bi bi-truck"></i>Aucun bon d'entrée.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $purchases->links() }}</div>
</div>
@endsection
