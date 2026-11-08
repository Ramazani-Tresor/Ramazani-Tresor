@extends('layouts.app')
@section('title', $employee->exists ? 'Modifier le dossier' : 'Nouveau dossier')
@php
    $locked = $employee->exists && $employee->careerEvents()->where('status', 'validated')->exists();
    $opt = fn ($c, $label = 'name') => $c->mapWithKeys(fn ($x) => [$x->id => $x->$label])->all();
    $gradeOpts = $grades->mapWithKeys(fn ($g) => [$g->id => ['label' => $g->name.' ('.\App\Support\Labels::status('family', $g->family).')', 'group' => $g->family]])->all();
    $catOpts = $categories->mapWithKeys(fn ($c) => [$c->id => ['label' => $c->name, 'group' => $c->family]])->all();
@endphp
@section('content')
@include('partials.head', ['title' => $employee->exists ? 'Modifier le dossier '.$employee->matricule : 'Nouveau dossier d’agent', 'crumbs' => ['Agents' => route('employees.index')], 'sub' => 'Chaque modification est historisée (ancienne et nouvelle valeur, auteur, date).'])
<form method="post" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="card"><div class="card-body">@csrf @if($employee->exists) @method('PUT') @endif
<div class="row g-3">
    <div class="col-12"><div class="form-section">Identité</div></div>
    @include('partials.field', ['name' => 'matricule', 'label' => 'Matricule', 'value' => $employee->matricule, 'col' => 'col-md-3', 'help' => $employee->exists ? 'Unique et définitif.' : 'Laisser vide pour une attribution automatique.', 'attrs' => $employee->exists ? 'readonly' : ''])
    @include('partials.field', ['name' => 'last_name', 'label' => 'Nom', 'value' => $employee->last_name, 'required' => true, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'middle_name', 'label' => 'Postnom', 'value' => $employee->middle_name, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'first_name', 'label' => 'Prénom', 'value' => $employee->first_name, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'sex', 'label' => 'Sexe', 'type' => 'select', 'options' => \App\Support\Labels::options('sex'), 'value' => $employee->sex, 'required' => true, 'col' => 'col-md-2'])
    @include('partials.field', ['name' => 'birth_date', 'label' => 'Date de naissance', 'type' => 'date', 'value' => $employee->birth_date, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'birth_place', 'label' => 'Lieu de naissance', 'value' => $employee->birth_place, 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'nationality', 'label' => 'Nationalité', 'value' => $employee->nationality, 'col' => 'col-md-3'])
    <div class="col-12"><div class="form-section">Coordonnées et situation familiale</div></div>
    @include('partials.field', ['name' => 'address', 'label' => 'Adresse', 'value' => $employee->address, 'col' => 'col-md-6'])
    @include('partials.field', ['name' => 'phone', 'label' => 'Téléphone', 'value' => $employee->phone, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'email', 'label' => 'Adresse électronique', 'type' => 'email', 'value' => $employee->email, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'marital_status', 'label' => 'État civil', 'type' => 'select', 'options' => \App\Support\Labels::options('marital'), 'value' => $employee->marital_status, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'children_count', 'label' => 'Nombre d’enfants', 'type' => 'number', 'value' => $employee->children_count ?? 0, 'col' => 'col-md-2', 'attrs' => 'min="0" max="40"'])
    @include('partials.field', ['name' => 'family_notes', 'label' => 'Situation familiale (précisions)', 'value' => $employee->family_notes, 'col' => 'col-md-7'])
    @include('partials.field', ['name' => 'emergency_contact_name', 'label' => 'Personne à contacter en cas d’urgence', 'value' => $employee->emergency_contact_name, 'col' => 'col-md-5'])
    @include('partials.field', ['name' => 'emergency_contact_phone', 'label' => 'Téléphone', 'value' => $employee->emergency_contact_phone, 'col' => 'col-md-3'])
    @include('partials.field', ['name' => 'emergency_contact_relation', 'label' => 'Lien', 'value' => $employee->emergency_contact_relation, 'col' => 'col-md-4'])
    <div class="col-12"><div class="form-section">Classification professionnelle</div>@if($locked)<div class="alert alert-info small mb-0">Une décision de carrière est validée : le grade, la catégorie et le mandat ne changent plus que par une nouvelle décision (onglet « Carrière »).</div>@endif</div>
    @if($locked)<input type="hidden" name="staff_family" value="{{ $employee->staff_family }}">@endif
    @include('partials.field', ['name' => 'staff_family', 'label' => 'Famille de personnel', 'type' => 'select', 'options' => \App\Support\PermissionRegistry::FAMILIES, 'value' => $employee->staff_family, 'required' => true, 'col' => 'col-md-4', 'attrs' => $locked ? 'disabled' : ''])
    @include('partials.field', ['name' => 'staff_category_id', 'label' => 'Catégorie', 'type' => 'select', 'options' => $catOpts, 'value' => $employee->staff_category_id, 'col' => 'col-md-4', 'attrs' => ($locked ? 'disabled ' : '').'data-filter-by="staff_family"'])
    @include('partials.field', ['name' => 'grade_id', 'label' => 'Grade', 'type' => 'select', 'options' => $gradeOpts, 'value' => $employee->grade_id, 'col' => 'col-md-4', 'attrs' => ($locked ? 'disabled ' : '').'data-filter-by="staff_family"'])
    @include('partials.field', ['name' => 'mandate_id', 'label' => 'Mandat (assistants)', 'type' => 'select', 'options' => $opt($mandates), 'value' => $employee->mandate_id, 'col' => 'col-md-4', 'attrs' => $locked ? 'disabled' : ''])
    @include('partials.field', ['name' => 'mandate_start', 'label' => 'Début du mandat', 'type' => 'date', 'value' => $employee->mandate_start, 'col' => 'col-md-4', 'attrs' => $locked ? 'disabled' : ''])
    @include('partials.field', ['name' => 'mandate_end', 'label' => 'Fin du mandat', 'type' => 'date', 'value' => $employee->mandate_end, 'col' => 'col-md-4', 'attrs' => $locked ? 'disabled' : ''])
    @include('partials.field', ['name' => 'assistant_track', 'label' => 'Fonction d’assistant', 'type' => 'select', 'options' => \App\Support\Labels::options('track'), 'value' => $employee->assistant_track, 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'admin_grade_id', 'label' => 'Catégorie administrative (assistant de recherche)', 'type' => 'select', 'options' => $opt($adminGrades), 'value' => $employee->admin_grade_id, 'col' => 'col-md-4', 'help' => 'Ex. Chef de bureau — distincte de la fonction scientifique.'])
    @include('partials.field', ['name' => 'job_function_id', 'label' => 'Fonction principale / responsabilité', 'type' => 'select', 'options' => $opt($functions), 'value' => $employee->job_function_id, 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'first_service_date', 'label' => 'Première prise de service', 'type' => 'date', 'value' => $employee->first_service_date, 'col' => 'col-md-4'])
    @include('partials.field', ['name' => 'engagement_date', 'label' => 'Engagement à l’université', 'type' => 'date', 'value' => $employee->engagement_date, 'col' => 'col-md-4'])
    @unless($employee->exists)
        <div class="col-12"><div class="form-section">Affectation initiale</div></div>
        @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Faculté, département ou service', 'type' => 'select', 'options' => $opt($units), 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'position_id', 'label' => 'Poste', 'type' => 'select', 'options' => $positions->mapWithKeys(fn ($p) => [$p->id => ['label' => $p->title, 'group' => $p->org_unit_id]])->all(), 'col' => 'col-md-6', 'attrs' => 'data-filter-by="org_unit_id"'])
        @if(can_do('employees.bank'))
            <div class="col-12"><div class="form-section">Coordonnées bancaires (chiffrées, accès restreint)</div></div>
            @include('partials.field', ['name' => 'bank_name', 'label' => 'Banque', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'bank_account', 'label' => 'Numéro de compte', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'bank_holder', 'label' => 'Titulaire', 'col' => 'col-md-4'])
        @endif
    @endunless
</div>
</div><div class="card-footer bg-white d-flex gap-2 justify-content-end"><a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn-light">Annuler</a><button class="btn btn-primary"><i class="bi bi-check2"></i> Enregistrer</button></div></form>
@endsection
