<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0E1E3A">
    <title>@yield('title') · Karo</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
@if(setting('demo_mode') === '1' || config('karo.demo_mode'))<div class="demo-banner">DONNÉES DE DÉMONSTRATION FICTIVES</div>@endif
<div class="auth">
    <section class="auth-side">
        <div class="d-flex align-items-center gap-3"><img src="{{ asset('images/logo.svg') }}" width="50" height="50" alt=""><div><div class="fs-3 fw-bold">Karo</div><div style="color:#9FB0CD">Frais académiques et connexes</div></div></div>
        <div style="max-width:460px">
            <h1 class="display-6 fw-bold text-white">{{ setting('university_name') }}</h1>
            <ul class="list-unstyled mt-4 lh-lg" style="color:#C9D5EA">
                <li><i class="bi bi-receipt me-2" style="color:#5EE0D0"></i>Bordereaux saisis, contrôlés puis validés par des personnes distinctes</li>
                <li><i class="bi bi-bank me-2" style="color:#5EE0D0"></i>Rapprochement avec les relevés bancaires</li>
                <li><i class="bi bi-shield-check me-2" style="color:#5EE0D0"></i>Opérations tracées dans un journal infalsifiable</li>
                <li><i class="bi bi-hdd-network me-2" style="color:#5EE0D0"></i>Fonctionne sur le réseau local, sans Internet</li>
            </ul>
        </div>
        <div class="small" style="color:#7D90B2">Serveur de référence de l’université</div>
    </section>
    <main class="auth-form"><div class="auth-card">
        <div class="d-lg-none text-center mb-4"><img src="{{ asset('images/logo.svg') }}" width="54" alt=""><div class="fs-4 fw-bold text-navy mt-2">Karo</div></div>
        @include('partials.flash')
        @yield('content')
    </div></main>
</div>
</body>
</html>
