@extends('layouts.app')
@section('title', 'Fournisseurs')
@section('content')
<div class="page-head"><div><h1>Fournisseurs</h1></div><a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouveau fournisseur</a></div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get"><div><input type="search" name="q" class="form-control" placeholder="Nom ou téléphone…" value="{{ request('q') }}"></div><button class="btn btn-light"><i class="bi bi-search"></i></button></form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Nom</th><th>Contact</th><th>Téléphone</th><th class="num">Bons</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($suppliers as $s)
            <tr><td><a href="{{ route('suppliers.show', $s) }}" class="fw-600">{{ $s->name }}</a></td><td>{{ $s->contact_person }}</td><td>{{ $s->phone }}</td><td class="num">{{ $s->purchases_count }}</td>
                <td>{!! $s->is_active ? '<span class="status status-ok">Actif</span>' : '<span class="status status-muted">Inactif</span>' !!}</td>
                <td class="text-end"><a href="{{ route('suppliers.edit', $s) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a></td></tr>
        @empty
            <tr><td colspan="6" class="empty"><i class="bi bi-building"></i>Aucun fournisseur.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $suppliers->links() }}</div>
</div>
@endsection
