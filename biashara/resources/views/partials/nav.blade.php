@php
    $sections = [
        null => [
            ['home', 'Accueil', 'bi-grid-1x2', null, 'home'],
            ['dashboard', 'Tableau de bord', 'bi-speedometer2', 'dashboard.view', 'dashboard'],
            ['remote', 'Consultation à distance', 'bi-broadcast', 'remote.view', 'remote'],
        ],
        'Ventes' => [
            ['pos', 'Point de vente', 'bi-cart3', 'sales.create', 'pos'],
            ['sales.index', 'Historique des ventes', 'bi-receipt', ['sales.view', 'sales.view_own'], 'sales.*'],
            ['returns.index', 'Retours', 'bi-arrow-counterclockwise', ['returns.create', 'sales.view'], 'returns.*'],
            ['customers.index', 'Clients et créances', 'bi-people', 'customers.view', 'customers.*'],
            ['cash.index', 'Ma caisse', 'bi-cash-coin', 'cash.use', 'cash.index'],
            ['cash.sessions', 'Sessions de caisse', 'bi-safe2', ['cash.view_all', 'cash.use'], 'cash.sessions'],
        ],
        'Stock' => [
            ['products.index', 'Produits et services', 'bi-box-seam', 'products.view', 'products.*'],
            ['categories.index', 'Catégories', 'bi-tags', 'categories.manage', 'categories.*'],
            ['stock.index', 'Stock', 'bi-boxes', 'stock.view', 'stock.index'],
            ['stock.movements', 'Mouvements', 'bi-arrow-left-right', 'stock.view', 'stock.movements'],
            ['purchases.index', 'Entrées de stock', 'bi-truck', 'purchases.view', 'purchases.*'],
            ['suppliers.index', 'Fournisseurs', 'bi-building', 'suppliers.manage', 'suppliers.*'],
            ['inventories.index', 'Inventaires', 'bi-clipboard-check', 'inventories.view', 'inventories.*'],
        ],
        'Gestion' => [
            ['expenses.index', 'Dépenses', 'bi-wallet2', ['expenses.view', 'expenses.create'], 'expenses.*'],
            ['reports.index', 'Rapports', 'bi-bar-chart-line', 'reports.view', 'reports.*'],
            ['sync.index', 'Synchronisation', 'bi-cloud-arrow-up', 'sync.resolve', 'sync.*'],
        ],
        'Administration' => [
            ['users.index', 'Utilisateurs', 'bi-person-badge', 'users.manage', 'users.*'],
            ['roles.index', 'Rôles et permissions', 'bi-shield-lock', 'roles.manage', 'roles.*'],
            ['settings.index', 'Paramètres', 'bi-gear', 'settings.manage', 'settings.*'],
            ['audit.index', "Journal d'audit", 'bi-journal-text', 'audit.view', 'audit.*'],
        ],
    ];
    $allowed = function ($perm) {
        if ($perm === null) return true;
        foreach ((array) $perm as $p) { if (can_do($p)) return true; }
        return false;
    };
@endphp
<a href="{{ route('home') }}" class="brand">
    <img src="{{ asset('images/logo.svg') }}" alt="">
    <span class="brand-name">Biashara<span class="brand-sub">{{ \Illuminate\Support\Str::limit(setting('shop_name'), 26) }}</span></span>
</a>
<nav class="nav flex-column">
    @foreach($sections as $title => $items)
        @php $visible = array_filter($items, fn ($i) => $allowed($i[3])); @endphp
        @if(count($visible))
            @if($title)<div class="nav-section">{{ $title }}</div>@endif
            @foreach($visible as [$route, $label, $icon, $perm, $pattern])
                <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}">
                    <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
                </a>
            @endforeach
        @endif
    @endforeach
</nav>
