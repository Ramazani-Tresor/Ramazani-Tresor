@extends('layouts.app')
@section('title', 'Taux de primes')
@section('content')
@include('partials.head', ['title' => 'Taux et montants des primes', 'sub' => 'Montants librement définis par l’administration selon le grade, la catégorie, la fonction, le service, l’année et une période de validité. Le taux le plus spécifique s’applique. Un taux enregistré n’est jamais modifié : pour changer un montant, clôturez sa validité et créez-en un nouveau.'])
<div class="card mb-3"><div class="card-header">Nouveau taux</div><div class="card-body"><form method="post" action="{{ route('rates.store') }}" class="row g-2">@csrf
    @include('partials.field', ['name' => 'prime_type_id', 'label' => 'Type de prime', 'type' => 'select', 'required' => true, 'options' => $types->pluck('name', 'id')->all(), 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'amount', 'label' => 'Montant pour une période complète', 'type' => 'decimal', 'required' => true, 'col' => 'col-md-2'])
    @include('partials.field', ['name' => 'currency', 'label' => 'Devise', 'value' => setting('default_currency', 'USD'), 'required' => true, 'col' => 'col-md-2'])
    @include('partials.field', ['name' => 'academic_year_id', 'label' => 'Année académique', 'type' => 'select', 'options' => $years->pluck('code', 'id')->all(), 'placeholder' => 'Toutes', 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'staff_family', 'label' => 'Famille', 'type' => 'select', 'options' => \App\Support\PermissionRegistry::FAMILIES, 'placeholder' => 'Toutes', 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'staff_category_id', 'label' => 'Catégorie', 'type' => 'select', 'options' => $categories->pluck('name', 'id')->all(), 'placeholder' => 'Toutes', 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'grade_id', 'label' => 'Grade', 'type' => 'select', 'options' => $grades->pluck('name', 'id')->all(), 'placeholder' => 'Tous', 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'job_function_id', 'label' => 'Fonction', 'type' => 'select', 'options' => $functions->pluck('name', 'id')->all(), 'placeholder' => 'Toutes', 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Service / faculté', 'type' => 'select', 'options' => $units->pluck('name', 'id')->all(), 'placeholder' => 'Tous', 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'valid_from', 'label' => 'Valide du', 'type' => 'date', 'col' => 'col-md-2'])
    @include('partials.field', ['name' => 'valid_to', 'label' => 'au', 'type' => 'date', 'col' => 'col-md-2'])
    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary">Enregistrer le taux</button></div>
</form></div></div>
<div class="card"><div class="card-header">Taux ({{ $rates->count() }})</div>
@if($rates->count())<div class="table-responsive"><table class="table table-sm table-stack align-middle"><thead><tr><th>Type</th><th class="num">Montant</th><th>Critères</th><th>Validité</th><th>Actif</th><th></th></tr></thead><tbody>
@foreach($rates as $r)<tr class="{{ $r->is_active ? '' : 'text-muted-2' }}"><td data-l="Type">{{ $r->primeType->name }}</td><td data-l="Montant" class="num fw-600">{{ money($r->amount, $r->currency) }}</td>
    <td data-l="Critères" class="small">{{ collect([$r->academicYear?->code, $r->staff_family ? \App\Support\Labels::status('family', $r->staff_family) : null, $r->category?->name, $r->grade?->name, $r->jobFunction?->name, $r->orgUnit?->name])->filter()->implode(' · ') ?: 'Tous les agents' }}</td>
    <td data-l="Validité">{{ $r->valid_from?->format('d/m/Y') ?? '…' }} → {{ $r->valid_to?->format('d/m/Y') ?? '…' }}</td><td data-l="Actif">{{ $r->is_active ? 'Oui' : 'Non' }}</td>
    <td><form method="post" action="{{ route('rates.update', $r) }}" class="d-flex gap-1">@csrf @method('PUT')<input type="date" name="valid_to" value="{{ $r->valid_to?->toDateString() }}" class="form-control form-control-sm" aria-label="Fin de validité"><input type="hidden" name="is_active" value="0"><div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ra{{ $r->id }}" @checked($r->is_active)><label class="form-check-label small" for="ra{{ $r->id }}">actif</label></div><button class="btn btn-sm btn-light">OK</button></form></td></tr>@endforeach
</tbody></table></div>@else <div class="card-body">@include('partials.empty', ['text' => 'Aucun taux : aucune prime ne peut être générée tant que l’administration n’a pas défini les montants.'])</div> @endif
</div>
@endsection
