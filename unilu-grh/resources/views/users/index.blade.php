@extends('layouts.app')
@section('title', 'Utilisateurs')
@section('content')
@include('partials.head', ['title' => 'Utilisateurs et habilitations', 'sub' => 'Chaque rôle peut être limité à une unité (et ses sous-unités) et/ou à une famille de personnel.', 'actions' => '<a class="btn btn-light" href="'.route('roles.index').'"><i class="bi bi-shield-lock"></i> Rôles et permissions</a>'])
<div class="row g-3">
<div class="col-xl-8"><div class="card"><div class="card-header"><span>Comptes ({{ $users->total() }})</span><form class="d-flex gap-1"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher" aria-label="Rechercher un compte"><button class="btn btn-sm btn-light">OK</button></form></div>
<div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Nom</th><th>Identifiant</th><th>Rôles (périmètre)</th><th>Agent lié</th><th>Dernière connexion</th><th>État</th></tr></thead><tbody>
@foreach($users as $usr)<tr><td data-l="Nom"><a href="{{ route('users.edit', $usr) }}" class="fw-600">{{ $usr->name }}</a></td><td data-l="Identifiant">{{ $usr->username }}</td>
    <td data-l="Rôles" class="small">@foreach($usr->roleAssignments as $a)<div>{{ $a->role?->name }} @if($a->orgUnit || $a->staff_family)<span class="text-muted-2">({{ collect([$a->orgUnit?->name, $a->staff_family ? \App\Support\Labels::status('family', $a->staff_family) : null])->filter()->implode(', ') }})</span>@endif</div>@endforeach</td>
    <td data-l="Agent">{{ $usr->employee?->matricule ?? '—' }}</td><td data-l="Connexion">{{ $usr->last_login_at?->format('d/m/Y H:i') ?? 'jamais' }}</td><td data-l="État">{!! $usr->is_active ? '<span class="badge-st st-ok">Actif</span>' : '<span class="badge-st st-muted">Désactivé</span>' !!}</td></tr>@endforeach
</tbody></table></div><div class="card-body border-top">{{ $users->links() }}</div></div></div>
<div class="col-xl-4"><div class="card"><div class="card-header">Nouveau compte</div><div class="card-body"><form method="post" action="{{ route('users.store') }}" class="row g-2">@csrf
    @include('partials.field', ['name' => 'name', 'label' => 'Nom complet', 'required' => true, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'username', 'label' => 'Identifiant', 'required' => true, 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'email', 'label' => 'Courriel', 'type' => 'email', 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'matricule', 'label' => 'Matricule de l’agent (espace personnel)', 'col' => 'col-12'])
    @include('partials.field', ['name' => 'role_id', 'label' => 'Rôle initial (sans restriction)', 'type' => 'select', 'options' => $roles->pluck('name', 'id')->all(), 'col' => 'col-12'])
    <div class="col-12 small text-muted-2">Un mot de passe temporaire est généré ; il devra être changé à la première connexion.</div>
    <div class="col-12"><button class="btn btn-primary">Créer</button></div>
</form></div></div></div>
</div>
@endsection
