@extends('layouts.app')
@section('title', $supplier->name)
@section('content')
<div class="page-head"><div><h1>{{ $supplier->name }}</h1><div class="sub">{{ $supplier->contact_person }} · {{ $supplier->phone }} · {{ $supplier->email }}</div></div>
    <div class="d-flex gap-2"><a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-light">Modifier</a>@if(can_do('purchases.create'))<a href="{{ route('purchases.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-primary"><i class="bi bi-truck me-1"></i>Nouveau bon d'entrée</a>@endif</div></div>
<div class="card"><div class="card-header">Approvisionnements</div>
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>N°</th><th>Date</th><th>Réf. fournisseur</th><th>Statut</th><th class="num">Total</th></tr></thead><tbody>
    @forelse($purchases as $p)<tr><td><a href="{{ route('purchases.show', $p) }}">{{ $p->number }}</a></td><td>{{ $p->purchase_date->format('d/m/Y') }}</td><td>{{ $p->supplier_reference }}</td><td>@include('purchases._status', ['purchase' => $p])</td><td class="num">{{ money($p->total) }}</td></tr>
    @empty<tr><td colspan="5" class="empty">Aucun bon.</td></tr>@endforelse
    </tbody></table></div><div class="card-body">{{ $purchases->links() }}</div></div>
@endsection
