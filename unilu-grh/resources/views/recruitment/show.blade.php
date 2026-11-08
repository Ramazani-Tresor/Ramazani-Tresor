@extends('layouts.app')
@section('title', $c->reference)
@section('content')
@include('partials.head', ['title' => $c->fullName(), 'crumbs' => ['Recrutement' => route('recruitment.index')], 'sub' => 'Candidature '.e($c->reference).' · '.\App\Support\Labels::badge('candidate', $c->status)])
<div class="row g-3">
<div class="col-lg-7">
    <div class="card mb-3"><div class="card-header">Dossier de candidature</div><div class="card-body"><dl class="kv">
        <dt>Sexe / naissance</dt><dd>{{ \App\Support\Labels::status('sex', $c->sex) }} · {{ $c->birth_date?->format('d/m/Y') ?? '—' }} {{ $c->birth_place }}</dd>
        <dt>Contacts</dt><dd>{{ $c->phone }} {{ $c->email }} {{ $c->address }}</dd>
        <dt>Famille / grade visé</dt><dd>{{ \App\Support\Labels::status('family', $c->staff_family) }} · {{ $c->grade?->name ?? '—' }}</dd>
        <dt>Structure / poste</dt><dd>{{ $c->orgUnit?->name ?? '—' }} {{ $c->position ? '· '.$c->position->title : '' }}</dd>
        <dt>Diplôme</dt><dd>{{ $c->highest_degree ?: '—' }}</dd><dt>Observations</dt><dd>{{ $c->notes ?: '—' }}</dd>
        @if($c->rejection_reason)<dt>Motif de rejet</dt><dd>{{ $c->rejection_reason }}</dd>@endif
        @if($c->employee)<dt>Engagement</dt><dd>Décision {{ $c->decision_number }} du {{ $c->decision_date?->format('d/m/Y') }} — <a href="{{ route('employees.show', $c->employee) }}">matricule {{ $c->employee->matricule }}</a></dd>@endif
    </dl></div></div>
    <div class="card"><div class="card-header">Pièces numérisées</div>
        <ul class="list-group list-group-flush">@forelse($c->documents as $doc)<li class="list-group-item"><a href="{{ route('recruitment.document', $doc) }}" target="_blank"><i class="bi bi-file-earmark"></i> {{ $doc->title }}</a> <span class="mini text-muted-2">{{ $doc->created_at->format('d/m/Y') }}</span></li>@empty<li class="list-group-item small text-muted-2">Aucune pièce.</li>@endforelse</ul>
        @if(can_do('recruitment.manage') && $c->status !== 'engaged')<div class="card-body border-top"><form method="post" action="{{ route('recruitment.upload', $c) }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf
            @include('partials.field', ['name' => 'title', 'label' => 'Intitulé', 'required' => true, 'col' => 'col-md-5'])
            @include('partials.field', ['name' => 'document', 'label' => 'Fichier', 'type' => 'file', 'required' => true, 'col' => 'col-md-5'])
            <div class="col-md-2"><button class="btn btn-light w-100">Joindre</button></div></form></div>@endif
    </div>
</div>
<div class="col-lg-5">
    @if(can_do('recruitment.manage') && isset(\App\Services\RecruitmentService::TRANSITIONS[$c->status]))
    <div class="card mb-3"><div class="card-header">Instruction</div><div class="card-body"><form method="post" action="{{ route('recruitment.status', $c) }}" class="row g-2">@csrf
        @include('partials.field', ['name' => 'status', 'label' => 'Nouveau statut', 'type' => 'select', 'required' => true, 'options' => collect(\App\Services\RecruitmentService::TRANSITIONS[$c->status])->mapWithKeys(fn ($s) => [$s => \App\Support\Labels::status('candidate', $s)])->all(), 'col' => 'col-12'])
        @include('partials.field', ['name' => 'reason', 'label' => 'Motif (obligatoire en cas de rejet)', 'col' => 'col-12'])
        <div class="col-12"><button class="btn btn-primary">Mettre à jour</button></div></form></div></div>
    @endif
    @if($c->status === 'validated' && can_do('recruitment.engage'))
    <div class="card"><div class="card-header">Décision d’engagement</div><div class="card-body"><form method="post" action="{{ route('recruitment.engage', $c) }}" class="row g-2" data-confirm="Prononcer l’engagement ? Un matricule sera attribué et l’agent intégré au fichier général.">@csrf
        @include('partials.field', ['name' => 'matricule', 'label' => 'Matricule', 'col' => 'col-12', 'help' => 'Vide = attribution automatique ('.config('grh.matricule_prefix').'-AAAA-NNNNN).'])
        @include('partials.field', ['name' => 'decision_number', 'label' => 'N° de décision', 'required' => true, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'decision_date', 'label' => 'Date de décision', 'type' => 'date', 'required' => true, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'signing_authority', 'label' => 'Autorité signataire', 'required' => true, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'engagement_date', 'label' => 'Date d’engagement', 'type' => 'date', 'required' => true, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Affectation initiale', 'type' => 'select', 'required' => true, 'options' => $units->pluck('name', 'id')->all(), 'value' => $c->org_unit_id, 'col' => 'col-12'])
        @include('partials.field', ['name' => 'position_id', 'label' => 'Poste', 'type' => 'select', 'options' => $positions->mapWithKeys(fn ($p) => [$p->id => ['label' => $p->title, 'group' => $p->org_unit_id]])->all(), 'value' => $c->position_id, 'col' => 'col-12', 'attrs' => 'data-filter-by="org_unit_id"'])
        @include('partials.field', ['name' => 'grade_id', 'label' => 'Grade', 'type' => 'select', 'options' => $grades->pluck('name', 'id')->all(), 'value' => $c->grade_id, 'col' => 'col-md-6'])
        @include('partials.field', ['name' => 'staff_category_id', 'label' => 'Catégorie', 'type' => 'select', 'options' => $categories->pluck('name', 'id')->all(), 'value' => $c->staff_category_id, 'col' => 'col-md-6'])
        <div class="col-12"><button class="btn btn-primary"><i class="bi bi-person-check"></i> Engager</button></div></form></div></div>
    @endif
</div>
</div>
@endsection
