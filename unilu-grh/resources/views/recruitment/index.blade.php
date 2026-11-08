@extends('layouts.app')
@section('title', 'Recrutement')
@section('content')
@include('partials.head', ['title' => 'Recrutement et engagements', 'sub' => 'Candidature → examen → validation (par une autre personne) → engagement : attribution du matricule, classification, affectation initiale et intégration au fichier général.'])
<div class="row g-3">
<div class="col-xl-8"><div class="card">
    <div class="card-header"><span>Candidatures ({{ $candidates->total() }})</span><form class="d-flex gap-1" method="get"><select name="status" class="form-select form-select-sm" aria-label="Statut"><option value="">Tous les statuts</option>@foreach(\App\Support\Labels::options('candidate') as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select><button class="btn btn-sm btn-light">Filtrer</button></form></div>
    @if($candidates->count())<div class="table-responsive"><table class="table table-hover table-stack align-middle"><thead><tr><th>Référence</th><th>Candidat</th><th>Famille / grade visé</th><th>Structure</th><th>Statut</th></tr></thead><tbody>
    @foreach($candidates as $c)<tr><td data-l="Référence"><a href="{{ route('recruitment.show', $c) }}" class="fw-600">{{ $c->reference }}</a></td><td data-l="Candidat">{{ $c->fullName() }}</td><td data-l="Visé">{{ \App\Support\Labels::status('family', $c->staff_family) }} · {{ $c->grade?->name }}</td><td data-l="Structure">{{ $c->orgUnit?->name }}</td><td data-l="Statut">{!! \App\Support\Labels::badge('candidate', $c->status) !!}</td></tr>@endforeach
    </tbody></table></div><div class="card-body border-top">{{ $candidates->links() }}</div>@else <div class="card-body">@include('partials.empty', ['icon' => 'bi-person-plus', 'text' => 'Aucune candidature.'])</div> @endif
</div></div>
<div class="col-xl-4">@if(can_do('recruitment.manage'))<div class="card"><div class="card-header">Nouvelle candidature</div><div class="card-body"><form method="post" action="{{ route('recruitment.store') }}" class="row g-2">@csrf
    @include('partials.field', ['name' => 'last_name', 'label' => 'Nom', 'required' => true, 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'middle_name', 'label' => 'Postnom', 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'first_name', 'label' => 'Prénom', 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'sex', 'label' => 'Sexe', 'type' => 'select', 'required' => true, 'options' => \App\Support\Labels::options('sex'), 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'birth_date', 'label' => 'Date de naissance', 'type' => 'date', 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'phone', 'label' => 'Téléphone', 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'email', 'label' => 'Courriel', 'type' => 'email', 'col' => 'col-12'])
    @include('partials.field', ['name' => 'staff_family', 'label' => 'Famille', 'type' => 'select', 'required' => true, 'options' => \App\Support\PermissionRegistry::FAMILIES, 'col' => 'col-12'])
    @include('partials.field', ['name' => 'grade_id', 'label' => 'Grade visé', 'type' => 'select', 'options' => $grades->mapWithKeys(fn ($g) => [$g->id => ['label' => $g->name, 'group' => $g->family]])->all(), 'col' => 'col-12', 'attrs' => 'data-filter-by="staff_family"'])
    @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Structure', 'type' => 'select', 'options' => $units->pluck('name', 'id')->all(), 'col' => 'col-12'])
    @include('partials.field', ['name' => 'highest_degree', 'label' => 'Diplôme le plus élevé', 'col' => 'col-12'])
    @include('partials.field', ['name' => 'notes', 'label' => 'Observations', 'type' => 'textarea', 'col' => 'col-12'])
    <div class="col-12"><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>@endif</div>
</div>
@endsection
