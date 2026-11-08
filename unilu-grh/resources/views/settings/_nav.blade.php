<div class="list-group mb-3">
    <a href="{{ route('settings.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('settings.index') ? 'active' : '' }}"><i class="bi bi-bank me-2"></i>Institution et règles générales</a>
    @foreach($refs as $k => [$m, $t])<a href="{{ route('refs.index', $k) }}" class="list-group-item list-group-item-action {{ request()->route('ref') === $k ? 'active' : '' }}">{{ $t }}</a>@endforeach
    @if(can_do('primes.configure'))<a href="{{ route('rates.index') }}" class="list-group-item list-group-item-action">Taux et montants des primes</a>@endif
    <a href="{{ route('structure.index') }}" class="list-group-item list-group-item-action">Facultés, départements, services et postes</a>
</div>
