@extends('layouts.app')
@section('title', 'Générer une période')
@section('content')
@include('partials.head', ['title' => 'Générer les droits d’une période', 'crumbs' => ['Primes' => route('primes.index')], 'sub' => 'Opération idempotente : relancer la génération d’une période ne crée aucun doublon. Aucun droit n’est créé pour un agent sans taux applicable, ni après sa fin des droits.'])
<div class="row g-3">
<div class="col-lg-7"><form method="post" action="{{ route('primes.generate.run') }}" class="card" data-confirm="Générer les droits de cette période pour tous les agents éligibles ?"><div class="card-body row g-3">@csrf
    @include('partials.field', ['name' => 'prime_type_id', 'label' => 'Type de prime', 'type' => 'select', 'required' => true, 'options' => $types->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.\App\Support\Labels::status('periodicity', $t->periodicity).')'])->all(), 'col' => 'col-12'])
    @include('partials.field', ['name' => 'period_start', 'label' => 'Début de période', 'type' => 'date', 'required' => true, 'value' => now()->startOfMonth()->toDateString(), 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'period_end', 'label' => 'Fin de période', 'type' => 'date', 'required' => true, 'value' => now()->endOfMonth()->toDateString(), 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'period_label', 'label' => 'Libellé de période', 'required' => true, 'value' => now()->format('Y-m'), 'col' => 'col-md-4', 'help' => 'ex. 2026-11, T1 2026-2027, Tranche 2'])
    @include('partials.field', ['name' => 'staff_family', 'label' => 'Limiter à une famille', 'type' => 'select', 'options' => \App\Support\PermissionRegistry::FAMILIES, 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Limiter à une unité', 'type' => 'select', 'options' => $units->pluck('name', 'id')->all(), 'col' => 'col-md-6'])
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-calculator"></i> Générer</button></div></form></div>
<div class="col-lg-5"><div class="card"><div class="card-header">Règles appliquées</div><div class="card-body small">
    <ol class="mb-2 ps-3">
        <li><b>Taux</b> : le taux actif le plus spécifique (famille, catégorie, grade, fonction, unité, année) l’emporte.</li>
        <li><b>Jours ouvrant droit</b> : rien avant l’engagement, ni à partir de la date de fin des droits (décès, mutation externe, retraite, démission, départ).</li>
        <li><b>Situations</b> : chaque jour couvert par une situation approuvée applique le « % de primes » de son type (ex. suspension 0 %).</li>
        <li><b>Montant</b> = taux × Σ coefficients journaliers / jours de la période (types au prorata).</li>
    </ol>
    <div class="form-section">Taux actifs</div>
    @foreach($types as $t)<div><b>{{ $t->name }}</b> : {{ $t->rates->count() }} taux @if(! $t->rates->count())<span class="text-danger">— aucun, à définir dans « Taux de primes »</span>@endif</div>@endforeach
</div></div></div>
</div>
@endsection
