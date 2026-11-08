@extends('layouts.app')
@section('title', 'Nouveau compte')
@section('content')
@include('partials.head', ['title' => 'Nouveau compte', 'crumbs' => ['Utilisateurs' => route('users.index')]])
<form method="post" action="{{ route('users.store') }}" class="card" style="max-width:760px">@csrf
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="n">Nom complet *</label><input id="n" name="name" class="form-control" required maxlength="150" value="{{ old('name') }}"></div>
        <div class="col-md-6"><label class="form-label" for="u">Identifiant *</label><input id="u" name="username" class="form-control" required maxlength="60" value="{{ old('username') }}"></div>
        <div class="col-md-6"><label class="form-label" for="e">E-mail</label><input id="e" type="email" name="email" class="form-control" maxlength="150" value="{{ old('email') }}"></div>
        <div class="col-md-6"><label class="form-label" for="p">Téléphone</label><input id="p" name="phone" class="form-control" maxlength="40" value="{{ old('phone') }}"></div>
        <div class="col-md-6"><label class="form-label" for="sm">Matricule (compte étudiant uniquement)</label><input id="sm" name="student_matricule" class="form-control" value="{{ old('student_matricule') }}"></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" checked><label class="form-check-label" for="ia">Actif</label></div></div>
        <p class="small text-muted-2 mb-0">Un mot de passe temporaire sera généré (changement obligatoire à la première connexion).</p>
    </div>
    <div class="card-footer bg-white"><button class="btn btn-primary">Créer</button></div>
</form>
@endsection
