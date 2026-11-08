@extends('layouts.app')
@section('title', 'Inventaires')
@section('content')
@include('partials.head', ['title' => 'Inventaires du patrimoine', 'crumbs' => ['Patrimoine' => route('assets.index')], 'sub' => 'Campagnes d’inventaire : constat de présence, d’état et de localisation ; la clôture met à jour les biens et historise chaque constat.'])
<div class="row g-3">
<div class="col-lg-8"><div class="card">
@if($inventories->count())<div class="table-responsive"><table class="table table-stack"><thead><tr><th>Référence</th><th>Libellé</th><th>Périmètre</th><th>Début</th><th>Constats</th><th>Statut</th></tr></thead><tbody>@foreach($inventories as $i)<tr><td data-l="Référence"><a href="{{ route('inventories.show', $i) }}" class="fw-600">{{ $i->reference }}</a></td><td data-l="Libellé">{{ $i->label }}</td><td data-l="Périmètre">{{ $i->orgUnit?->name ?? 'Toute l’université' }}</td><td data-l="Début">{{ $i->started_on->format('d/m/Y') }}</td><td data-l="Constats">{{ $i->lines_count }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('inventory', $i->status) !!}</td></tr>@endforeach</tbody></table></div>@else <div class="card-body">@include('partials.empty', ['icon' => 'bi-clipboard-check', 'text' => 'Aucun inventaire.'])</div> @endif
</div></div>
<div class="col-lg-4"><div class="card"><div class="card-header">Ouvrir un inventaire</div><div class="card-body"><form method="post" action="{{ route('inventories.store') }}" class="row g-2">@csrf
    @include('partials.field', ['name' => 'label', 'label' => 'Libellé', 'required' => true, 'value' => 'Inventaire '.now()->year, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Périmètre', 'type' => 'select', 'options' => $units->pluck('name', 'id')->all(), 'placeholder' => 'Toute l’université', 'col' => 'col-12'])
    @include('partials.field', ['name' => 'started_on', 'label' => 'Date de début', 'type' => 'date', 'value' => now()->toDateString(), 'required' => true, 'col' => 'col-12'])
    <div class="col-12"><button class="btn btn-primary">Ouvrir</button></div>
</form></div></div></div>
</div>
@endsection
