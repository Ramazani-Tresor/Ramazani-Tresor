@extends('layouts.app')
@section('title', $asset->code)
@php $L = fn ($g, $v) => \App\Support\Labels::status($g, $v); $active = $asset->status === 'in_service'; @endphp
@section('content')
@include('partials.head', ['title' => $asset->designation, 'crumbs' => ['Patrimoine' => route('assets.index')], 'sub' => e($asset->code).' · '.e($asset->category->name).' · '.\App\Support\Labels::badge('asset_status', $asset->status)])
<div class="row g-3">
<div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">Fiche du bien</div><div class="card-body"><dl class="kv">
        <dt>Nature</dt><dd>{{ $L('asset_kind', $asset->category->kind) }}</dd><dt>Quantité</dt><dd>{{ $asset->quantity }}</dd>
        <dt>Localisation</dt><dd>{{ $asset->location ?: '—' }} @if($asset->parent)· dans <a href="{{ route('assets.show', $asset->parent) }}">{{ $asset->parent->designation }}</a>@endif</dd>
        <dt>Affectation</dt><dd>{{ $asset->orgUnit?->name ?? '—' }}</dd><dt>Responsable</dt><dd>{{ $asset->responsible?->fullName() ?? '—' }}</dd>
        <dt>Marque / série</dt><dd>{{ $asset->brand_model }} {{ $asset->serial_number }}</dd>
        <dt>Acquisition</dt><dd>{{ $asset->acquisition_date?->format('d/m/Y') ?? '—' }} {{ $asset->acquisition_mode ? '· '.$L('acquisition', $asset->acquisition_mode) : '' }}</dd>
        <dt>Valeur</dt><dd>{{ $asset->value !== null ? money($asset->value, $asset->currency) : '—' }}</dd><dt>État</dt><dd>{{ $L('asset_condition', $asset->condition) }}</dd><dt>Observations</dt><dd>{{ $asset->notes ?: '—' }}</dd>
    </dl></div></div>
    @if($asset->children->count())<div class="card mb-3"><div class="card-header">Biens situés dans ce bien ({{ $asset->children->count() }})</div><ul class="list-group list-group-flush">@foreach($asset->children as $ch)<li class="list-group-item"><a href="{{ route('assets.show', $ch) }}">{{ $ch->code }}</a> — {{ $ch->designation }} ({{ $ch->quantity }})</li>@endforeach</ul></div>@endif
    <div class="card"><div class="card-header">Historique des mouvements</div>
        <ul class="timeline p-3">@foreach($asset->movements->sortByDesc('id') as $mv)<li class="{{ in_array($mv->type, ['reform', 'scrap']) ? 'warn' : '' }}"><span class="dot"></span><div class="when">{{ $mv->movement_date->format('d/m/Y') }} · {{ $mv->user?->name }}</div><div class="what">{{ $L('asset_movement', $mv->type) }}</div><div class="small">@if($mv->fromUnit || $mv->toUnit){{ $mv->fromUnit?->name ?? '—' }} → {{ $mv->toUnit?->name ?? '—' }} @endif @if($mv->new_condition)· état : {{ $mv->old_condition ? $L('asset_condition', $mv->old_condition).' → ' : '' }}{{ $L('asset_condition', $mv->new_condition) }}@endif @if($mv->responsible)· responsable : {{ $mv->responsible->fullName() }}@endif @if($mv->reference)· réf. {{ $mv->reference }}@endif</div>@if($mv->notes)<div class="small text-muted-2">{{ $mv->notes }}</div>@endif</li>@endforeach</ul>
    </div>
</div>
<div class="col-lg-5">
@if(can_do('assets.manage') && $active)
    <div class="card mb-3"><div class="card-header">Affectation / transfert</div><div class="card-body"><form method="post" action="{{ route('assets.transfer', $asset) }}" class="row g-2">@csrf
        @include('partials.field', ['name' => 'to_unit_id', 'label' => 'Vers la structure', 'type' => 'select', 'options' => $units->pluck('name', 'id')->all(), 'col' => 'col-12'])
        @include('partials.field', ['name' => 'responsible_employee_id', 'label' => 'Responsable', 'type' => 'select', 'options' => $people->mapWithKeys(fn ($p) => [$p->id => $p->matricule.' — '.$p->fullName()])->all(), 'col' => 'col-12'])
        @include('partials.field', ['name' => 'location', 'label' => 'Nouvelle localisation', 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'movement_date', 'label' => 'Date', 'type' => 'date', 'value' => now()->toDateString(), 'required' => true, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'reference', 'label' => 'Référence (bon de sortie…)', 'col' => 'col-12'])
        <div class="col-12"><button class="btn btn-primary btn-sm">Enregistrer le mouvement</button></div></form></div></div>
    <div class="card mb-3"><div class="card-header">Suivi de l’état</div><div class="card-body"><form method="post" action="{{ route('assets.condition', $asset) }}" class="row g-2">@csrf
        @include('partials.field', ['name' => 'condition', 'label' => 'Nouvel état', 'type' => 'select', 'required' => true, 'options' => \App\Support\Labels::options('asset_condition'), 'value' => $asset->condition, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'movement_date', 'label' => 'Date du constat', 'type' => 'date', 'value' => now()->toDateString(), 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'notes', 'label' => 'Observations', 'col' => 'col-12'])
        <div class="col-12"><button class="btn btn-light btn-sm">Enregistrer</button></div></form></div></div>
@endif
@if(can_do('assets.manage') && $asset->status !== 'scrapped')
    <div class="card"><div class="card-header">Réforme / mise au rebut</div><div class="card-body"><form method="post" action="{{ route('assets.retire', $asset) }}" class="row g-2" data-confirm="Confirmer la sortie du bien {{ $asset->code }} ? Il restera dans l’historique.">@csrf
        @include('partials.field', ['name' => 'type', 'label' => 'Opération', 'type' => 'select', 'required' => true, 'options' => $active ? ['reform' => 'Réforme', 'scrap' => 'Mise au rebut'] : ['scrap' => 'Mise au rebut'], 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'reference', 'label' => 'PV / décision', 'required' => true, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'notes', 'label' => 'Motif', 'col' => 'col-12'])
        <div class="col-12"><button class="btn btn-outline-danger btn-sm">Enregistrer la sortie</button></div></form></div></div>
@endif
</div>
</div>
@endsection
