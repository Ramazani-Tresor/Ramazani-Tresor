@extends('layouts.guest')
@section('title', 'Connexion')
@section('content')
<h2 class="h4 fw-bold text-navy">Connexion</h2>
<p class="text-muted-2">Accès réservé aux personnes habilitées.</p>
<form method="post" action="{{ route('login.attempt') }}" novalidate>
    @csrf
    <div class="mb-3"><label class="form-label" for="username">Identifiant ou e-mail</label>
        <input id="username" name="username" class="form-control form-control-lg @error('username') is-invalid @enderror" value="{{ old('username') }}" required autofocus autocomplete="username"></div>
    <div class="mb-3"><label class="form-label" for="password">Mot de passe</label>
        <input id="password" type="password" name="password" class="form-control form-control-lg" required autocomplete="current-password"></div>
    <button class="btn btn-primary btn-lg w-100">Se connecter</button>
</form>
<p class="small mt-3 mb-0"><a href="{{ route('account.recovery') }}">Mot de passe oublié ou téléphone d’authentification perdu ?</a></p>
<p class="small text-muted-2 mt-2"><i class="bi bi-lock"></i> 5 tentatives par minute au maximum. Les connexions sont journalisées.</p>
@endsection
