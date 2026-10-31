@php
    $menu = [
        null => [
            ['dashboard', 'Tableau de bord', 'bi-speedometer2', 'dashboard.view', 'dashboard'],
            ['portal.show', 'Mon compte étudiant', 'bi-person-vcard', 'student.self', 'portal.*'],
        ],
        'Étudiants' => [
            ['students.index', 'Étudiants', 'bi-people', 'students.view', 'students.*'],
            ['imports.students.create', 'Import Excel', 'bi-file-earmark-spreadsheet', 'students.import', 'imports.students.*'],
        ],
        'Encaissements' => [
            ['payments.entry', 'Saisie des bordereaux', 'bi-receipt', 'payments.create', 'payments.entry'],
            ['payments.control', 'Contrôle et validation', 'bi-check2-square', 'payments.check|payments.validate', 'payments.control'],
            ['payments.index', 'Paiements', 'bi-cash-stack', 'payments.view', 'payments.index|payments.show'],
            ['bank.index', 'Relevés et rapprochement', 'bi-bank', 'bank.import|bank.reconcile|bank.review', 'bank.*'],
            ['credits.index', 'Excédents', 'bi-arrow-left-right', 'credits.request|credits.approve', 'credits.*'],
        ],
        'Frais et engagements' => [
            ['fees.index', 'Frais, barèmes, tranches', 'bi-tags', 'fees.view', 'fees.*'],
            ['adjustments.index', 'Réductions et exonérations', 'bi-percent', 'adjustments.request|adjustments.approve', 'adjustments.*'],
            ['contracts.index', 'Contrats et échéanciers', 'bi-file-earmark-text', 'contracts.view', 'contracts.*'],
            ['exams.index', 'Contrôle des examens', 'bi-mortarboard', 'exams.view', 'exams.*'],
        ],
        'Pilotage' => [
            ['reports.index', 'Rapports', 'bi-bar-chart-line', 'reports.view', 'reports.*'],
            ['years.index', 'Années et clôtures', 'bi-calendar-check', 'years.manage|years.close|years.reopen', 'years.*'],
        ],
        'Administration' => [
            ['users.index', 'Utilisateurs et permissions', 'bi-person-gear', 'users.manage|roles.manage', 'users.*|roles.*'],
            ['audit.index', 'Journal d’audit', 'bi-shield-check', 'audit.view', 'audit.*'],
            ['sync.index', 'Synchronisation', 'bi-cloud-arrow-up', 'sync.use|sync.resolve', 'sync.*'],
            ['settings.index', 'Paramètres', 'bi-gear', 'settings.manage|structure.manage|finance_settings.manage|backups.manage', 'settings.*'],
        ],
    ];
    $can = fn ($p) => collect(explode('|', $p))->contains(fn ($x) => can_do($x));
@endphp
<a href="{{ route('home') }}" class="brand"><img src="{{ asset('images/logo.svg') }}" alt=""><span><b>Karo</b><small>{{ setting('university_acronym') ?: setting('university_name') }}</small></span></a>
<nav class="nav flex-column">
    @foreach($menu as $title => $items)
        @php $vis = array_filter($items, fn ($i) => $can($i[3])); @endphp
        @if($vis)
            @if($title)<div class="nav-sec">{{ $title }}</div>@endif
            @foreach($vis as [$r, $label, $icon, $p, $pattern])
                <a class="nav-link {{ request()->routeIs(...explode('|', $pattern)) ? 'active' : '' }}" href="{{ route($r) }}" @if(request()->routeIs(...explode('|', $pattern))) aria-current="page" @endif><i class="bi {{ $icon }}"></i><span>{{ $label }}</span></a>
            @endforeach
        @endif
    @endforeach
</nav>
