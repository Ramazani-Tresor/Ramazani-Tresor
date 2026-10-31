@extends('layouts.guest')
@section('title', 'Vérification')
@section('content')
<h2 class="h4 fw-bold text-navy">Vérification en deux étapes</h2>
<p class="text-muted-2">Saisissez le code de votre application d’authentification, ou un code de récupération.</p>
<form method="post" action="{{ route('two-factor.verify') }}">@csrf
    <input name="code" class="form-control form-control-lg text-center" autocomplete="one-time-code" maxlength="14" required autofocus aria-label="Code">
    <button class="btn btn-primary w-100 mt-3">Vérifier</button>
</form>
<form method="post" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-link w-100">Se déconnecter</button></form>
@endsection
