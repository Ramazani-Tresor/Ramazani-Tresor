@extends('layouts.app')
@section('title', 'Mot de passe')
@section('content')
@include('partials.head', ['title' => 'Changer mon mot de passe'])
<div class="card" style="max-width:520px"><div class="card-body">
<form method="post" action="{{ route('account.password.update') }}">@csrf @method('PUT')
    <div class="mb-3"><label class="form-label" for="current">Mot de passe actuel</label><input class="form-control" type="password" id="current" name="current_password" required autocomplete="current-password"></div>
    <div class="mb-3"><label class="form-label" for="pwd">Nouveau mot de passe</label><input class="form-control" type="password" id="pwd" name="password" required minlength="10" autocomplete="new-password"><div class="form-text">10 caractères minimum, avec majuscules, minuscules, chiffres et symboles.</div></div>
    <div class="mb-3"><label class="form-label" for="pwd2">Confirmation</label><input class="form-control" type="password" id="pwd2" name="password_confirmation" required autocomplete="new-password"></div>
    <button class="btn btn-primary">Enregistrer</button>
</form>
</div></div>
@endsection
