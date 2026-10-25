@extends('layouts.app')
@section('title', "Journal d'audit")
@section('content')
<div class="page-head"><div><h1>Journal d'audit</h1><div class="sub">Actions sensibles : connexions, ventes, annulations, retours, caisse, stock, paramètres…</div></div></div>
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        @include('partials.period')
        <div><label class="form-label small mb-1">Utilisateur</label><select name="user_id" class="form-select"><option value="">Tous</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-1">Action</label><select name="action" class="form-select"><option value="">Toutes</option>@foreach($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-1">Texte</label><input type="search" name="q" class="form-control" value="{{ request('q') }}"></div>
        <button class="btn btn-light"><i class="bi bi-funnel"></i> Filtrer</button>
    </form></div>
    <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Description</th><th>Adresse IP</th></tr></thead>
        <tbody>
        @forelse($logs as $l)
            <tr><td class="small text-nowrap">{{ $l->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $l->user?->name ?? '—' }}</td><td><code>{{ $l->action }}</code></td>
                <td class="small">{{ $l->description }}
                    @if($l->old_values || $l->new_values)<details><summary class="text-muted-2">détails</summary><pre class="small mb-0">{{ json_encode(['avant' => $l->old_values, 'après' => $l->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</td>
                <td class="small">{{ $l->ip_address }}</td></tr>
        @empty
            <tr><td colspan="5" class="empty">Aucune entrée.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body">{{ $logs->links() }}</div>
</div>
@endsection
