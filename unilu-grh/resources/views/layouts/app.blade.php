<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0B1F3A">
    <title>@yield('title', 'Accueil') · {{ setting('university_acronym') ?: 'UNILU' }}-GRH</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/grh.css') }}?v=1">
    @livewireStyles(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
    @stack('head')
</head>
<body data-base="{{ url('') }}" data-idle="{{ (int) config('session.lifetime') }}">
<a href="#contenu" class="visually-hidden-focusable btn btn-light m-2">Aller au contenu</a>
@if(config('grh.demo_mode'))
    <div class="demo-banner" role="status"><i class="bi bi-exclamation-triangle"></i> DONNÉES DE DÉMONSTRATION FICTIVES — base séparée, ne pas utiliser en production</div>
@endif
<div class="shell">
    <aside class="side d-none d-lg-block" aria-label="Navigation principale">@include('partials.nav')</aside>
    <div class="offcanvas offcanvas-start side-m" tabindex="-1" id="menuMobile" aria-label="Menu">
        <div class="offcanvas-body p-0"><div class="side">@include('partials.nav')</div></div>
    </div>
    <div class="main">
        <header class="top no-print">
            <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuMobile" aria-label="Ouvrir le menu"><i class="bi bi-list fs-5"></i></button>
            @if(can_do('employees.view') || can_do('employees.directory'))
                <form class="d-none d-md-block flex-grow-1" style="max-width:440px" action="{{ route('employees.index') }}" method="get" role="search">
                    <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" placeholder="Matricule, nom ou postnom d’un agent…" aria-label="Rechercher un agent" value="{{ request()->routeIs('employees.index') ? request('q') : '' }}"></div>
                </form>
            @endif
            <div class="ms-auto d-flex align-items-center gap-2">
                <livewire:notification-bell />
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="rounded-circle d-inline-grid fw-bold avatar-sm">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="d-none d-sm-block text-start lh-sm"><span class="d-block small fw-600">{{ auth()->user()->name }}</span><span class="d-block text-muted-2" style="font-size:.7rem">{{ \Illuminate\Support\Str::limit(auth()->user()->roleNames(), 34) }}</span></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        @if(auth()->user()->employee_id && can_do('self.view'))<li><a class="dropdown-item" href="{{ route('me.show') }}"><i class="bi bi-person-badge me-2"></i>Mon espace personnel</a></li>@endif
                        <li><a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="bi bi-bell me-2"></i>Mes notifications</a></li>
                        <li><a class="dropdown-item" href="{{ route('account.password') }}"><i class="bi bi-key me-2"></i>Changer mon mot de passe</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</button></form></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="content" id="contenu" tabindex="-1">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<nav class="mobile-nav no-print" aria-label="Navigation rapide">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home', 'dashboard') ? 'active' : '' }}"><i class="bi bi-grid-1x2"></i>Accueil</a>
    @if(can_do('employees.view') || can_do('employees.directory'))<a href="{{ route('employees.index') }}" class="{{ request()->routeIs('employees.*') ? 'active' : '' }}"><i class="bi bi-people"></i>Agents</a>@endif
    @if(can_do('primes.view'))<a href="{{ route('primes.index') }}" class="{{ request()->routeIs('primes.*') ? 'active' : '' }}"><i class="bi bi-cash-coin"></i>Primes</a>@endif
    @if(can_do('assets.view'))<a href="{{ route('assets.index') }}" class="{{ request()->routeIs('assets.*') ? 'active' : '' }}"><i class="bi bi-building"></i>Patrimoine</a>@endif
    @if(auth()->user()->employee_id && can_do('self.view'))<a href="{{ route('me.show') }}" class="{{ request()->routeIs('me.*') ? 'active' : '' }}"><i class="bi bi-person-badge"></i>Mon espace</a>@endif
    <a href="#" data-bs-toggle="offcanvas" data-bs-target="#menuMobile" aria-label="Plus de menus"><i class="bi bi-three-dots"></i>Plus</a>
</nav>
<div class="loading-overlay" aria-hidden="true"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement…</span></div></div>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="confirmTitle">Confirmation</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body" id="confirmText"></div>
        <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="confirmOk">Confirmer</button></div>
    </div></div>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts-before')
<script src="{{ asset('js/app.js') }}?v=1"></script>
@livewireScripts(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
@stack('scripts')
</body>
</html>
