@php
    $u = auth()->user();
    $sections = [
        'Pilotage' => [
            ['dashboard', 'Tableau de bord', 'bi-speedometer2', ['dashboard.view'], 'dashboard'],
            ['stats.scientific', 'Statistiques scientifiques', 'bi-mortarboard', ['reports.view', 'dashboard.view'], 'stats.scientific'],
            ['reports.index', 'Éditions PDF / Excel', 'bi-printer', ['reports.view', 'reports.finance', 'assets.view'], 'reports.*'],
        ],
        'Personnel' => [
            ['employees.index', 'Agents', 'bi-people', ['employees.view', 'employees.directory'], 'employees.*'],
            ['structure.index', 'Structure et postes', 'bi-diagram-3', ['employees.view', 'structure.manage'], 'structure.*'],
            ['recruitment.index', 'Recrutement', 'bi-person-plus', ['recruitment.manage', 'recruitment.engage'], 'recruitment.*'],
            ['corrections.index', 'Demandes de correction', 'bi-pencil-square', ['corrections.handle'], 'corrections.*'],
        ],
        'Carrière et mouvements' => [
            ['career.index', 'Décisions de carrière', 'bi-graph-up-arrow', ['career.manage', 'career.validate'], 'career.*'],
            ['situations.index', 'Congés et situations', 'bi-calendar2-week', ['situations.manage', 'situations.validate'], 'situations.*'],
            ['mutations.index', 'Mutations', 'bi-arrow-left-right', ['mutations.manage', 'mutations.validate'], 'mutations.*'],
            ['deaths.index', 'Décès', 'bi-flower1', ['deaths.manage', 'deaths.validate'], 'deaths.*'],
        ],
        'Finances' => [
            ['primes.index', 'Primes', 'bi-cash-coin', ['primes.view'], 'primes.index|primes.show'],
            ['primes.generate', 'Générer une période', 'bi-calculator', ['primes.generate'], 'primes.generate'],
            ['payments.index', 'Paiements et acomptes', 'bi-wallet2', ['payments.create', 'payments.validate'], 'payments.*'],
            ['social.index', 'Soutiens sociaux', 'bi-heart', ['social.manage', 'social.approve', 'social.pay'], 'social.*'],
            ['rates.index', 'Taux de primes', 'bi-sliders', ['primes.configure'], 'rates.*'],
        ],
        'Patrimoine' => [
            ['assets.index', 'Biens', 'bi-building', ['assets.view'], 'assets.*'],
            ['inventories.index', 'Inventaires', 'bi-clipboard-check', ['inventories.manage'], 'inventories.*'],
        ],
        'Mon espace' => $u->employee_id ? [
            ['me.show', 'Mon dossier', 'bi-person-badge', ['self.view'], 'me.show'],
            ['me.career', 'Ma carrière', 'bi-signpost-split', ['self.view'], 'me.career'],
            ['me.finances', 'Mes primes et soutiens', 'bi-piggy-bank', ['self.view'], 'me.finances'],
            ['me.situations', 'Mes congés', 'bi-calendar-check', ['self.view'], 'me.situations'],
            ['me.corrections', 'Mes demandes', 'bi-chat-left-text', ['self.view'], 'me.corrections'],
        ] : [],
        'Administration' => [
            ['settings.index', 'Paramétrage', 'bi-gear', ['settings.manage'], 'settings.*|refs.*'],
            ['users.index', 'Utilisateurs', 'bi-person-gear', ['users.manage'], 'users.*'],
            ['roles.index', 'Rôles et permissions', 'bi-shield-lock', ['roles.manage', 'users.manage'], 'roles.*'],
            ['audit.index', 'Journal d’audit', 'bi-journal-check', ['audit.view'], 'audit.*'],
            ['backups.index', 'Sauvegardes', 'bi-hdd-stack', ['backups.manage'], 'backups.*'],
            ['tokens.index', 'Jetons d’API', 'bi-plug', ['api.tokens'], 'tokens.*'],
        ],
    ];
    $pending = $u->hasPermission('payments.validate') ? \App\Models\PrimePayment::where('status', 'pending')->where('created_by', '!=', $u->id)->count() : 0;
@endphp
<a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/logo.svg') }}" alt=""><span><b>{{ setting('university_acronym') ?: 'UNILU' }}-GRH</b><small>{{ setting('university_name') }}</small></span></a>
@foreach($sections as $title => $items)
    @php $visible = array_filter($items, fn ($i) => collect($i[3])->contains(fn ($p) => $u->hasPermission($p))); @endphp
    @if($visible)
        <div class="nav-sec">{{ $title }}</div>
        <nav class="nav flex-column">
            @foreach($visible as [$route, $label, $icon, $perms, $active])
                <a class="nav-link {{ request()->routeIs(...explode('|', $active)) ? 'active' : '' }}" href="{{ route($route) }}" @if(request()->routeIs(...explode('|', $active))) aria-current="page" @endif>
                    <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
                    @if($route === 'payments.index' && $pending)<span class="badge rounded-pill text-bg-warning">{{ $pending }}</span>@endif
                </a>
            @endforeach
        </nav>
    @endif
@endforeach
