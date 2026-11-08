@extends('layouts.app')
@section('title', 'Patrimoine')
@section('content')
@include('partials.head', ['title' => 'Patrimoine universitaire', 'sub' => 'Biens immobiliers et mobiliers : code patrimonial, affectation, état et historique de chaque mouvement.', 'actions' => (can_do('assets.manage') ? '<a class="btn btn-primary" href="'.route('assets.create').'"><i class="bi bi-plus-lg"></i> Entrée en patrimoine</a>' : '').(can_do('inventories.manage') ? '<a class="btn btn-light" href="'.route('inventories.index').'"><i class="bi bi-clipboard-check"></i> Inventaires</a>' : '').'<a class="btn btn-light" href="'.route('reports.export', ['assets', 'pdf']).'"><i class="bi bi-file-earmark-pdf"></i> Inventaire PDF</a>'])
<div class="card mb-3"><div class="card-body"><form class="filters" method="get">
    <div style="flex:2 1 200px"><label class="form-label" for="aq">Recherche</label><input id="aq" name="q" value="{{ request('q') }}" class="form-control" placeholder="Code, désignation, n° de série, localisation"></div>
    <div><label class="form-label" for="ak">Nature</label><select id="ak" name="kind" class="form-select"><option value="">Toutes</option>@foreach(\App\Support\Labels::options('asset_kind') as $k => $v)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="form-label" for="ac">Catégorie</label><select id="ac" name="category" class="form-select"><option value="">Toutes</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div><label class="form-label" for="au">Affectation</label><select id="au" name="unit" class="form-select"><option value="">Toutes</option>@foreach($units as $un)<option value="{{ $un->id }}" @selected(request('unit') == $un->id)>{{ $un->name }}</option>@endforeach</select></div>
    <div><label class="form-label" for="ae">État</label><select id="ae" name="condition" class="form-select"><option value="">Tous</option>@foreach(\App\Support\Labels::options('asset_condition') as $k => $v)<option value="{{ $k }}" @selected(request('condition') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div><label class="form-label" for="as">Statut</label><select id="as" name="status" class="form-select"><option value="">Tous</option>@foreach(\App\Support\Labels::options('asset_status') as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button>
</form></div></div>
<div class="card">
@if($assets->count())<div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Code</th><th>Désignation</th><th>Catégorie</th><th>Qté</th><th>Localisation</th><th>Affectation</th><th>État</th><th>Statut</th></tr></thead><tbody>
@foreach($assets as $a)<tr><td data-l="Code"><a href="{{ route('assets.show', $a) }}" class="fw-600">{{ $a->code }}</a></td><td data-l="Désignation">{{ $a->designation }}</td><td data-l="Catégorie">{{ $a->category->name }}</td><td data-l="Qté">{{ $a->quantity }}</td><td data-l="Localisation">{{ $a->location ?: $a->parent?->designation }}</td><td data-l="Affectation">{{ $a->orgUnit?->name }}</td><td data-l="État">{{ \App\Support\Labels::status('asset_condition', $a->condition) }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('asset_status', $a->status) !!}</td></tr>@endforeach
</tbody></table></div><div class="card-body border-top">{{ $assets->links() }}</div>
@else <div class="card-body">@include('partials.empty', ['icon' => 'bi-building', 'text' => 'Aucun bien pour ces critères.'])</div> @endif
</div>
@endsection
