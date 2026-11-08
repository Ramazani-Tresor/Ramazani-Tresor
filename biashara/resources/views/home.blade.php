@extends('layouts.app')
@section('title', 'Accueil')
@section('content')
@php
    $tiles = [
        ['pos', 'Ventes', 'Encaisser rapidement', 'bi-cart3', 'sales.create', 'primary'],
        ['products.index', 'Produits', 'Articles et services', 'bi-box-seam', 'products.view', ''],
        ['customers.index', 'Clients', 'Comptes et créances', 'bi-people', 'customers.view', ''],
        ['stock.index', 'Stock', 'Niveaux et mouvements', 'bi-boxes', 'stock.view', ''],
        ['expenses.index', 'Dépenses', 'Sorties d\'argent', 'bi-wallet2', ['expenses.view', 'expenses.create'], ''],
        ['reports.index', 'Rapports', 'Analyses et exports', 'bi-bar-chart-line', 'reports.view', ''],
        ['inventories.index', 'Inventaire', 'Comptage physique', 'bi-clipboard-check', 'inventories.view', ''],
        ['settings.index', 'Paramètres', 'Commerce et sécurité', 'bi-gear', 'settings.manage', ''],
        ['purchases.index', 'Entrées de stock', 'Approvisionnements', 'bi-truck', 'purchases.view', ''],
        ['returns.index', 'Retours', 'Retours et annulations', 'bi-arrow-counterclockwise', ['returns.create', 'sales.view'], ''],
        ['cash.index', 'Caisse', 'Ouverture et clôture', 'bi-cash-coin', 'cash.use', ''],
        ['categories.index', 'Catégories', 'Familles d\'articles', 'bi-tags', 'categories.manage', ''],
        ['users.index', 'Utilisateurs', 'Comptes et rôles', 'bi-person-badge', 'users.manage', ''],
        ['remote', 'Contrôle à distance', 'Vue du responsable', 'bi-broadcast', 'remote.view', 'navy'],
        ['dashboard', 'Tableau de bord', 'Indicateurs du jour', 'bi-speedometer2', 'dashboard.view', 'navy'],
    ];
@endphp
<div class="page-head">
    <div>
        <h1>Bonjour {{ explode(' ', auth()->user()->name)[0] }} 👋</h1>
        <div class="sub">{{ setting('shop_name') }} · {{ now()->translatedFormat('l d F Y') }}</div>
    </div>
    @if(can_do('cash.use'))
        @if($session)
            <a href="{{ route('cash.show', $session) }}" class="btn btn-light"><i class="bi bi-cash-coin me-1 text-success"></i>Caisse ouverte · {{ money($sessionCash) }} en espèces</a>
        @else
            <a href="{{ route('cash.index') }}" class="btn btn-warning"><i class="bi bi-unlock me-1"></i>Ouvrir ma caisse</a>
        @endif
    @endif
</div>

@if($conflicts || $pendingExpenses || $lowStockCount)
<div class="d-flex flex-wrap gap-2 mb-3">
    @if($conflicts)<a href="{{ route('sync.index') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ $conflicts }} vente(s) hors ligne en conflit</a>@endif
    @if($pendingExpenses)<a href="{{ route('expenses.index', ['status' => 'pending']) }}" class="btn btn-sm btn-outline-warning"><i class="bi bi-hourglass-split me-1"></i>{{ $pendingExpenses }} dépense(s) à valider</a>@endif
    @if($lowStockCount)<a href="{{ route('stock.index', ['stock' => 'low']) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-down-circle me-1"></i>{{ $lowStockCount }} article(s) sous le seuil</a>@endif
</div>
@endif

<div class="tiles">
    @foreach($tiles as [$route, $title, $desc, $icon, $perm, $style])
        @php $ok = collect((array) $perm)->contains(fn ($p) => can_do($p)); @endphp
        @if($ok)
            <a href="{{ route($route) }}" class="tile {{ $style }}">
                <span class="ico"><i class="bi {{ $icon }}"></i></span>
                <span><span class="t d-block">{{ $title }}</span><span class="d">{{ $desc }}</span></span>
            </a>
        @endif
    @endforeach
</div>
@endsection
