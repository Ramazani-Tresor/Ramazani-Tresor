@extends('layouts.guest')
@section('title', 'Connexion')
@section('content')
<h2 class="h4 fw-bold text-navy">Connexion</h2>
<p class="text-muted-2">Identifiez-vous avec le compte qui vous a été attribué.</p>
@if(request('expire'))<div class="alert alert-info small">Votre session a expiré après une période d’inactivité. Veuillez vous reconnecter.</div>@endif
<form method="post" action="{{ route('login.attempt') }}">@csrf
    <div class="mb-3"><label class="form-label" for="username">Identifiant ou adresse électronique</label><input class="form-control" id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"></div>
    <div class="mb-3"><label class="form-label" for="password">Mot de passe</label><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary btn-lg w-100">Se connecter</button>
</form>
<p class="small text-muted-2 mt-3"><i class="bi bi-lock"></i> 5 tentatives par minute au maximum. Mot de passe oublié : adressez-vous à l’administrateur RH ou technique.</p>
@endsection
