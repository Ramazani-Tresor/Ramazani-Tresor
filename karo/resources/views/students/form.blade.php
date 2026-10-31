@extends('layouts.app')
@section('title', $student->exists ? 'Modifier le dossier' : 'Nouveau dossier étudiant')
@section('content')
@include('partials.head', ['title' => $student->exists ? 'Modifier le dossier '.$student->matricule : 'Nouveau dossier étudiant', 'crumbs' => ['Étudiants' => route('students.index')]])
<form method="post" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}" class="card" style="max-width:900px">@csrf @if($student->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label" for="m">Matricule *</label><input id="m" name="matricule" class="form-control" value="{{ old('matricule', $student->matricule) }}" required maxlength="40"></div>
        <div class="col-md-4"><label class="form-label" for="ln">Nom *</label><input id="ln" name="last_name" class="form-control" value="{{ old('last_name', $student->last_name) }}" required maxlength="100"></div>
        <div class="col-md-4"><label class="form-label" for="pn">Postnom</label><input id="pn" name="post_name" class="form-control" value="{{ old('post_name', $student->post_name) }}" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label" for="fn">Prénom *</label><input id="fn" name="first_name" class="form-control" value="{{ old('first_name', $student->first_name) }}" required maxlength="100"></div>
        <div class="col-md-2"><label class="form-label" for="g">Sexe</label><select id="g" name="gender" class="form-select"><option value="">—</option><option value="M" @selected(old('gender', $student->gender) === 'M')>M</option><option value="F" @selected(old('gender', $student->gender) === 'F')>F</option></select></div>
        <div class="col-md-3"><label class="form-label" for="bd">Date de naissance</label><input id="bd" type="date" name="birth_date" class="form-control" value="{{ old('birth_date', $student->birth_date?->toDateString()) }}"></div>
        <div class="col-md-3"><label class="form-label" for="ph">Téléphone</label><input id="ph" name="phone" class="form-control" value="{{ old('phone', $student->phone) }}" maxlength="40"></div>
        <div class="col-md-6"><label class="form-label" for="em">E-mail</label><input id="em" type="email" name="email" class="form-control" value="{{ old('email', $student->email) }}" maxlength="150"></div>
        <div class="col-md-6"><label class="form-label" for="ad">Adresse</label><input id="ad" name="address" class="form-control" value="{{ old('address', $student->address) }}" maxlength="255"></div>
        @unless($student->exists)
            <div class="col-12"><hr class="my-1"><div class="fw-600 text-navy">Inscription</div></div>
            <div class="col-md-4"><label class="form-label" for="y">Année académique *</label><select id="y" name="academic_year_id" class="form-select" required>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->code }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="pr">Promotion *</label><select id="pr" name="promotion_id" class="form-select" required>@foreach($promotions as $p)<option value="{{ $p->id }}" @selected(old('promotion_id') == $p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="st">Statut *</label><select id="st" name="status" class="form-select">@foreach(\App\Support\Labels::STATUSES['enrollment_status'] as $k => $v)<option value="{{ $k }}" @selected(old('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        @endunless
    </div>
    <div class="card-footer bg-white d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a href="{{ $student->exists ? route('students.show', $student) : route('students.index') }}" class="btn btn-light">Annuler</a></div>
</form>
@endsection
