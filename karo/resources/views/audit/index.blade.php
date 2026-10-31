@extends('layouts.app')
@section('title', 'Journal d’audit')
@section('content')
@include('partials.head', ['title' => 'Journal d’audit', 'sub' => 'Ajout seul, chaîné par empreintes SHA-256 : toute modification ou suppression hors application est détectable.',
    'actions' => '<form method="post" action="'.route('audit.verify').'">'.csrf_field().'<button class="btn btn-light"><i class="bi bi-shield-check"></i> Vérifier l’intégrité</button></form><a class="btn btn-light" href="'.route('audit.export', request()->only('from', 'to', 'user', 'action', 'q')).'"><i class="bi bi-download"></i> Copie indépendante (CSV)</a>'])
<div class="card">
    <div class="card-body border-bottom"><form class="filters" method="get">
        <div><label class="form-label" for="af">Du</label><input id="af" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
        <div><label class="form-label" for="at">Au</label><input id="at" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}"></div>
        <div><label class="form-label" for="au">Utilisateur</label><select id="au" name="user" class="form-select"><option value="">Tous</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <div><label class="form-label" for="aa">Action</label><select id="aa" name="action" class="form-select"><option value="">Toutes</option>@foreach($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div>
        <div><label class="form-label" for="aq">Texte</label><input id="aq" type="search" name="q" class="form-control" value="{{ request('q') }}"></div>
        <div class="acts"><button class="btn btn-primary">Filtrer</button></div></form></div>
    <div class="table-responsive"><table class="table table-sm table-stack mb-0"><thead><tr><th>#</th><th>Date</th><th>Acteur</th><th>Action</th><th>Description / motif</th><th>Poste</th></tr></thead><tbody>
    @forelse($logs as $l)
        <tr><td data-l="#" class="small">{{ $l->id }}</td><td data-l="Date" class="small nw">{{ $l->created_at->format('d/m/Y H:i:s') }}</td><td data-l="Acteur">{{ $l->user?->name ?? 'Système / anonyme' }}</td><td data-l="Action"><code>{{ $l->action }}</code></td>
            <td data-l="Description" class="small">{{ $l->description }}@if($l->reason)<br><em>Motif : {{ $l->reason }}</em>@endif
                @if($l->old_values || $l->new_values)<details><summary>Valeurs avant / après</summary><pre class="small mb-0" style="white-space:pre-wrap">{{ json_encode(['avant' => $l->old_values, 'après' => $l->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</td>
            <td data-l="Poste" class="small">{{ $l->ip_address }}<br>{{ $l->device_id }}</td></tr>
    @empty<tr><td colspan="6">@include('partials.empty')</td></tr>@endforelse
    </tbody></table></div><div class="card-body">{{ $logs->links() }}</div></div>
@endsection
