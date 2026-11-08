@extends('layouts.app')
@section('title', 'Inventaires')
@section('content')
<div class="page-head"><div><h1>Inventaires physiques</h1></div>@if(can_do('inventories.validate'))<a href="{{ route('inventories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Ouvrir un inventaire</a>@endif</div>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>N°</th><th>Périmètre</th><th>Ouvert le</th><th>Par</th><th>Validé le</th><th class="num">Articles</th><th>Statut</th></tr></thead>
    <tbody>
    @forelse($inventories as $i)
        <tr><td><a href="{{ route('inventories.show', $i) }}" class="fw-600">{{ $i->number }}</a></td><td>{{ $i->category?->name ?? 'Tous les articles' }}</td><td>{{ $i->opened_at->format('d/m/Y H:i') }}</td><td>{{ $i->opener->name }}</td><td>{{ $i->validated_at?->format('d/m/Y H:i') ?? '—' }}</td><td class="num">{{ $i->items_count }}</td>
            <td>@switch($i->status)@case('open')<span class="status status-warn">En cours</span>@break @case('validated')<span class="status status-ok">Validé</span>@break @default<span class="status status-muted">Annulé</span>@endswitch</td></tr>
    @empty
        <tr><td colspan="7" class="empty"><i class="bi bi-clipboard-check"></i>Aucun inventaire.</td></tr>
    @endforelse
    </tbody>
</table></div><div class="card-body">{{ $inventories->links() }}</div></div>
@endsection
