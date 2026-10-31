@extends('layouts.app')
@section('title', 'Catégories')
@section('content')
<div class="page-head">
    <div><h1>Catégories</h1><div class="sub">Familles de produits et de services</div></div>
    <a href="{{ route('categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvelle catégorie</a>
</div>
<div class="card">
    <div class="card-body border-bottom">
        <form class="filters" method="get">
            <div><input type="search" name="q" class="form-control" placeholder="Rechercher…" value="{{ request('q') }}"></div>
            <div><select name="status" class="form-select"><option value="">Toutes</option><option value="active" @selected(request('status') === 'active')>Actives</option><option value="inactive" @selected(request('status') === 'inactive')>Désactivées</option></select></div>
            <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Nom</th><th>Description</th><th class="num">Articles</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($categories as $c)
            <tr>
                <td><span class="d-inline-block rounded-circle me-2" style="width:10px;height:10px;background:{{ $c->color ?? '#1E6FD9' }}"></span><strong>{{ $c->name }}</strong></td>
                <td class="text-muted-2">{{ $c->description }}</td>
                <td class="num"><a href="{{ route('products.index', ['category_id' => $c->id]) }}">{{ $c->products_count }}</a></td>
                <td>{!! $c->is_active ? '<span class="status status-ok">Active</span>' : '<span class="status status-muted">Désactivée</span>' !!}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('categories.edit', $c) }}" class="btn btn-sm btn-light" title="Modifier"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="{{ route('categories.toggle', $c) }}" class="d-inline" data-confirm="{{ $c->is_active ? 'Désactiver cette catégorie ? Ses articles restent consultables.' : 'Réactiver cette catégorie ?' }}">@csrf @method('PATCH')
                        <button class="btn btn-sm btn-light" title="{{ $c->is_active ? 'Désactiver' : 'Réactiver' }}"><i class="bi {{ $c->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i></button>
                    </form>
                    @if($c->products_count === 0)
                        <form method="post" action="{{ route('categories.destroy', $c) }}" class="d-inline" data-confirm="Supprimer définitivement cette catégorie vide ?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-light text-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty"><i class="bi bi-tags"></i>Aucune catégorie.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $categories->links() }}</div>
</div>
@endsection
