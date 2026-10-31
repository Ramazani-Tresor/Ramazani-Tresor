@extends('layouts.app')
@section('title', 'Utilisateurs')
@section('content')
@include('partials.head', ['title' => 'Utilisateurs et permissions', 'actions' => (can_do('roles.manage') ? '<a class="btn btn-light" href="'.route('roles.index').'"><i class="bi bi-grid-3x3"></i> Rôles × permissions</a>' : '').'<a class="btn btn-primary" href="'.route('users.create').'"><i class="bi bi-person-plus"></i> Nouveau compte</a>'])
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get"><div><label class="form-label" for="uq">Recherche</label><input id="uq" type="search" name="q" class="form-control" value="{{ request('q') }}"></div><div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="archived" value="1" id="ua" @checked(request('archived'))><label class="form-check-label" for="ua">Inclure les archivés</label></div><div class="acts"><button class="btn btn-primary">Filtrer</button></div></form></div>
    <div class="table-responsive"><table class="table table-hover table-stack mb-0"><thead><tr><th>Nom</th><th>Identifiant</th><th>Rôles et périmètres</th><th>2FA</th><th>Dernière connexion</th><th>Statut</th></tr></thead><tbody>
    @foreach($users as $u)
        <tr><td data-l="Nom"><a href="{{ route('users.edit', $u) }}" class="fw-600">{{ $u->name }}</a></td><td data-l="Identifiant">{{ $u->username }}</td>
            <td data-l="Rôles" class="small">@forelse($u->assignments as $a){{ $a->role->name }}{{ $a->faculty ? ' · '.$a->faculty->code : '' }}{{ $a->academicYear ? ' · '.$a->academicYear->code : '' }}<br>@empty <span class="text-muted-2">aucun</span>@endforelse</td>
            <td data-l="2FA">{!! $u->hasTwoFactor() ? '<span class="badge-st st-ok">Active</span>' : '<span class="badge-st st-muted">Non</span>' !!}</td><td data-l="Connexion" class="small">{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
            <td data-l="Statut">{!! $u->archived_at ? '<span class="badge-st st-muted">Archivé</span>' : ($u->is_active ? '<span class="badge-st st-ok">Actif</span>' : '<span class="badge-st st-warn">Désactivé</span>') !!}</td></tr>
    @endforeach
    </tbody></table></div><div class="card-body">{{ $users->links() }}</div></div>
@endsection
