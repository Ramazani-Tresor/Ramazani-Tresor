@extends('layouts.app')
@section('title', $user->exists ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur')
@section('content')
<div class="page-head"><h1>{{ $user->exists ? 'Modifier '.$user->name : 'Nouvel utilisateur' }}</h1></div>
<div class="card" style="max-width:760px">
    <form class="card-body row g-3" method="post" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" autocomplete="off">
        @csrf @if($user->exists) @method('PUT') @endif
        <div class="col-md-6"><label class="form-label">Nom complet *</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required maxlength="120"></div>
        <div class="col-md-6"><label class="form-label">Identifiant de connexion *</label><input name="username" class="form-control" value="{{ old('username', $user->username) }}" required maxlength="60" pattern="[A-Za-z0-9_\-]+"></div>
        <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" maxlength="150"></div>
        <div class="col-md-6"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" maxlength="40"></div>
        <div class="col-md-6"><label class="form-label">Rôle *</label><select name="role_id" class="form-select" required>@foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="act">Compte actif</label></div></div>
        <div class="col-md-6"><label class="form-label">Mot de passe {{ $user->exists ? '(laisser vide pour conserver)' : '*' }}</label><input type="password" name="password" class="form-control" autocomplete="new-password" @required(!$user->exists)><div class="form-text">8 caractères minimum, lettres et chiffres.</div></div>
        <div class="col-md-6"><label class="form-label">Confirmation</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a href="{{ route('users.index') }}" class="btn btn-light">Annuler</a></div>
    </form>
</div>
@endsection
