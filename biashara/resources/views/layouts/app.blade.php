<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0B1F3A">
    <title>@yield('title', 'Accueil') · {{ setting('shop_name') }}</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
    @stack('head')
</head>
<body data-user-id="{{ auth()->id() }}" data-sw="{{ route('pwa.sw') }}" data-base="{{ url('/') }}">
<div class="app-shell">
    <aside class="sidebar d-none d-lg-block" aria-label="Menu principal">
        @include('partials.nav')
    </aside>

    <div class="offcanvas offcanvas-start sidebar-mobile" tabindex="-1" id="mobileMenu" aria-label="Menu">
        <div class="offcanvas-body p-0">
            <div class="sidebar">@include('partials.nav')</div>
        </div>
    </div>

    <div class="main">
        <header class="topbar no-print">
            <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-label="Ouvrir le menu">
                <i class="bi bi-list fs-4"></i>
            </button>
            @if(can_do('products.view'))
                <form class="search d-none d-md-block" action="{{ route('products.index') }}" method="get" role="search">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" placeholder="Rechercher un article, une référence, un code-barres…" value="{{ request('q') }}">
                    </div>
                </form>
            @endif
            <div class="ms-auto d-flex align-items-center gap-2">
                <span id="connPill" class="conn-pill conn-online" title="État de la connexion au serveur">
                    <span class="dot"></span><span class="txt">En ligne</span>
                </span>
                <a id="syncPill" href="{{ can_do('sales.create') ? route('pos') : '#' }}" class="conn-pill conn-sync d-none" title="Ventes hors ligne en attente de synchronisation">
                    <i class="bi bi-arrow-repeat"></i><span class="txt">0</span>
                </a>
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="rounded-circle bg-sky text-navy fw-bold d-inline-grid" style="width:32px;height:32px;place-items:center">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="d-none d-sm-inline text-start lh-sm"><span class="d-block fw-600 small">{{ auth()->user()->name }}</span><span class="d-block text-muted-2" style="font-size:.72rem">{{ auth()->user()->role->name }}</span></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="{{ route('account') }}"><i class="bi bi-person-gear me-2"></i>Mon compte</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<nav class="mobile-nav no-print" aria-label="Navigation rapide">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}"><i class="bi bi-grid-1x2"></i>Accueil</a>
    @if(can_do('sales.create'))<a href="{{ route('pos') }}" class="{{ request()->routeIs('pos') ? 'active' : '' }}"><i class="bi bi-cart3"></i>Vendre</a>@endif
    @if(can_do('products.view'))<a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="bi bi-box-seam"></i>Produits</a>@endif
    @if(can_do('dashboard.view'))<a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i>Bilan</a>@endif
    <a href="#" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-three-dots"></i>Plus</a>
</nav>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Confirmation</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
            <div class="modal-body" id="confirmMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmOk">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/offline-store.js') }}?v=1"></script>
<script src="{{ asset('js/app.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
