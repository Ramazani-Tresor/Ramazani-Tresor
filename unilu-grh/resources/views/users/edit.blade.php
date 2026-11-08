@extends('layouts.app')
@section('title', $user->name)
@section('content')
@include('partials.head', ['title' => $user->name, 'crumbs' => ['Utilisateurs' => route('users.index')], 'sub' => 'Identifiant '.e($user->username).' · dernière connexion : '.($user->last_login_at?->format('d/m/Y H:i') ?? 'jamais').($user->last_login_ip ? ' depuis '.e($user->last_login_ip) : '')])
<div class="row g-3">
<div class="col-lg-5"><div class="card mb-3"><div class="card-header">Compte</div><div class="card-body"><form method="post" action="{{ route('users.update', $user) }}" class="row g-2">@csrf @method('PUT')
    @include('partials.field', ['name' => 'name', 'label' => 'Nom complet', 'value' => $user->name, 'required' => true, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'email', 'label' => 'Courriel (notifications)', 'type' => 'email', 'value' => $user->email, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'matricule', 'label' => 'Matricule de l’agent lié', 'value' => $user->employee?->matricule, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'is_active', 'label' => 'Compte actif', 'type' => 'bool', 'value' => $user->is_active, 'col' => 'col-12'])
    <div class="col-12"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form></div></div>
    <div class="card"><div class="card-body"><form method="post" action="{{ route('users.password', $user) }}" data-confirm="Générer un nouveau mot de passe temporaire ?">@csrf<button class="btn btn-outline-secondary btn-sm"><i class="bi bi-key"></i> Réinitialiser le mot de passe</button></form></div></div></div>
<div class="col-lg-7"><div class="card"><div class="card-header">Rôles et périmètres</div>
    <ul class="list-group list-group-flush">@forelse($user->roleAssignments as $a)<li class="list-group-item d-flex justify-content-between align-items-center"><span><b>{{ $a->role?->name }}</b><br><span class="small text-muted-2">{{ $a->orgUnit ? 'Unité : '.$a->orgUnit->name.' (et sous-unités)' : 'Toute l’université' }} · {{ $a->staff_family ? \App\Support\Labels::status('family', $a->staff_family) : 'toutes familles' }}</span></span>
        <form method="post" action="{{ route('users.unassign', [$user, $a]) }}" data-confirm="Retirer ce rôle ?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Retirer</button></form></li>@empty<li class="list-group-item small text-muted-2">Aucun rôle.</li>@endforelse</ul>
    <div class="card-body border-top"><form method="post" action="{{ route('users.assign', $user) }}" class="row g-2 align-items-end">@csrf
        @include('partials.field', ['name' => 'role_id', 'label' => 'Rôle', 'type' => 'select', 'required' => true, 'options' => $roles->pluck('name', 'id')->all(), 'col' => 'col-md-4'])
        @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Limiter à l’unité', 'type' => 'select', 'options' => $units->pluck('name', 'id')->all(), 'placeholder' => 'Toute l’université', 'col' => 'col-md-4'])
        @include('partials.field', ['name' => 'staff_family', 'label' => 'Limiter à la famille', 'type' => 'select', 'options' => \App\Support\PermissionRegistry::FAMILIES, 'placeholder' => 'Toutes', 'col' => 'col-md-4'])
        <div class="col-12"><button class="btn btn-primary btn-sm">Attribuer</button></div></form></div>
</div></div>
</div>
@endsection
