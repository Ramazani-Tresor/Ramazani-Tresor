<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0B1F3A">
    <title>@yield('title') · UNILU-GRH</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/grh.css') }}?v=1">
</head>
<body>
@if(config('grh.demo_mode'))<div class="demo-banner">DONNÉES DE DÉMONSTRATION FICTIVES</div>@endif
<div class="auth">
    <section class="auth-side">
        <div class="d-flex align-items-center gap-3"><img src="{{ asset('images/logo.svg') }}" width="50" height="50" alt=""><div><div class="fs-3 fw-bold">{{ setting('university_acronym') ?: 'UNILU' }}-GRH</div><div style="color:#9DB4D6">Gestion intégrée du personnel et du patrimoine</div></div></div>
        <div style="max-width:470px">
            <h1 class="display-6 fw-bold text-white">{{ setting('university_name') }}</h1>
            <ul class="list-unstyled mt-4 lh-lg" style="color:#C9D6EA">
                <li><i class="bi bi-person-vcard me-2" style="color:#9CC3F5"></i>Dossier électronique unique pour chaque agent</li>
                <li><i class="bi bi-diagram-3 me-2" style="color:#9CC3F5"></i>Carrières, affectations et mouvements fondés sur des décisions</li>
                <li><i class="bi bi-cash-coin me-2" style="color:#9CC3F5"></i>Primes, acomptes et soutiens sociaux traçables</li>
                <li><i class="bi bi-building me-2" style="color:#9CC3F5"></i>Patrimoine immobilier et mobilier inventorié</li>
            </ul>
        </div>
        <div class="small" style="color:#6F87AB">Accès réservé aux personnes habilitées · toutes les opérations sont journalisées</div>
    </section>
    <main class="auth-form"><div class="auth-card">
        <div class="d-lg-none text-center mb-4"><img src="{{ asset('images/logo.svg') }}" width="54" alt=""><div class="fs-4 fw-bold text-navy mt-2">UNILU-GRH</div></div>
        @include('partials.flash')
        @yield('content')
    </div></main>
</div>
</body>
</html>
