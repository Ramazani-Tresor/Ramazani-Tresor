@extends('layouts.app')
@section('title', 'Utilisateurs')
@section('content')
<div class="page-head"><div><h1>Utilisateurs</h1></div><a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nouvel utilisateur</a></div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        <div><input type="search" name="q" class="form-control" placeholder="Nom ou identifiant" value="{{ request('q') }}"></div>
        <div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="archived" value="1" id="arc" @checked(request('archived'))><label class="form-check-label" for="arc">Inclure les archivés</label></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i></button>
    </form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Nom</th><th>Identifiant</th><th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr><td class="fw-600">{{ $u->name }}</td><td>{{ $u->username }}</td><td>{{ $u->role->name }}</td><td>{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>@if($u->archived_at)<span class="status status-muted">Archivé</span>@elseif($u->is_active)<span class="status status-ok">Actif</span>@else<span class="status status-warn">Désactivé</span>@endif</td>
                <td class="text-end text-nowrap"><a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                    @if($u->id !== auth()->id())
                        <form method="post" action="{{ route('users.archive', $u) }}" class="d-inline" data-confirm="{{ $u->archived_at ? 'Restaurer cet utilisateur ?' : 'Archiver cet utilisateur ? Il ne pourra plus se connecter ; son historique est conservé.' }}">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-light" title="{{ $u->archived_at ? 'Restaurer' : 'Archiver' }}"><i class="bi {{ $u->archived_at ? 'bi-arrow-counterclockwise' : 'bi-archive' }}"></i></button></form>
                    @endif
                </td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="card-body">{{ $users->links() }}</div>
</div>
@endsection
