<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1F3A">
    <title>Connexion · {{ setting('shop_name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="auth-wrap">
    <section class="auth-side">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ asset('images/logo.svg') }}" width="52" height="52" alt="">
            <div><div class="fs-3 fw-bold">Biashara</div><div style="color:#9DB4D6">Ventes · Stock · Caisse</div></div>
        </div>
        <div style="max-width:460px">
            <h1 class="display-6 fw-bold">Votre commerce, sous contrôle — même sans connexion.</h1>
            <ul class="list-unstyled mt-4" style="color:#C9D6EA">
                <li class="mb-2"><i class="bi bi-lightning-charge me-2 text-info"></i>Encaissement rapide au scanner ou au toucher</li>
                <li class="mb-2"><i class="bi bi-boxes me-2 text-info"></i>Stock suivi mouvement par mouvement</li>
                <li class="mb-2"><i class="bi bi-wifi-off me-2 text-info"></i>Ventes hors ligne, synchronisées au retour du réseau</li>
                <li><i class="bi bi-graph-up-arrow me-2 text-info"></i>Marges, caisses et créances consultables à distance</li>
            </ul>
        </div>
        <div class="small" style="color:#6F87AB">{{ setting('shop_name') }}</div>
    </section>
    <section class="auth-form">
        <div class="auth-card">
            <div class="d-lg-none text-center mb-4"><img src="{{ asset('images/logo.svg') }}" width="56" alt=""><div class="fs-4 fw-bold text-navy mt-2">Biashara</div></div>
            <h2 class="h4 text-navy fw-bold">Connexion</h2>
            <p class="text-muted-2">Identifiez-vous pour accéder à votre espace.</p>
            @include('partials.flash')
            <form method="post" action="{{ route('login.attempt') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="username">Identifiant ou e-mail</label>
                    <input id="username" name="username" class="form-control form-control-lg @error('username') is-invalid @enderror" value="{{ old('username') }}" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input id="password" type="password" name="password" class="form-control form-control-lg" required autocomplete="current-password">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Rester connecté sur cet appareil</label>
                </div>
                <button class="btn btn-primary btn-lg w-100">Se connecter</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
