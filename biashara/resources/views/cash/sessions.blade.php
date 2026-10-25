@extends('layouts.app')
@section('title', 'Sessions de caisse')
@section('content')
<div class="page-head"><div><h1>Sessions de caisse</h1></div>@if(can_do('cash.use'))<a href="{{ route('cash.index') }}" class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Ma caisse</a>@endif</div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        @if($users->isNotEmpty())<div><select name="user_id" class="form-select"><option value="">Tous les caissiers</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>@endif
        <div><select name="status" class="form-select"><option value="">Toutes</option><option value="open" @selected(request('status') === 'open')>Ouvertes</option><option value="closed" @selected(request('status') === 'closed')>Clôturées</option></select></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Session</th><th>Caissier</th><th>Ouverture</th><th>Clôture</th><th class="num">Fonds initial</th><th class="num">Théorique</th><th class="num">Compté</th><th class="num">Écart</th><th>Statut</th></tr></thead>
        <tbody>
        @forelse($sessions as $s)
            <tr><td><a href="{{ route('cash.show', $s) }}" class="fw-600">{{ $s->number }}</a></td><td>{{ $s->user->name }}</td><td>{{ $s->opened_at->format('d/m/Y H:i') }}</td><td>{{ $s->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="num">{{ money($s->opening_amount) }}</td><td class="num">{{ money($s->isOpen() ? $expected[$s->id] : $s->expected_cash) }}</td><td class="num">{{ $s->counted_cash !== null ? money($s->counted_cash) : '—' }}</td>
                <td class="num {{ (float) $s->difference < 0 ? 'text-danger' : ((float) $s->difference > 0 ? 'text-warning' : '') }}">{{ $s->difference !== null ? money($s->difference) : '—' }}</td>
                <td>{!! $s->isOpen() ? '<span class="status status-ok">Ouverte</span>' : '<span class="status status-muted">Clôturée</span>' !!}</td></tr>
        @empty
            <tr><td colspan="9" class="empty"><i class="bi bi-safe2"></i>Aucune session.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $sessions->links() }}</div>
</div>
@endsection
