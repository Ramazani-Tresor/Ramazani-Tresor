<ul class="nav nav-tabs mb-3">
    @foreach(['me.show' => 'Mon dossier', 'me.career' => 'Ma carrière', 'me.finances' => 'Mes primes et soutiens', 'me.situations' => 'Mes congés et situations', 'me.corrections' => 'Mes demandes de correction'] as $r => $l)
        <li class="nav-item"><a class="nav-link {{ request()->routeIs($r) ? 'active' : '' }}" href="{{ route($r) }}">{{ $l }}</a></li>
    @endforeach
</ul>
