<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0B1F3A">
    <title>@yield('title', 'Karo') · {{ setting('university_acronym') ?: setting('university_name') }}</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
    @stack('head')
</head>
<body data-base="{{ url('/') }}" data-sw="{{ route('pwa.sw') }}" data-user="{{ auth()->id() }}">
<a href="#contenu" class="visually-hidden-focusable btn btn-light m-2">Aller au contenu</a>
@if(setting('demo_mode') === '1' || config('karo.demo_mode'))
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
            @if(can_do('students.view'))
                <form class="d-none d-md-block flex-grow-1" style="max-width:440px" action="{{ route('students.index') }}" method="get" role="search">
                    <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" placeholder="Matricule ou nom d’un étudiant…" aria-label="Rechercher un étudiant" value="{{ request()->routeIs('students.index') ? request('q') : '' }}"></div>
                </form>
            @endif
            <div class="ms-auto d-flex align-items-center gap-2">
                <span id="netPill" class="net-pill net-on" title="Accessibilité du serveur de référence"><span class="dot"></span><span class="t">Serveur joignable</span></span>
                <a id="queuePill" href="{{ route('sync.index') }}" class="net-pill net-queue d-none" title="Saisies hors connexion en attente"><i class="bi bi-cloud-upload"></i><span class="t">0</span></a>
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="rounded-circle d-inline-grid fw-bold" style="width:30px;height:30px;place-items:center;background:var(--k-blue-soft);color:var(--k-navy)">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="d-none d-sm-block text-start lh-sm"><span class="d-block small fw-600">{{ auth()->user()->name }}</span><span class="d-block text-muted-2" style="font-size:.7rem">{{ \Illuminate\Support\Str::limit(auth()->user()->roleNames(), 32) }}</span></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="{{ route('account.show') }}"><i class="bi bi-person-lock me-2"></i>Mon compte et sécurité</a></li>
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
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}"><i class="bi bi-grid-1x2"></i>Accueil</a>
    @if(can_do('payments.create'))<a href="{{ route('payments.entry') }}" class="{{ request()->routeIs('payments.entry') ? 'active' : '' }}"><i class="bi bi-receipt"></i>Saisie</a>@endif
    @if(can_do('payments.check') || can_do('payments.validate'))<a href="{{ route('payments.control') }}" class="{{ request()->routeIs('payments.control') ? 'active' : '' }}"><i class="bi bi-check2-square"></i>Contrôle</a>@endif
    @if(can_do('students.view'))<a href="{{ route('students.index') }}" class="{{ request()->routeIs('students.*') ? 'active' : '' }}"><i class="bi bi-people"></i>Étudiants</a>@endif
    @if(can_do('dashboard.view'))<a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i>Bilan</a>@endif
    @if(can_do('student.self'))<a href="{{ route('portal.show') }}" class="{{ request()->routeIs('portal.*') ? 'active' : '' }}"><i class="bi bi-person-vcard"></i>Mon espace</a>@endif
    <a href="#" data-bs-toggle="offcanvas" data-bs-target="#menuMobile" aria-label="Plus de menus"><i class="bi bi-three-dots"></i>Plus</a>
</nav>
<div class="loading-overlay" aria-hidden="true"><div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Chargement…</span></div></div>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="confirmTitle">Confirmation</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body" id="confirmText"></div>
        <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="confirmOk">Confirmer</button></div>
    </div></div>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/offline.js') }}?v=1"></script>
<script src="{{ asset('js/app.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
