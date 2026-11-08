@extends('layouts.app')
@section('title', 'Mon espace')
@php $d = fn ($v) => $v ? $v->format('d/m/Y') : '—'; $L = fn ($g, $v) => \App\Support\Labels::status($g, $v); @endphp
@section('content')
@include('partials.head', ['title' => 'Mon espace personnel', 'sub' => 'Consultation de votre dossier. Pour toute erreur, soumettez une demande de correction : les décisions administratives ne se modifient pas directement.', 'actions' => '<a class="btn btn-light" href="'.route('me.sheet').'" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Ma fiche individuelle</a><a class="btn btn-primary" href="'.route('me.corrections').'"><i class="bi bi-pencil-square"></i> Demander une correction</a>'])
@include('me._tabs')
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-body d-flex gap-3">
        @if($e->photo_path)<img src="{{ route('employees.photo.show', $e) }}" class="photo-id" alt="Ma photo">@else<div class="photo-placeholder"><i class="bi bi-person"></i></div>@endif
        <dl class="kv flex-grow-1"><dt>Matricule</dt><dd class="fw-600">{{ $e->matricule }}</dd><dt>Nom complet</dt><dd>{{ $e->fullName() }}</dd><dt>Naissance</dt><dd>{{ $d($e->birth_date) }} {{ $e->birth_place }}</dd><dt>État civil</dt><dd>{{ $L('marital', $e->marital_status) }} · {{ $e->children_count }} enfant(s)</dd><dt>Téléphone</dt><dd>{{ $e->phone ?: '—' }}</dd><dt>Courriel</dt><dd>{{ $e->email ?: '—' }}</dd><dt>Adresse</dt><dd>{{ $e->address ?: '—' }}</dd><dt>Personne à contacter</dt><dd>{{ $e->emergency_contact_name ?: '—' }} {{ $e->emergency_contact_phone }}</dd></dl>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Situation administrative</div><div class="card-body"><dl class="kv">
        <dt>Situation</dt><dd>{!! \App\Support\Labels::badge('employee', $e->status) !!}</dd><dt>Famille</dt><dd>{{ $L('family', $e->staff_family) }}</dd><dt>Grade</dt><dd>{{ $e->grade?->name ?? '—' }}</dd><dt>Catégorie</dt><dd>{{ $e->category?->name ?? '—' }}</dd>
        <dt>Mandat</dt><dd>{{ $e->mandate?->name ?? '—' }} @if($e->mandate_end)(jusqu’au {{ $d($e->mandate_end) }})@endif</dd><dt>Fonction</dt><dd>{{ $e->jobFunction?->name ?? '—' }}</dd>
        <dt>Affectation</dt><dd>{{ $e->activeAssignment?->orgUnit?->pathName() ?? '—' }}</dd><dt>Poste</dt><dd>{{ $e->activeAssignment?->position?->title ?? '—' }}</dd><dt>Engagement</dt><dd>{{ $d($e->engagement_date) }}</dd>
    </dl></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Mes diplômes enregistrés</div><ul class="list-group list-group-flush">@forelse($qualifications as $q)<li class="list-group-item"><b>{{ $L('qualification', $q->nature) }}</b> {{ $q->title ? '— '.$q->title : '' }}<br><span class="small text-muted-2">{{ $q->defense_university ?: $q->institution }} · {{ $d($q->defense_date) }} {{ $q->mention ? '· '.$q->mention : '' }}</span></li>@empty<li class="list-group-item small text-muted-2">Aucun diplôme enregistré.</li>@endforelse</ul></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Documents téléchargeables</div><ul class="list-group list-group-flush">@forelse($documents as $doc)<li class="list-group-item"><a href="{{ route('documents.download', $doc) }}" target="_blank"><i class="bi bi-file-earmark"></i> {{ $doc->title }}</a> <span class="mini text-muted-2">{{ $doc->type?->name }} · {{ $d($doc->document_date) }}</span></li>@empty<li class="list-group-item small text-muted-2">Aucun document mis à disposition.</li>@endforelse</ul></div></div>
</div>
@endsection
