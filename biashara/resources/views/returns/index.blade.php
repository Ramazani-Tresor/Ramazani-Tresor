@extends('layouts.app')
@section('title', 'Retours')
@section('content')
<div class="page-head"><div><h1>Retours et annulations</h1><div class="sub">Pour enregistrer un retour, ouvrez la vente concernée depuis l'historique.</div></div>
    <a href="{{ route('sales.index') }}" class="btn btn-primary"><i class="bi bi-search me-1"></i>Trouver une vente</a></div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">@include('partials.period')<button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button></form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>N°</th><th>Date</th><th>Vente</th><th>Nature</th><th>Motif</th><th>Saisi par</th><th>Autorisé par</th><th class="num">Valeur</th><th class="num">Remboursé</th></tr></thead>
        <tbody>
        @forelse($returns as $r)
            <tr><td><a href="{{ route('returns.show', $r) }}" target="_blank">{{ $r->number }}</a></td><td>{{ $r->returned_at->format('d/m/Y H:i') }}</td><td><a href="{{ route('sales.show', $r->sale) }}">{{ $r->sale->number }}</a></td>
                <td>{!! $r->is_cancellation ? '<span class="status status-muted">Annulation</span>' : '<span class="status status-info">Retour</span>' !!}</td><td>{{ $r->reason }}</td><td>{{ $r->user->name }}</td><td>{{ $r->authorizer->name }}</td><td class="num">{{ money($r->total_value) }}</td><td class="num">{{ money($r->refund_amount) }}</td></tr>
        @empty
            <tr><td colspan="9" class="empty"><i class="bi bi-arrow-counterclockwise"></i>Aucun retour sur la période.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $returns->links() }}</div>
</div>
@endsection
