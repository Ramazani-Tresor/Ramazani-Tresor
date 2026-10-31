@extends('layouts.guest')
@section('title', 'Double authentification')
@section('content')
<h2 class="h4 fw-bold text-navy">Configurer la double authentification</h2>
<ol class="small">
    <li>Ouvrez une application d’authentification sur votre téléphone (Google Authenticator, Microsoft Authenticator, FreeOTP, Aegis…).</li>
    <li>Scannez ce QR code, ou saisissez la clé manuellement.</li>
    <li>Saisissez le code à 6 chiffres affiché.</li>
</ol>
<div class="text-center my-3"><img src="{{ $qr }}" alt="QR code de configuration" width="200" height="200"></div>
<p class="small text-center">Clé : <code class="user-select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</code></p>
<form method="post" action="{{ route('two-factor.confirm') }}">@csrf
    <label class="form-label" for="code">Code à 6 chiffres</label>
    <input id="code" name="code" class="form-control form-control-lg text-center" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
    <button class="btn btn-primary w-100 mt-3">Activer</button>
</form>
<form method="post" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-link w-100">Se déconnecter</button></form>
@endsection
