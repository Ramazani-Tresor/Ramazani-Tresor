@extends('layouts.app')
@section('title', 'Journal d’audit')
@section('content')
@include('partials.head', ['title' => 'Journal d’audit', 'sub' => 'Connexions et opérations sensibles, chaînées par empreinte SHA-256 : toute modification ou suppression d’une entrée est détectée.'])
<div class="alert {{ $chain['ok'] ? 'alert-success' : 'alert-danger' }} d-flex gap-2"><i class="bi {{ $chain['ok'] ? 'bi-shield-check' : 'bi-shield-x' }}"></i><div>{{ $chain['ok'] ? 'Intégrité vérifiée : '.$chain['checked'].' entrée(s) chaînée(s) sans rupture.' : 'ALERTE : chaîne rompue à l’entrée #'.$chain['broken_at'].' — le journal a été altéré.' }}</div></div>
<div class="card mb-3"><div class="card-body"><form class="filters" method="get">
    <div><label class="form-label" for="ak">Catégorie</label><select id="ak" name="kind" class="form-select"><option value="">Toutes les opérations</option><option value="auth" @selected(request('kind') === 'auth')>Connexions uniquement</option></select></div>
    <div><label class="form-label" for="aa">Action (préfixe)</label><input id="aa" name="action" value="{{ request('action') }}" class="form-control" placeholder="ex. payments, career"></div>
    <div><label class="form-label" for="au">Utilisateur</label><select id="au" name="user" class="form-select"><option value="">Tous</option>@foreach($users as $usr)<option value="{{ $usr->id }}" @selected(request('user') == $usr->id)>{{ $usr->name }}</option>@endforeach</select></div>
    <div><label class="form-label" for="af">Du</label><input id="af" type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div><label class="form-label" for="at">Au</label><input id="at" type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <button class="btn btn-primary">Filtrer</button>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-stack"><thead><tr><th>#</th><th>Date</th><th>Utilisateur</th><th>Action</th><th>Description</th><th>Adresse IP</th></tr></thead><tbody>
@foreach($logs as $l)<tr><td data-l="#">{{ $l->id }}</td><td data-l="Date" class="nw">{{ $l->created_at?->format('d/m/Y H:i:s') }}</td><td data-l="Utilisateur">{{ $l->user?->name ?? '—' }}</td><td data-l="Action"><code>{{ $l->action }}</code></td><td data-l="Description">{{ $l->description }} @if($l->reason)<br><span class="small text-muted-2">Motif : {{ $l->reason }}</span>@endif</td><td data-l="IP">{{ $l->ip_address }}</td></tr>@endforeach
</tbody></table></div><div class="card-body border-top">{{ $logs->links() }}</div></div>
@endsection
